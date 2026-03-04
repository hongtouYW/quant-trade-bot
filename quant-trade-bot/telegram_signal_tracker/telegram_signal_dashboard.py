#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Telegram Signal Tracker - 信号跟踪模拟交易仪表盘
Port: 5112
手动录入 Telegram 群信号，模拟交易跟踪盈亏
"""

from flask import Flask, jsonify, render_template_string, request
import sqlite3
import threading
import requests
import time
import json
import os
import re
import asyncio
from datetime import datetime, timedelta, timezone

app = Flask(__name__)

DB_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'telegram_signals.db')
BINANCE_FUTURES_API = 'https://fapi.binance.com'

# ===== Telegram Config =====
TG_API_ID = 37356394
TG_API_HASH = '02b91c774b0ae70701daaff905cbd295'
TG_GROUP = 'kokoworld886'
TG_SESSION_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'tg_session')

tg_client = None
tg_auth_phone = None
tg_auth_hash = None
tg_status = {'connected': False, 'listening': False, 'last_message': None, 'error': None}

# Persistent event loop for all Telethon operations (fixes "event loop must not change" error)
_tg_loop = None
_tg_loop_thread = None

def _run_tg_loop(loop):
    asyncio.set_event_loop(loop)
    loop.run_forever()

def get_tg_loop():
    """Get or create the persistent event loop for Telethon"""
    global _tg_loop, _tg_loop_thread
    if _tg_loop is None or _tg_loop.is_closed():
        _tg_loop = asyncio.new_event_loop()
        _tg_loop_thread = threading.Thread(target=_run_tg_loop, args=(_tg_loop,), daemon=True)
        _tg_loop_thread.start()
    return _tg_loop

# ===== Database =====

def get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA journal_mode=WAL")
    return conn

def init_db():
    conn = get_db()
    conn.executescript('''
        CREATE TABLE IF NOT EXISTS signals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            signal_time TIMESTAMP,
            source TEXT DEFAULT 'manual',
            category TEXT DEFAULT '赌狗日记',
            symbol TEXT NOT NULL,
            direction TEXT NOT NULL,
            entry_price REAL,
            stop_loss REAL NOT NULL,
            take_profit REAL NOT NULL,
            leverage INTEGER DEFAULT 10,
            status TEXT DEFAULT 'pending',
            raw_message TEXT,
            notes TEXT
        );

        CREATE TABLE IF NOT EXISTS trades (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            signal_id INTEGER REFERENCES signals(id),
            opened_at TIMESTAMP,
            closed_at TIMESTAMP,
            symbol TEXT NOT NULL,
            direction TEXT NOT NULL,
            entry_price REAL NOT NULL,
            exit_price REAL,
            leverage INTEGER,
            position_size REAL,
            pnl REAL,
            pnl_pct REAL,
            fees REAL,
            close_reason TEXT,
            status TEXT DEFAULT 'open'
        );

        CREATE TABLE IF NOT EXISTS account_config (
            key TEXT PRIMARY KEY,
            value TEXT
        );

        CREATE TABLE IF NOT EXISTS daily_pnl (
            date TEXT PRIMARY KEY,
            pnl REAL,
            trades_count INTEGER,
            win_count INTEGER,
            cumulative_pnl REAL
        );
    ''')
    # Migrations
    for sql in [
        "ALTER TABLE signals ADD COLUMN category TEXT DEFAULT '赌狗日记'",
        "ALTER TABLE signals ADD COLUMN signal_time TIMESTAMP",
    ]:
        try:
            conn.execute(sql)
        except:
            pass

    # Default config
    defaults = {
        'initial_capital': '2000',
        'fee_rate': '0.05',
        'funding_rate': '0.01',
        'position_pct': '10',
    }
    for k, v in defaults.items():
        conn.execute('INSERT OR IGNORE INTO account_config (key, value) VALUES (?, ?)', (k, v))
    conn.commit()
    conn.close()

def get_config():
    conn = get_db()
    rows = conn.execute('SELECT key, value FROM account_config').fetchall()
    conn.close()
    return {r['key']: r['value'] for r in rows}

def get_capital():
    """Calculate current available capital"""
    config = get_config()
    initial = float(config.get('initial_capital', 2000))
    conn = get_db()
    # Total realized PnL
    row = conn.execute('SELECT COALESCE(SUM(pnl), 0) as total_pnl, COALESCE(SUM(fees), 0) as total_fees FROM trades WHERE status = ?', ('closed',)).fetchone()
    total_pnl = row['total_pnl']
    total_fees = row['total_fees']
    # Capital used in open positions
    row2 = conn.execute('SELECT COALESCE(SUM(position_size), 0) as used FROM trades WHERE status = ?', ('open',)).fetchone()
    used = row2['used']
    conn.close()
    total_capital = initial + total_pnl - total_fees
    available = total_capital - used
    return {'total': round(total_capital, 2), 'available': round(available, 2), 'used': round(used, 2), 'initial': initial}

def get_binance_price(symbol):
    """Get current price from Binance Futures"""
    try:
        # Ensure USDT suffix
        sym = symbol.upper()
        if not sym.endswith('USDT'):
            sym = sym + 'USDT'
        resp = requests.get(f'{BINANCE_FUTURES_API}/fapi/v1/ticker/price', params={'symbol': sym}, timeout=5)
        data = resp.json()
        return float(data['price'])
    except Exception as e:
        print(f"[Price Error] {symbol}: {e}")
        return None

def auto_activate_signal(signal_id):
    """Auto-activate a signal: fetch price, calculate size, open trade"""
    try:
        conn = get_db()
        signal = conn.execute('SELECT * FROM signals WHERE id=?', (signal_id,)).fetchone()
        if not signal or signal['status'] != 'pending':
            conn.close()
            return False

        price = get_binance_price(signal['symbol'])
        if price is None:
            print(f"[Auto-Activate] Cannot get price for {signal['symbol']}, skipping")
            conn.close()
            return False

        entry_price = signal['entry_price'] if signal['entry_price'] else price

        cap = get_capital()
        config = get_config()
        pct = float(config.get('position_pct', 10))
        position_size = min(cap['available'] * (pct / 100), cap['available'] * 0.5)
        if position_size < 10:
            print(f"[Auto-Activate] Insufficient capital ({cap['available']:.0f}U), skipping")
            conn.close()
            return False

        now = datetime.now(timezone.utc).isoformat()
        leverage = signal['leverage']

        conn.execute('''
            INSERT INTO trades (signal_id, opened_at, symbol, direction, entry_price, leverage, position_size, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'open')
        ''', (signal_id, now, signal['symbol'], signal['direction'], entry_price, leverage, round(position_size, 2)))
        conn.execute('UPDATE signals SET status=?, entry_price=? WHERE id=?', ('active', entry_price, signal_id))
        conn.commit()
        conn.close()

        print(f"[Auto-Activate] {signal['symbol']} {signal['direction']} @ {entry_price:.6g}, size={position_size:.0f}U, lev={leverage}x")
        return True
    except Exception as e:
        print(f"[Auto-Activate Error] {e}")
        return False

def calculate_fees(position_size, entry_price, exit_price, leverage, opened_at, closed_at, fee_rate=0.05, funding_rate=0.01):
    """Calculate trading fees"""
    notional = position_size * leverage
    # Entry + exit fees
    entry_fee = notional * (fee_rate / 100)
    exit_fee = notional * (fee_rate / 100)
    # Funding fees (every 8h)
    if opened_at and closed_at:
        try:
            if isinstance(opened_at, str):
                opened_at = datetime.fromisoformat(opened_at.replace('Z', '+00:00'))
            if isinstance(closed_at, str):
                closed_at = datetime.fromisoformat(closed_at.replace('Z', '+00:00'))
            hours = max((closed_at - opened_at).total_seconds() / 3600, 0)
        except:
            hours = 0
    else:
        hours = 0
    funding_periods = hours / 8
    funding_fee = notional * (funding_rate / 100) * funding_periods
    total = entry_fee + exit_fee + funding_fee
    return round(total, 4)

# ===== Position Monitor Thread =====

monitor_running = False

def position_monitor():
    """Background thread: check open positions every 30s for TP/SL"""
    global monitor_running
    monitor_running = True
    print("[Monitor] Position monitor started")
    while monitor_running:
        try:
            conn = get_db()
            open_trades = conn.execute('''
                SELECT t.*, s.stop_loss, s.take_profit, s.direction as sig_dir
                FROM trades t JOIN signals s ON t.signal_id = s.id
                WHERE t.status = 'open'
            ''').fetchall()
            conn.close()

            for trade in open_trades:
                symbol = trade['symbol']
                price = get_binance_price(symbol)
                if price is None:
                    continue

                direction = trade['direction']
                entry = trade['entry_price']
                sl = trade['stop_loss']
                tp = trade['take_profit']
                close_reason = None

                if direction == 'LONG':
                    if price <= sl:
                        close_reason = 'sl'
                    elif price >= tp:
                        close_reason = 'tp'
                elif direction == 'SHORT':
                    if price >= sl:
                        close_reason = 'sl'
                    elif price <= tp:
                        close_reason = 'tp'

                if close_reason:
                    close_trade(trade['id'], price, close_reason)
                    print(f"[Monitor] Auto-closed {symbol} {direction} @ {price} ({close_reason.upper()})")

        except Exception as e:
            print(f"[Monitor Error] {e}")

        time.sleep(30)

def close_trade(trade_id, exit_price, reason):
    """Close a trade and calculate PnL"""
    conn = get_db()
    trade = conn.execute('SELECT * FROM trades WHERE id = ?', (trade_id,)).fetchone()
    if not trade or trade['status'] != 'open':
        conn.close()
        return None

    now = datetime.now(timezone.utc).isoformat()
    entry = trade['entry_price']
    direction = trade['direction']
    leverage = trade['leverage']
    size = trade['position_size']

    # PnL calculation
    if direction == 'LONG':
        pnl_pct = ((exit_price - entry) / entry) * leverage * 100
    else:
        pnl_pct = ((entry - exit_price) / entry) * leverage * 100
    pnl = size * (pnl_pct / 100)

    # Fees
    config = get_config()
    fees = calculate_fees(size, entry, exit_price, leverage,
                         trade['opened_at'], now,
                         float(config.get('fee_rate', 0.05)),
                         float(config.get('funding_rate', 0.01)))

    pnl_after_fees = pnl - fees

    conn.execute('''
        UPDATE trades SET exit_price=?, closed_at=?, pnl=?, pnl_pct=?, fees=?,
                         close_reason=?, status='closed'
        WHERE id=?
    ''', (exit_price, now, round(pnl_after_fees, 4), round(pnl_pct, 2), fees, reason, trade_id))

    conn.execute('UPDATE signals SET status=? WHERE id=?', ('closed', trade['signal_id']))

    # Update daily PnL
    today = datetime.now(timezone.utc).strftime('%Y-%m-%d')
    existing = conn.execute('SELECT * FROM daily_pnl WHERE date=?', (today,)).fetchone()
    if existing:
        new_pnl = existing['pnl'] + pnl_after_fees
        new_count = existing['trades_count'] + 1
        new_wins = existing['win_count'] + (1 if pnl_after_fees > 0 else 0)
        conn.execute('UPDATE daily_pnl SET pnl=?, trades_count=?, win_count=? WHERE date=?',
                    (round(new_pnl, 4), new_count, new_wins, today))
    else:
        conn.execute('INSERT INTO daily_pnl (date, pnl, trades_count, win_count) VALUES (?, ?, ?, ?)',
                    (today, round(pnl_after_fees, 4), 1, 1 if pnl_after_fees > 0 else 0))

    # Update cumulative
    rows = conn.execute('SELECT date, pnl FROM daily_pnl ORDER BY date').fetchall()
    cum = 0
    for r in rows:
        cum += r['pnl']
        conn.execute('UPDATE daily_pnl SET cumulative_pnl=? WHERE date=?', (round(cum, 4), r['date']))

    conn.commit()
    conn.close()
    return {'pnl': round(pnl_after_fees, 4), 'pnl_pct': round(pnl_pct, 2), 'fees': fees}

# ===== API Endpoints =====

@app.route('/api/stats')
def api_stats():
    """Overall statistics"""
    cap = get_capital()
    conn = get_db()
    # Trade stats
    closed = conn.execute('SELECT COUNT(*) as cnt, COALESCE(SUM(pnl),0) as total_pnl, COALESCE(SUM(fees),0) as total_fees FROM trades WHERE status=?', ('closed',)).fetchone()
    wins = conn.execute('SELECT COUNT(*) as cnt FROM trades WHERE status=? AND pnl > 0', ('closed',)).fetchone()
    open_count = conn.execute('SELECT COUNT(*) as cnt FROM trades WHERE status=?', ('open',)).fetchone()
    # Today's PnL
    today = datetime.now(timezone.utc).strftime('%Y-%m-%d')
    today_row = conn.execute('SELECT COALESCE(pnl, 0) as pnl FROM daily_pnl WHERE date=?', (today,)).fetchone()
    # Pending signals
    pending = conn.execute('SELECT COUNT(*) as cnt FROM signals WHERE status=?', ('pending',)).fetchone()
    conn.close()

    total_trades = closed['cnt']
    win_rate = round(wins['cnt'] / total_trades * 100, 1) if total_trades > 0 else 0

    return jsonify({
        'total_capital': cap['total'],
        'available': cap['available'],
        'used': cap['used'],
        'initial_capital': cap['initial'],
        'total_pnl': round(closed['total_pnl'], 2),
        'total_fees': round(closed['total_fees'], 2),
        'pnl_pct': round(closed['total_pnl'] / cap['initial'] * 100, 2) if cap['initial'] > 0 else 0,
        'total_trades': total_trades,
        'win_count': wins['cnt'],
        'win_rate': win_rate,
        'open_positions': open_count['cnt'],
        'today_pnl': round(today_row['pnl'], 2) if today_row else 0,
        'pending_signals': pending['cnt'],
    })

@app.route('/api/signal', methods=['POST'])
def api_add_signal():
    """Add a new signal"""
    data = request.json
    required = ['symbol', 'direction', 'stop_loss', 'take_profit']
    for f in required:
        if f not in data or data[f] is None:
            return jsonify({'error': f'Missing field: {f}'}), 400

    symbol = data['symbol'].upper().strip()
    if not symbol.endswith('USDT'):
        symbol = symbol + 'USDT'
    direction = data['direction'].upper()
    if direction not in ('LONG', 'SHORT'):
        return jsonify({'error': 'Direction must be LONG or SHORT'}), 400

    signal_time = data.get('signal_time') or datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')
    conn = get_db()
    conn.execute('''
        INSERT INTO signals (symbol, direction, entry_price, stop_loss, take_profit, leverage, raw_message, notes, category, signal_time)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ''', (symbol, direction,
          data.get('entry_price'),
          float(data['stop_loss']),
          float(data['take_profit']),
          int(data.get('leverage', 10)),
          data.get('raw_message', ''),
          data.get('notes', ''),
          data.get('category', '赌狗日记'),
          signal_time))
    signal_id = conn.execute('SELECT last_insert_rowid()').fetchone()[0]
    conn.commit()
    conn.close()
    return jsonify({'ok': True, 'id': signal_id})

@app.route('/api/signals')
def api_signals():
    """List signals with optional status/category filter"""
    status = request.args.get('status', '')
    category = request.args.get('category', '')
    conn = get_db()
    conditions = []
    params = []
    if status:
        conditions.append('status=?')
        params.append(status)
    if category:
        conditions.append('category=?')
        params.append(category)
    where = ' WHERE ' + ' AND '.join(conditions) if conditions else ''
    rows = conn.execute(f'SELECT * FROM signals{where} ORDER BY COALESCE(signal_time, created_at) DESC LIMIT 200', params).fetchall()
    conn.close()
    return jsonify([dict(r) for r in rows])

@app.route('/api/categories')
def api_categories():
    """List all distinct categories"""
    conn = get_db()
    rows = conn.execute("SELECT DISTINCT category FROM signals WHERE category IS NOT NULL AND category != '' ORDER BY category").fetchall()
    conn.close()
    return jsonify([r['category'] for r in rows])

@app.route('/api/signal/<int:sid>/activate', methods=['PUT'])
def api_activate_signal(sid):
    """Activate signal: open a trade"""
    conn = get_db()
    signal = conn.execute('SELECT * FROM signals WHERE id=?', (sid,)).fetchone()
    if not signal:
        conn.close()
        return jsonify({'error': 'Signal not found'}), 404
    if signal['status'] != 'pending':
        conn.close()
        return jsonify({'error': f'Signal is {signal["status"]}, not pending'}), 400

    # Get current price
    price = get_binance_price(signal['symbol'])
    if price is None:
        conn.close()
        return jsonify({'error': f'Cannot get price for {signal["symbol"]}'}), 500

    entry_price = signal['entry_price'] if signal['entry_price'] else price

    # Calculate position size
    cap = get_capital()
    config = get_config()
    pct = float(config.get('position_pct', 10))
    position_size = min(cap['available'] * (pct / 100), cap['available'] * 0.5)
    if position_size < 10:
        conn.close()
        return jsonify({'error': 'Insufficient capital'}), 400

    now = datetime.now(timezone.utc).isoformat()
    leverage = signal['leverage']

    # Entry fee deducted from size
    fee_rate = float(config.get('fee_rate', 0.05))
    entry_fee = position_size * leverage * (fee_rate / 100)

    conn.execute('''
        INSERT INTO trades (signal_id, opened_at, symbol, direction, entry_price, leverage, position_size, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'open')
    ''', (sid, now, signal['symbol'], signal['direction'], entry_price, leverage, round(position_size, 2)))

    conn.execute('UPDATE signals SET status=?, entry_price=? WHERE id=?', ('active', entry_price, sid))
    conn.commit()
    conn.close()

    return jsonify({'ok': True, 'entry_price': entry_price, 'position_size': round(position_size, 2), 'entry_fee': round(entry_fee, 4)})

@app.route('/api/signal/<int:sid>/close', methods=['PUT'])
def api_close_signal(sid):
    """Manually close a signal's trade"""
    conn = get_db()
    trade = conn.execute('SELECT * FROM trades WHERE signal_id=? AND status=?', (sid, 'open')).fetchone()
    if not trade:
        conn.close()
        return jsonify({'error': 'No open trade for this signal'}), 404

    price = get_binance_price(trade['symbol'])
    if price is None:
        conn.close()
        return jsonify({'error': 'Cannot get price'}), 500

    conn.close()
    result = close_trade(trade['id'], price, 'manual')
    return jsonify({'ok': True, **result})

@app.route('/api/signal/<int:sid>/cancel', methods=['PUT'])
def api_cancel_signal(sid):
    """Cancel a pending signal"""
    conn = get_db()
    signal = conn.execute('SELECT * FROM signals WHERE id=?', (sid,)).fetchone()
    if not signal:
        conn.close()
        return jsonify({'error': 'Signal not found'}), 404
    if signal['status'] != 'pending':
        conn.close()
        return jsonify({'error': 'Can only cancel pending signals'}), 400
    conn.execute('UPDATE signals SET status=? WHERE id=?', ('cancelled', sid))
    conn.commit()
    conn.close()
    return jsonify({'ok': True})

@app.route('/api/trades')
def api_trades():
    """Trade history"""
    conn = get_db()
    rows = conn.execute('''
        SELECT t.*, s.stop_loss as sl, s.take_profit as tp, s.raw_message, s.notes
        FROM trades t JOIN signals s ON t.signal_id = s.id
        ORDER BY t.opened_at DESC LIMIT 200
    ''').fetchall()
    conn.close()
    return jsonify([dict(r) for r in rows])

@app.route('/api/trade/<int:tid>')
def api_trade_detail(tid):
    """Trade detail with kline data for chart"""
    conn = get_db()
    trade = conn.execute('''
        SELECT t.*, s.stop_loss as sl, s.take_profit as tp, s.raw_message, s.notes
        FROM trades t JOIN signals s ON t.signal_id = s.id
        WHERE t.id = ?
    ''', (tid,)).fetchone()
    conn.close()
    if not trade:
        return jsonify({'error': 'Trade not found'}), 404

    trade_dict = dict(trade)

    # Fetch kline data for chart
    symbol = trade['symbol']
    try:
        end_time = trade['closed_at'] or datetime.now(timezone.utc).isoformat()
        if isinstance(end_time, str):
            end_dt = datetime.fromisoformat(end_time.replace('Z', '+00:00'))
        else:
            end_dt = end_time
        open_dt = datetime.fromisoformat(trade['opened_at'].replace('Z', '+00:00'))
        # Show 24h before entry to 12h after close
        start_ms = int((open_dt - timedelta(hours=24)).timestamp() * 1000)
        end_ms = int((end_dt + timedelta(hours=12)).timestamp() * 1000)

        resp = requests.get(f'{BINANCE_FUTURES_API}/fapi/v1/klines', params={
            'symbol': symbol, 'interval': '1h',
            'startTime': start_ms, 'endTime': end_ms, 'limit': 500
        }, timeout=10)
        klines = resp.json()
        trade_dict['klines'] = [[k[0], float(k[1]), float(k[2]), float(k[3]), float(k[4])] for k in klines]
    except Exception as e:
        print(f"[Kline Error] {e}")
        trade_dict['klines'] = []

    return jsonify(trade_dict)

@app.route('/api/positions')
def api_positions():
    """Current open positions with live PnL"""
    conn = get_db()
    trades = conn.execute('''
        SELECT t.*, s.stop_loss as sl, s.take_profit as tp
        FROM trades t JOIN signals s ON t.signal_id = s.id
        WHERE t.status = 'open'
    ''').fetchall()
    conn.close()

    result = []
    for t in trades:
        td = dict(t)
        price = get_binance_price(t['symbol'])
        if price:
            entry = t['entry_price']
            direction = t['direction']
            leverage = t['leverage']
            if direction == 'LONG':
                unrealized_pct = ((price - entry) / entry) * leverage * 100
            else:
                unrealized_pct = ((entry - price) / entry) * leverage * 100
            td['current_price'] = price
            td['unrealized_pnl'] = round(t['position_size'] * (unrealized_pct / 100), 2)
            td['unrealized_pct'] = round(unrealized_pct, 2)
        result.append(td)
    return jsonify(result)

@app.route('/api/daily-pnl')
def api_daily_pnl():
    conn = get_db()
    rows = conn.execute('SELECT * FROM daily_pnl ORDER BY date').fetchall()
    conn.close()
    return jsonify([dict(r) for r in rows])

@app.route('/api/config', methods=['GET'])
def api_get_config():
    return jsonify(get_config())

@app.route('/api/config', methods=['PUT'])
def api_update_config():
    data = request.json
    conn = get_db()
    for k, v in data.items():
        conn.execute('INSERT OR REPLACE INTO account_config (key, value) VALUES (?, ?)', (k, str(v)))
    conn.commit()
    conn.close()
    return jsonify({'ok': True})

# ===== Telegram Signal Parser =====

def parse_signal_message(text):
    """Parse a TG group message and extract trading signal if present.
    Returns dict with symbol, direction, stop_loss, take_profit, leverage, entry_price or None.
    Tries multiple common signal formats."""
    if not text:
        return None
    text = text.strip()

    result = {
        'symbol': None, 'direction': None, 'stop_loss': None,
        'take_profit': None, 'leverage': None, 'entry_price': None
    }

    # Extract symbol: look for coin names like BTC, ETH, BTCUSDT etc
    sym_match = re.search(r'[#\$]?([A-Z]{2,10})(USDT|/USDT)?', text.upper())
    if sym_match:
        sym = sym_match.group(1)
        if sym in ('USDT', 'USD', 'THE', 'FOR', 'AND', 'NOT', 'ALL', 'BUT'):
            sym = None
        else:
            result['symbol'] = sym + 'USDT' if not sym.endswith('USDT') else sym

    # Extract direction (简体+繁体)
    text_upper = text.upper()
    if any(w in text for w in ['做多', '輕倉多', '轻仓多', '加多', '開多', '开多']) or \
       any(w in text_upper for w in ['LONG', 'BUY']) or \
       (re.search(r'(?<![做開开加輕轻])多(?!空)', text) and '做空' not in text):
        result['direction'] = 'LONG'
    elif any(w in text for w in ['做空', '輕倉空', '轻仓空', '加空', '開空', '开空']) or \
         any(w in text_upper for w in ['SHORT', 'SELL']) or \
         (re.search(r'(?<![做開开加輕轻])空(?!多)', text) and '做多' not in text):
        result['direction'] = 'SHORT'

    # Extract numbers - find all decimal numbers in text
    numbers = re.findall(r'[\d]+\.?[\d]*', text)
    numbers = [float(n) for n in numbers if float(n) > 0]

    # Try structured format: look for labeled values
    # Stop loss patterns (简体+繁体)
    sl_match = re.search(r'(?:止損|止损|SL|stop.?loss|防守|停損|停损)[:\s：]*(\d+\.?\d*)', text, re.IGNORECASE)
    if sl_match:
        result['stop_loss'] = float(sl_match.group(1))

    # Take profit patterns (简体+繁体)
    tp_match = re.search(r'(?:止盈|TP|take.?profit|目標|目标|到價|到价)[:\s：]*(\d+\.?\d*)', text, re.IGNORECASE)
    if tp_match:
        result['take_profit'] = float(tp_match.group(1))

    # Entry price patterns (简体+繁体)
    entry_match = re.search(r'(?:入場|入场|entry|開倉|开仓|價格|价格|price|進場|进场|市價|市价)[:\s：]*(\d+\.?\d*)', text, re.IGNORECASE)
    if entry_match:
        result['entry_price'] = float(entry_match.group(1))

    # Leverage patterns (支持 8-12x 格式，取较大值)
    lev_match = re.search(r'(\d+)\s*[-~]\s*(\d+)\s*[xX倍]', text)
    if lev_match:
        result['leverage'] = int(lev_match.group(2))
    else:
        lev_match = re.search(r'(\d+)\s*[xX倍]', text)
        if lev_match:
            result['leverage'] = int(lev_match.group(1))

    # Need at minimum: symbol + direction + (sl or tp)
    if result['symbol'] and result['direction'] and (result['stop_loss'] or result['take_profit']):
        return result

    return None

# ===== Telegram Listener =====

def start_telegram_listener():
    """Start Telethon client on the persistent event loop"""
    loop = get_tg_loop()
    asyncio.run_coroutine_threadsafe(_run_telegram_listener(), loop)

async def _run_telegram_listener():
    global tg_client, tg_status
    from telethon import TelegramClient, events
    try:
        tg_client = TelegramClient(TG_SESSION_PATH, TG_API_ID, TG_API_HASH)
        await tg_client.connect()

        if not await tg_client.is_user_authorized():
            tg_status['error'] = 'Not authenticated. Use /api/telegram/auth to login.'
            tg_status['connected'] = False
            return

        me = await tg_client.get_me()
        tg_status['connected'] = True
        tg_status['error'] = None
        print(f"[Telegram] Connected as {me.first_name} ({me.phone})")

        # Try to find the group
        target = None
        async for dialog in tg_client.iter_dialogs():
            if TG_GROUP.lower() in (dialog.name or '').lower():
                target = dialog.entity
                break

        if not target:
            # Try by username
            try:
                target = await tg_client.get_entity(TG_GROUP)
            except:
                tg_status['error'] = f'Cannot find group: {TG_GROUP}'
                print(f"[Telegram] Cannot find group: {TG_GROUP}")

        if target:
            tg_status['listening'] = True
            tg_status['group_name'] = getattr(target, 'title', TG_GROUP)
            print(f"[Telegram] Listening to: {tg_status['group_name']}")

            @tg_client.on(events.NewMessage(chats=target))
            async def handler(event):
                msg_text = event.message.text
                if not msg_text:
                    return
                tg_status['last_message'] = msg_text[:100]
                print(f"[TG Message] {msg_text[:80]}")

                signal = parse_signal_message(msg_text)
                if signal:
                    # Auto-create signal in DB
                    msg_time = event.message.date.strftime('%Y-%m-%d %H:%M:%S') if event.message.date else None
                    conn = get_db()
                    conn.execute('''
                        INSERT INTO signals (symbol, direction, entry_price, stop_loss, take_profit, leverage, raw_message, source, status, category, signal_time)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'telegram', 'pending', ?, ?)
                    ''', (
                        signal['symbol'], signal['direction'], signal.get('entry_price'),
                        signal['stop_loss'] or 0, signal['take_profit'] or 0,
                        signal.get('leverage') or 10, msg_text,
                        tg_status.get('group_name', '赌狗日记'),
                        msg_time
                    ))
                    conn.commit()
                    signal_id = conn.execute('SELECT last_insert_rowid()').fetchone()[0]
                    conn.close()
                    print(f"[TG Signal] Auto-created: {signal['symbol']} {signal['direction']}")

                    # Auto-activate: immediately open trade
                    if auto_activate_signal(signal_id):
                        print(f"[TG Signal] Auto-activated: {signal['symbol']} {signal['direction']}")
                    else:
                        print(f"[TG Signal] Auto-activate failed for {signal['symbol']}, stays pending")

        await tg_client.run_until_disconnected()

    except Exception as e:
        tg_status['error'] = str(e)
        tg_status['connected'] = False
        print(f"[Telegram Error] {e}")

# ===== Telegram Auth API =====

@app.route('/api/telegram/status')
def api_tg_status():
    return jsonify(tg_status)

@app.route('/api/telegram/auth/send-code', methods=['POST'])
def api_tg_send_code():
    """Step 1: Send phone number, receive code via TG app"""
    global tg_client, tg_auth_phone, tg_auth_hash
    data = request.json
    phone = data.get('phone', '').strip()
    if not phone:
        return jsonify({'error': 'Phone number required'}), 400

    tg_auth_phone = phone
    loop = get_tg_loop()

    async def _send():
        global tg_client, tg_auth_hash
        from telethon import TelegramClient
        tg_client = TelegramClient(TG_SESSION_PATH, TG_API_ID, TG_API_HASH)
        await tg_client.connect()
        result = await tg_client.send_code_request(phone)
        tg_auth_hash = result.phone_code_hash

    try:
        future = asyncio.run_coroutine_threadsafe(_send(), loop)
        future.result(timeout=30)
        return jsonify({'ok': True, 'message': 'Code sent to your Telegram app'})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/telegram/auth/verify', methods=['POST'])
def api_tg_verify_code():
    """Step 2: Verify code and complete auth"""
    global tg_client, tg_auth_phone, tg_auth_hash, tg_status
    data = request.json
    code = data.get('code', '').strip()
    password = data.get('password', '').strip()
    if not code:
        return jsonify({'error': 'Code required'}), 400

    loop = get_tg_loop()

    async def _verify():
        global tg_status
        from telethon import errors
        try:
            await tg_client.sign_in(tg_auth_phone, code, phone_code_hash=tg_auth_hash)
        except errors.SessionPasswordNeededError:
            if password:
                await tg_client.sign_in(password=password)
            else:
                return {'needs_2fa': True}
        me = await tg_client.get_me()
        await tg_client.disconnect()
        tg_status['connected'] = False
        return {'ok': True, 'user': me.first_name}

    try:
        future = asyncio.run_coroutine_threadsafe(_verify(), loop)
        result = future.result(timeout=30)
        if result.get('needs_2fa'):
            return jsonify({'needs_2fa': True, 'message': 'Two-factor password required'})
        # Start listener
        start_telegram_listener()
        return jsonify(result)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/telegram/fetch-history', methods=['POST'])
def api_tg_fetch_history():
    """Fetch recent messages from group (for initial analysis)"""
    limit = request.json.get('limit', 20) if request.json else 20
    loop = get_tg_loop()

    async def _fetch():
        from telethon import TelegramClient
        client = TelegramClient(TG_SESSION_PATH, TG_API_ID, TG_API_HASH)
        await client.connect()
        if not await client.is_user_authorized():
            await client.disconnect()
            return {'error': 'Not authenticated'}

        target = None
        async for dialog in client.iter_dialogs():
            if TG_GROUP.lower() in (dialog.name or '').lower():
                target = dialog.entity
                break
        if not target:
            try:
                target = await client.get_entity(TG_GROUP)
            except:
                await client.disconnect()
                return {'error': f'Cannot find group: {TG_GROUP}'}

        messages = []
        async for msg in client.iter_messages(target, limit=limit):
            if msg.text:
                parsed = parse_signal_message(msg.text)
                messages.append({
                    'id': msg.id,
                    'date': msg.date.isoformat(),
                    'text': msg.text[:500],
                    'parsed': parsed
                })
        await client.disconnect()
        return {'messages': messages}

    try:
        future = asyncio.run_coroutine_threadsafe(_fetch(), loop)
        result = future.result(timeout=60)
        return jsonify(result)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

# ===== Main Page =====

@app.route('/')
def index():
    return render_template_string(PAGE_HTML)

PAGE_HTML = r'''<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Telegram 信号跟踪器</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #0c1222 0%, #162036 40%, #0f1a2e 70%, #0a1628 100%);
    color: #e2e8f0; min-height: 100vh;
    background-attachment: fixed;
}
body::before {
    content: ''; position: fixed; top: -50%; left: -50%; width: 200%; height: 200%;
    background: radial-gradient(ellipse at 20% 50%, rgba(56,189,248,0.03) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(139,92,246,0.03) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 80%, rgba(16,185,129,0.02) 0%, transparent 50%);
    pointer-events: none; z-index: 0;
}
.container { max-width: 1440px; margin: 0 auto; padding: 20px 24px; position: relative; z-index: 1; }

/* Header */
.header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 20px 28px; margin-bottom: 24px;
    background: rgba(15,23,42,0.6); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.06); border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.2);
}
.header h1 {
    font-size: 24px; font-weight: 700; color: #f8fafc;
    background: linear-gradient(135deg, #38bdf8, #818cf8);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}
.header-links { display: flex; gap: 8px; }
.header-links a {
    color: #94a3b8; text-decoration: none; padding: 7px 16px; border-radius: 8px;
    background: rgba(255,255,255,0.04); font-size: 13px; font-weight: 500;
    border: 1px solid rgba(255,255,255,0.06); transition: all .25s ease;
}
.header-links a:hover { background: rgba(56,189,248,0.1); color: #e2e8f0; border-color: rgba(56,189,248,0.2); }

/* Stats Cards */
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card {
    background: rgba(15,23,42,0.5); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255,255,255,0.06); border-radius: 14px;
    padding: 20px 22px; position: relative; overflow: hidden;
    transition: transform .2s ease, box-shadow .2s ease;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(0,0,0,0.2); }
.stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    border-radius: 14px 14px 0 0;
}
.stat-card:nth-child(1)::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
.stat-card:nth-child(2)::before { background: linear-gradient(90deg, #10b981, #34d399); }
.stat-card:nth-child(3)::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.stat-card:nth-child(4)::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }
.stat-card .label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; font-weight: 600; }
.stat-card .value { font-size: 26px; font-weight: 800; letter-spacing: -0.5px; }
.stat-card .sub { font-size: 12px; color: #64748b; margin-top: 6px; line-height: 1.4; }
.green { color: #10b981; }
.red { color: #ef4444; }
.blue { color: #38bdf8; }
.yellow { color: #fbbf24; }

/* Section */
.section {
    background: rgba(15,23,42,0.5); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255,255,255,0.06); border-radius: 16px;
    padding: 24px; margin-bottom: 20px;
    box-shadow: 0 2px 16px rgba(0,0,0,0.1);
}
.section-title {
    font-size: 15px; font-weight: 700; margin-bottom: 18px; color: #e2e8f0;
    display: flex; align-items: center; gap: 8px;
    padding-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.05);
}

/* Signal Form */
.form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 11px; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
.form-group input, .form-group select, .form-group textarea {
    background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.08);
    color: #e2e8f0; padding: 10px 14px; border-radius: 10px; font-size: 14px; outline: none;
    transition: all .2s ease;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    border-color: rgba(56,189,248,0.5); box-shadow: 0 0 0 3px rgba(56,189,248,0.1);
}
.form-group input::placeholder, .form-group textarea::placeholder { color: #475569; }
.form-group.full { grid-column: 1 / -1; }
.form-group textarea { resize: vertical; min-height: 60px; }
.btn {
    padding: 10px 22px; border: none; border-radius: 10px; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: all .2s ease; letter-spacing: .3px;
}
.btn-primary { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; box-shadow: 0 2px 8px rgba(59,130,246,0.25); }
.btn-primary:hover { box-shadow: 0 4px 16px rgba(59,130,246,0.35); transform: translateY(-1px); }
.btn-success { background: linear-gradient(135deg, #10b981, #059669); color: white; box-shadow: 0 2px 8px rgba(16,185,129,0.25); }
.btn-success:hover { box-shadow: 0 4px 16px rgba(16,185,129,0.35); transform: translateY(-1px); }
.btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; box-shadow: 0 2px 8px rgba(239,68,68,0.25); }
.btn-danger:hover { box-shadow: 0 4px 16px rgba(239,68,68,0.35); transform: translateY(-1px); }
.btn-warning { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; box-shadow: 0 2px 8px rgba(245,158,11,0.25); }
.btn-warning:hover { box-shadow: 0 4px 16px rgba(245,158,11,0.35); transform: translateY(-1px); }
.btn-sm { padding: 5px 12px; font-size: 12px; border-radius: 7px; }
.form-actions { grid-column: 1 / -1; display: flex; gap: 10px; margin-top: 10px; }

/* Tables */
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th {
    text-align: left; padding: 12px 14px;
    background: rgba(0,0,0,0.2); color: #64748b;
    font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: .8px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
td { padding: 11px 14px; border-bottom: 1px solid rgba(255,255,255,0.04); }
tr { transition: background .15s ease; }
tr:hover { background: rgba(56,189,248,0.04); }
.badge {
    display: inline-block; padding: 3px 10px; border-radius: 6px;
    font-size: 11px; font-weight: 700; letter-spacing: .3px;
}
.badge-long { background: rgba(16,185,129,0.12); color: #34d399; border: 1px solid rgba(16,185,129,0.15); }
.badge-short { background: rgba(239,68,68,0.12); color: #f87171; border: 1px solid rgba(239,68,68,0.15); }
.badge-pending { background: rgba(245,158,11,0.12); color: #fbbf24; border: 1px solid rgba(245,158,11,0.15); }
.badge-active { background: rgba(59,130,246,0.12); color: #60a5fa; border: 1px solid rgba(59,130,246,0.15); }
.badge-closed { background: rgba(100,116,139,0.12); color: #94a3b8; border: 1px solid rgba(100,116,139,0.15); }
.badge-cancelled { background: rgba(30,41,59,0.5); color: #475569; border: 1px solid rgba(71,85,105,0.2); }
.badge-tp { background: rgba(16,185,129,0.12); color: #34d399; border: 1px solid rgba(16,185,129,0.15); }
.badge-sl { background: rgba(239,68,68,0.12); color: #f87171; border: 1px solid rgba(239,68,68,0.15); }
.badge-manual { background: rgba(59,130,246,0.12); color: #60a5fa; border: 1px solid rgba(59,130,246,0.15); }

/* Chart */
.chart-container { height: 320px; margin-top: 12px; }

/* Modal */
.modal-overlay {
    display:none; position:fixed; top:0; left:0; right:0; bottom:0;
    background:rgba(0,0,0,0.6); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
    z-index:1000; justify-content:center; align-items:center;
}
.modal-overlay.active { display:flex; }
.modal {
    background: rgba(15,23,42,0.95); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
    border:1px solid rgba(255,255,255,0.08); border-radius:20px; padding:28px;
    max-width:900px; width:95%; max-height:90vh; overflow-y:auto;
    box-shadow: 0 24px 64px rgba(0,0,0,0.4);
}
.modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:14px; border-bottom: 1px solid rgba(255,255,255,0.06); }
.modal-header h3 { font-size: 18px; font-weight: 700; }
.modal-close { background:none; border:none; color:#94a3b8; font-size:28px; cursor:pointer; transition: color .2s; }
.modal-close:hover { color: #f8fafc; }
.trade-info-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:16px; }
.trade-info-item { background:rgba(0,0,0,0.25); border-radius:12px; padding:14px; border: 1px solid rgba(255,255,255,0.04); }
.trade-info-item .label { font-size:11px; color:#64748b; margin-bottom:4px; text-transform: uppercase; letter-spacing: .5px; }
.trade-info-item .val { font-size:18px; font-weight:700; }

/* Responsive */
@media (max-width: 900px) {
    .header { flex-direction: column; gap: 14px; text-align: center; }
    .header-links { justify-content: center; flex-wrap: wrap; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .form-grid { grid-template-columns: repeat(2, 1fr); }
    .trade-info-grid { grid-template-columns: repeat(2, 1fr); }
    .config-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
    .form-grid { grid-template-columns: 1fr; }
}

/* Tabs */
.tab-bar { display: flex; gap: 4px; margin-bottom: 16px; }
.tab {
    padding: 7px 18px; border-radius: 8px; font-size: 13px; font-weight: 500;
    cursor: pointer; background: transparent; color: #64748b; border: none;
    transition: all .2s ease;
}
.tab.active { background: rgba(56,189,248,0.12); color: #38bdf8; font-weight: 600; }
.tab:hover { color: #e2e8f0; background: rgba(255,255,255,0.04); }

/* Config panel */
.config-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }

.loading { text-align:center; padding:50px; color:#475569; font-size: 14px; }

/* Scrollbar */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Telegram 信号跟踪器</h1>
        <div class="header-links">
            <a href="http://139.162.41.38:5111/">交易仪表盘</a>
            <a href="http://139.162.41.38:5111/backtest">回测模拟器</a>
            <a href="http://139.162.41.38:5111/report">策略报告</a>
            <a href="http://139.162.41.38:5111/validation">过拟合验证</a>
        </div>
    </div>

    <!-- Telegram Status -->
    <div class="section" id="tgSection" style="padding:16px 24px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <span id="tgDot" style="width:10px;height:10px;border-radius:50%;background:#ef4444;display:inline-block;box-shadow:0 0 8px rgba(239,68,68,0.4);"></span>
                <span id="tgStatusText" style="font-size:14px;font-weight:600;">Telegram: 未连接</span>
                <span id="tgGroup" style="font-size:12px;color:#64748b;"></span>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <span id="tgLastMsg" style="font-size:11px;color:#475569;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                <button class="btn btn-primary btn-sm" id="tgAuthBtn" onclick="showTgAuth()">连接 Telegram</button>
                <button class="btn btn-sm" style="background:rgba(255,255,255,0.04);color:#94a3b8;border:1px solid rgba(255,255,255,0.08);" onclick="fetchHistory()">拉取历史</button>
            </div>
        </div>
        <div id="tgAuthPanel" style="display:none;margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,0.06);">
            <div style="display:flex;gap:10px;align-items:end;">
                <div class="form-group" style="flex:1;">
                    <label>手机号 (含国际区号)</label>
                    <input type="text" id="tgPhone" placeholder="+8613800138000" />
                </div>
                <button class="btn btn-primary btn-sm" onclick="tgSendCode()" id="tgSendBtn">发送验证码</button>
            </div>
            <div id="tgCodePanel" style="display:none;margin-top:10px;">
                <div style="display:flex;gap:10px;align-items:end;">
                    <div class="form-group" style="flex:1;">
                        <label>验证码 (TG收到的)</label>
                        <input type="text" id="tgCode" placeholder="12345" />
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>两步验证密码 (如有)</label>
                        <input type="password" id="tg2FA" placeholder="可选" />
                    </div>
                    <button class="btn btn-success btn-sm" onclick="tgVerify()">验证</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" id="statsGrid">
        <div class="stat-card"><div class="label">总资金</div><div class="value blue" id="statCapital">-</div><div class="sub" id="statCapitalSub">-</div></div>
        <div class="stat-card"><div class="label">总盈亏</div><div class="value" id="statPnl">-</div><div class="sub" id="statPnlSub">-</div></div>
        <div class="stat-card"><div class="label">胜率</div><div class="value" id="statWinRate">-</div><div class="sub" id="statWinRateSub">-</div></div>
        <div class="stat-card"><div class="label">持仓</div><div class="value yellow" id="statPositions">-</div><div class="sub" id="statPositionsSub">-</div></div>
    </div>

    <!-- Signal Input -->
    <div class="section">
        <div class="section-title">录入信号</div>
        <div class="form-grid" id="signalForm">
            <div class="form-group">
                <label>分类</label>
                <input type="text" id="fCategory" value="赌狗日记" list="categoryList" />
                <datalist id="categoryList"></datalist>
            </div>
            <div class="form-group">
                <label>币种</label>
                <input type="text" id="fSymbol" placeholder="如 BTCUSDT" />
            </div>
            <div class="form-group">
                <label>方向</label>
                <select id="fDirection">
                    <option value="LONG">做多 LONG</option>
                    <option value="SHORT">做空 SHORT</option>
                </select>
            </div>
            <div class="form-group">
                <label>入场价 (可选)</label>
                <input type="number" id="fEntry" step="any" placeholder="不填则按市价" />
            </div>
            <div class="form-group">
                <label>杠杆倍数</label>
                <input type="number" id="fLeverage" value="10" min="1" max="125" />
            </div>
            <div class="form-group">
                <label>止损价</label>
                <input type="number" id="fSL" step="any" placeholder="必填" />
            </div>
            <div class="form-group">
                <label>止盈价</label>
                <input type="number" id="fTP" step="any" placeholder="必填" />
            </div>
            <div class="form-group">
                <label>信号时间</label>
                <input type="datetime-local" id="fSignalTime" />
            </div>
            <div class="form-group full">
                <label>原始消息 (TG群消息)</label>
                <textarea id="fRaw" placeholder="粘贴 Telegram 群的原始消息..."></textarea>
            </div>
            <div class="form-group full">
                <label>备注</label>
                <input type="text" id="fNotes" placeholder="可选备注" />
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" onclick="addSignal()">添加信号</button>
                <button class="btn btn-success" onclick="addAndActivate()">添加并开仓</button>
            </div>
        </div>
    </div>

    <!-- Open Positions -->
    <div class="section">
        <div class="section-title">当前持仓 <span id="posCount" style="color:#475569;font-size:12px;font-weight:400;"></span></div>
        <div id="positionsTable"><div class="loading">Loading...</div></div>
    </div>

    <!-- Pending Signals -->
    <div class="section">
        <div class="section-title">信号列表</div>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">
            <div class="tab-bar" style="margin-bottom:0;">
                <button class="tab active" onclick="filterSignals('pending',this)">待处理</button>
                <button class="tab" onclick="filterSignals('active',this)">进行中</button>
                <button class="tab" onclick="filterSignals('closed',this)">已完成</button>
                <button class="tab" onclick="filterSignals('',this)">全部</button>
            </div>
            <div style="display:flex;align-items:center;gap:6px;margin-left:auto;">
                <span style="font-size:12px;color:#64748b;">分类:</span>
                <select id="categoryFilter" onchange="filterByCategory(this.value)" style="background:rgba(0,0,0,0.25);color:#e2e8f0;border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:5px 10px;font-size:12px;outline:none;">
                    <option value="">全部分类</option>
                </select>
            </div>
        </div>
        <div id="signalsTable"><div class="loading">Loading...</div></div>
    </div>

    <!-- PnL Curve -->
    <div class="section">
        <div class="section-title">资金曲线</div>
        <div class="chart-container"><canvas id="pnlChart"></canvas></div>
    </div>

    <!-- Trade History -->
    <div class="section">
        <div class="section-title">交易历史</div>
        <div id="tradesTable"><div class="loading">Loading...</div></div>
    </div>

    <!-- Config -->
    <div class="section">
        <div class="section-title">设置</div>
        <div class="config-grid">
            <div class="form-group">
                <label>初始资金 (USDT)</label>
                <input type="number" id="cfgCapital" value="2000" />
            </div>
            <div class="form-group">
                <label>手续费率 (%)</label>
                <input type="number" id="cfgFee" value="0.05" step="0.01" />
            </div>
            <div class="form-group">
                <label>资金费率 (%/8h)</label>
                <input type="number" id="cfgFunding" value="0.01" step="0.001" />
            </div>
            <div class="form-group">
                <label>每笔仓位占比 (%)</label>
                <input type="number" id="cfgPosPct" value="10" />
            </div>
        </div>
        <div style="margin-top:12px;">
            <button class="btn btn-primary" onclick="saveConfig()">保存设置</button>
        </div>
    </div>
</div>

<!-- Trade Detail Modal -->
<div class="modal-overlay" id="tradeModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">交易详情</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div id="modalContent"></div>
    </div>
</div>

<script>
let pnlChart = null;
let currentFilter = 'pending';
let currentCategory = '';

function fmtPrice(p) {
    if (!p && p !== 0) return '-';
    p = parseFloat(p);
    if (p >= 1000) return p.toFixed(2);
    if (p >= 1) return p.toFixed(4);
    return p.toFixed(6);
}

function fmtPnl(v) {
    if (!v && v !== 0) return '-';
    v = parseFloat(v);
    const cls = v >= 0 ? 'green' : 'red';
    const sign = v >= 0 ? '+' : '';
    return '<span class="' + cls + '">' + sign + v.toFixed(2) + '</span>';
}

function fmtTime(ts) {
    if (!ts) return '-';
    // DB存的是UTC时间，补上Z让浏览器自动转本地时区
    if (!ts.includes('Z') && !ts.includes('+')) {
        ts = ts.replace(' ', 'T') + 'Z';
    }
    const d = new Date(ts);
    return d.toLocaleDateString('zh-CN', {month:'2-digit',day:'2-digit'}) + ' ' +
           d.toLocaleTimeString('zh-CN', {hour:'2-digit',minute:'2-digit'});
}

// === Data Loading ===

async function loadStats() {
    const r = await fetch('/api/stats');
    const s = await r.json();

    document.getElementById('statCapital').textContent = s.total_capital.toFixed(2) + 'U';
    document.getElementById('statCapitalSub').textContent = '可用: ' + s.available.toFixed(2) + 'U | 已用: ' + s.used.toFixed(2) + 'U';

    const pnlEl = document.getElementById('statPnl');
    pnlEl.textContent = (s.total_pnl >= 0 ? '+' : '') + s.total_pnl.toFixed(2) + 'U';
    pnlEl.className = 'value ' + (s.total_pnl >= 0 ? 'green' : 'red');
    document.getElementById('statPnlSub').innerHTML = '收益率: ' + (s.pnl_pct >= 0 ? '+' : '') + s.pnl_pct + '% | 今日: ' + fmtPnl(s.today_pnl);

    const wrEl = document.getElementById('statWinRate');
    wrEl.textContent = s.win_rate + '%';
    wrEl.className = 'value ' + (s.win_rate >= 50 ? 'green' : s.total_trades > 0 ? 'red' : 'blue');
    document.getElementById('statWinRateSub').textContent = s.win_count + '胜 / ' + s.total_trades + '笔 | 费用: ' + s.total_fees.toFixed(2) + 'U';

    document.getElementById('statPositions').textContent = s.open_positions;
    document.getElementById('statPositionsSub').textContent = '待处理信号: ' + s.pending_signals;
}

async function loadPositions() {
    const r = await fetch('/api/positions');
    const positions = await r.json();
    const el = document.getElementById('positionsTable');
    document.getElementById('posCount').textContent = positions.length > 0 ? '(' + positions.length + ')' : '';

    if (positions.length === 0) {
        el.innerHTML = '<div style="text-align:center;padding:20px;color:#64748b;">暂无持仓</div>';
        return;
    }

    let html = '<table><thead><tr><th>币种</th><th>方向</th><th>入场价</th><th>当前价</th><th>止损</th><th>止盈</th><th>仓位</th><th>杠杆</th><th>盈亏</th><th>操作</th></tr></thead><tbody>';
    for (const p of positions) {
        const dirBadge = p.direction === 'LONG' ? '<span class="badge badge-long">LONG</span>' : '<span class="badge badge-short">SHORT</span>';
        const pnlColor = (p.unrealized_pnl || 0) >= 0 ? 'green' : 'red';
        html += '<tr>' +
            '<td><strong>' + p.symbol + '</strong></td>' +
            '<td>' + dirBadge + '</td>' +
            '<td>' + fmtPrice(p.entry_price) + '</td>' +
            '<td>' + fmtPrice(p.current_price) + '</td>' +
            '<td class="red">' + fmtPrice(p.sl) + '</td>' +
            '<td class="green">' + fmtPrice(p.tp) + '</td>' +
            '<td>' + (p.position_size || 0).toFixed(2) + 'U</td>' +
            '<td>' + p.leverage + 'x</td>' +
            '<td class="' + pnlColor + '">' + (p.unrealized_pnl >= 0 ? '+' : '') + (p.unrealized_pnl || 0).toFixed(2) + 'U (' + (p.unrealized_pct >= 0 ? '+' : '') + (p.unrealized_pct || 0).toFixed(1) + '%)</td>' +
            '<td><button class="btn btn-danger btn-sm" onclick="closeSignal(' + p.signal_id + ')">平仓</button></td>' +
            '</tr>';
    }
    html += '</tbody></table>';
    el.innerHTML = html;
}

async function loadSignals() {
    let url = '/api/signals?status=' + currentFilter;
    if (currentCategory) url += '&category=' + encodeURIComponent(currentCategory);
    const r = await fetch(url);
    const signals = await r.json();
    const el = document.getElementById('signalsTable');

    if (signals.length === 0) {
        el.innerHTML = '<div style="text-align:center;padding:20px;color:#64748b;">暂无信号</div>';
        return;
    }

    let html = '<table><thead><tr><th>信号时间</th><th>分类</th><th>币种</th><th>方向</th><th>入场价</th><th>止损</th><th>止盈</th><th>杠杆</th><th>状态</th><th>操作</th></tr></thead><tbody>';
    for (const s of signals) {
        const dirBadge = s.direction === 'LONG' ? '<span class="badge badge-long">LONG</span>' : '<span class="badge badge-short">SHORT</span>';
        const statusBadge = '<span class="badge badge-' + s.status + '">' + s.status.toUpperCase() + '</span>';
        let actions = '';
        if (s.status === 'pending') {
            actions = '<button class="btn btn-success btn-sm" onclick="activateSignal(' + s.id + ')">开仓</button> ' +
                      '<button class="btn btn-warning btn-sm" onclick="cancelSignal(' + s.id + ')">取消</button>';
        } else if (s.status === 'active') {
            actions = '<button class="btn btn-danger btn-sm" onclick="closeSignal(' + s.id + ')">平仓</button>';
        }
        html += '<tr>' +
            '<td>' + fmtTime(s.signal_time || s.created_at) + '</td>' +
            '<td><span class="badge" style="background:#1e293b;color:#94a3b8;font-size:11px;">' + (s.category || '-') + '</span></td>' +
            '<td><strong>' + s.symbol + '</strong></td>' +
            '<td>' + dirBadge + '</td>' +
            '<td>' + fmtPrice(s.entry_price) + '</td>' +
            '<td class="red">' + fmtPrice(s.stop_loss) + '</td>' +
            '<td class="green">' + fmtPrice(s.take_profit) + '</td>' +
            '<td>' + s.leverage + 'x</td>' +
            '<td>' + statusBadge + '</td>' +
            '<td>' + actions + '</td>' +
            '</tr>';
        if (s.raw_message) {
            html += '<tr><td colspan="10" style="padding:4px 12px 10px;color:#64748b;font-size:12px;border-bottom:1px solid #1e293b;">' + escapeHtml(s.raw_message).substring(0,200) + '</td></tr>';
        }
    }
    html += '</tbody></table>';
    el.innerHTML = html;
}

async function loadTrades() {
    const r = await fetch('/api/trades');
    const trades = await r.json();
    const el = document.getElementById('tradesTable');

    if (trades.length === 0) {
        el.innerHTML = '<div style="text-align:center;padding:20px;color:#64748b;">暂无交易记录</div>';
        return;
    }

    let html = '<table><thead><tr><th>#</th><th>时间</th><th>币种</th><th>方向</th><th>入场</th><th>出场</th><th>仓位</th><th>杠杆</th><th>盈亏</th><th>费用</th><th>原因</th></tr></thead><tbody>';
    for (let i = 0; i < trades.length; i++) {
        const t = trades[i];
        const dirBadge = t.direction === 'LONG' ? '<span class="badge badge-long">L</span>' : '<span class="badge badge-short">S</span>';
        const reasonBadge = t.close_reason ? '<span class="badge badge-' + t.close_reason + '">' + t.close_reason.toUpperCase() + '</span>' : (t.status === 'open' ? '<span class="badge badge-active">OPEN</span>' : '-');
        html += '<tr style="cursor:pointer;" onclick="showTradeDetail(' + t.id + ')">' +
            '<td>' + (i + 1) + '</td>' +
            '<td>' + fmtTime(t.opened_at) + '</td>' +
            '<td><strong>' + t.symbol + '</strong></td>' +
            '<td>' + dirBadge + '</td>' +
            '<td>' + fmtPrice(t.entry_price) + '</td>' +
            '<td>' + fmtPrice(t.exit_price) + '</td>' +
            '<td>' + (t.position_size || 0).toFixed(2) + 'U</td>' +
            '<td>' + t.leverage + 'x</td>' +
            '<td>' + fmtPnl(t.pnl) + '</td>' +
            '<td>' + (t.fees || 0).toFixed(2) + '</td>' +
            '<td>' + reasonBadge + '</td>' +
            '</tr>';
    }
    html += '</tbody></table>';
    el.innerHTML = html;
}

async function loadPnlChart() {
    const r = await fetch('/api/daily-pnl');
    const data = await r.json();

    if (data.length === 0) return;

    const ctx = document.getElementById('pnlChart').getContext('2d');
    if (pnlChart) pnlChart.destroy();

    const labels = data.map(d => d.date);
    const cumPnl = data.map(d => d.cumulative_pnl || 0);
    const dailyPnl = data.map(d => d.pnl || 0);

    pnlChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: '累计盈亏',
                data: cumPnl,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59,130,246,0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 3,
            }, {
                label: '每日盈亏',
                data: dailyPnl,
                type: 'bar',
                backgroundColor: dailyPnl.map(v => v >= 0 ? 'rgba(16,185,129,0.6)' : 'rgba(239,68,68,0.6)'),
                borderRadius: 3,
                barPercentage: 0.6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#94a3b8' } } },
            scales: {
                x: { ticks: { color: '#64748b', maxRotation: 45 }, grid: { color: '#1e293b' } },
                y: { ticks: { color: '#64748b' }, grid: { color: '#1e293b' } }
            }
        }
    });
}

async function loadConfig() {
    const r = await fetch('/api/config');
    const cfg = await r.json();
    document.getElementById('cfgCapital').value = cfg.initial_capital || 2000;
    document.getElementById('cfgFee').value = cfg.fee_rate || 0.05;
    document.getElementById('cfgFunding').value = cfg.funding_rate || 0.01;
    document.getElementById('cfgPosPct').value = cfg.position_pct || 10;
}

// === Actions ===

async function addSignal() {
    const data = getFormData();
    if (!data) return;
    const r = await fetch('/api/signal', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data) });
    const res = await r.json();
    if (res.ok) {
        clearForm();
        refreshAll();
    } else {
        alert(res.error || '添加信号失败');
    }
}

async function addAndActivate() {
    const data = getFormData();
    if (!data) return;
    // Add signal
    const r = await fetch('/api/signal', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data) });
    const res = await r.json();
    if (!res.ok) { alert(res.error || '添加失败'); return; }
    // Activate immediately
    const r2 = await fetch('/api/signal/' + res.id + '/activate', { method: 'PUT' });
    const res2 = await r2.json();
    if (res2.ok) {
        clearForm();
        refreshAll();
    } else {
        alert(res2.error || '信号已添加但开仓失败');
        refreshAll();
    }
}

function getFormData() {
    const symbol = document.getElementById('fSymbol').value.trim();
    const sl = document.getElementById('fSL').value;
    const tp = document.getElementById('fTP').value;
    if (!symbol || !sl || !tp) {
        alert('币种、止损价、止盈价为必填项');
        return null;
    }
    const sigTime = document.getElementById('fSignalTime').value;
    return {
        symbol: symbol,
        direction: document.getElementById('fDirection').value,
        entry_price: document.getElementById('fEntry').value ? parseFloat(document.getElementById('fEntry').value) : null,
        leverage: parseInt(document.getElementById('fLeverage').value) || 10,
        category: document.getElementById('fCategory').value.trim() || '赌狗日记',
        signal_time: sigTime || null,
        stop_loss: parseFloat(sl),
        take_profit: parseFloat(tp),
        raw_message: document.getElementById('fRaw').value,
        notes: document.getElementById('fNotes').value,
    };
}

function clearForm() {
    document.getElementById('fSymbol').value = '';
    document.getElementById('fEntry').value = '';
    document.getElementById('fSL').value = '';
    document.getElementById('fTP').value = '';
    setDefaultSignalTime();
    document.getElementById('fRaw').value = '';
    document.getElementById('fNotes').value = '';
}

async function activateSignal(id) {
    if (!confirm('确认开仓？')) return;
    const r = await fetch('/api/signal/' + id + '/activate', { method: 'PUT' });
    const res = await r.json();
    if (!res.ok) alert(res.error || '操作失败');
    refreshAll();
}

async function closeSignal(id) {
    if (!confirm('确认以市价平仓？')) return;
    const r = await fetch('/api/signal/' + id + '/close', { method: 'PUT' });
    const res = await r.json();
    if (res.ok) {
        alert('已平仓! 盈亏: ' + (res.pnl >= 0 ? '+' : '') + res.pnl.toFixed(2) + 'U (' + res.pnl_pct + '%)');
    } else {
        alert(res.error || '平仓失败');
    }
    refreshAll();
}

async function cancelSignal(id) {
    if (!confirm('确认取消该信号？')) return;
    const r = await fetch('/api/signal/' + id + '/cancel', { method: 'PUT' });
    await r.json();
    refreshAll();
}

function filterSignals(status, btn) {
    currentFilter = status;
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    loadSignals();
}

function filterByCategory(cat) {
    currentCategory = cat;
    loadSignals();
}

async function loadCategories() {
    try {
        const r = await fetch('/api/categories');
        const cats = await r.json();
        const sel = document.getElementById('categoryFilter');
        const dl = document.getElementById('categoryList');
        // Update filter dropdown
        sel.innerHTML = '<option value="">全部分类</option>';
        // Update datalist for form input
        dl.innerHTML = '';
        for (const c of cats) {
            sel.innerHTML += '<option value="' + c + '">' + c + '</option>';
            dl.innerHTML += '<option value="' + c + '">';
        }
    } catch(e) {}
}

async function saveConfig() {
    const data = {
        initial_capital: document.getElementById('cfgCapital').value,
        fee_rate: document.getElementById('cfgFee').value,
        funding_rate: document.getElementById('cfgFunding').value,
        position_pct: document.getElementById('cfgPosPct').value,
    };
    await fetch('/api/config', { method: 'PUT', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data) });
    refreshAll();
}

async function showTradeDetail(tid) {
    const r = await fetch('/api/trade/' + tid);
    const t = await r.json();
    if (t.error) return;

    document.getElementById('modalTitle').textContent = t.symbol + ' ' + t.direction + ' #' + t.id;

    let html = '<div class="trade-info-grid">' +
        '<div class="trade-info-item"><div class="label">入场价</div><div class="val">' + fmtPrice(t.entry_price) + '</div></div>' +
        '<div class="trade-info-item"><div class="label">出场价</div><div class="val">' + fmtPrice(t.exit_price) + '</div></div>' +
        '<div class="trade-info-item"><div class="label">止损价</div><div class="val red">' + fmtPrice(t.sl) + '</div></div>' +
        '<div class="trade-info-item"><div class="label">止盈价</div><div class="val green">' + fmtPrice(t.tp) + '</div></div>' +
        '<div class="trade-info-item"><div class="label">杠杆</div><div class="val">' + t.leverage + 'x</div></div>' +
        '<div class="trade-info-item"><div class="label">仓位</div><div class="val">' + (t.position_size || 0).toFixed(2) + 'U</div></div>' +
        '<div class="trade-info-item"><div class="label">盈亏</div><div class="val ' + ((t.pnl || 0) >= 0 ? 'green' : 'red') + '">' + fmtPnl(t.pnl) + '</div></div>' +
        '<div class="trade-info-item"><div class="label">费用</div><div class="val">' + (t.fees || 0).toFixed(4) + 'U</div></div>' +
        '</div>';

    if (t.raw_message) {
        html += '<div style="background:rgba(0,0,0,0.25);border-radius:12px;padding:14px;margin-bottom:16px;border:1px solid rgba(255,255,255,0.04);"><div style="font-size:11px;color:#64748b;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;">原始消息</div><div style="font-size:13px;white-space:pre-wrap;line-height:1.5;">' + escapeHtml(t.raw_message) + '</div></div>';
    }

    if (t.klines && t.klines.length > 0) {
        html += '<div style="height:350px;"><canvas id="tradeChart"></canvas></div>';
    }

    document.getElementById('modalContent').innerHTML = html;
    document.getElementById('tradeModal').classList.add('active');

    // Draw chart
    if (t.klines && t.klines.length > 0) {
        setTimeout(() => drawTradeChart(t), 100);
    }
}

function drawTradeChart(trade) {
    const ctx = document.getElementById('tradeChart');
    if (!ctx) return;

    const labels = trade.klines.map(k => {
        const d = new Date(k[0]);
        return d.toLocaleDateString('zh-CN', {month:'2-digit',day:'2-digit'}) + ' ' + d.toLocaleTimeString('zh-CN', {hour:'2-digit',minute:'2-digit'});
    });
    const closes = trade.klines.map(k => k[4]);
    const highs = trade.klines.map(k => k[2]);
    const lows = trade.klines.map(k => k[3]);

    const annotations = {};
    // Entry line
    annotations.entryLine = {
        type: 'line', yMin: trade.entry_price, yMax: trade.entry_price,
        borderColor: '#3b82f6', borderWidth: 2, borderDash: [6,3],
        label: { content: '入场 ' + fmtPrice(trade.entry_price), display: true, position: 'start',
                 backgroundColor: 'rgba(59,130,246,0.8)', font: {size: 10} }
    };
    // Exit line
    if (trade.exit_price) {
        annotations.exitLine = {
            type: 'line', yMin: trade.exit_price, yMax: trade.exit_price,
            borderColor: '#f59e0b', borderWidth: 2, borderDash: [6,3],
            label: { content: '出场 ' + fmtPrice(trade.exit_price), display: true, position: 'end',
                     backgroundColor: 'rgba(245,158,11,0.8)', font: {size: 10} }
        };
    }
    // SL line
    if (trade.sl) {
        annotations.slLine = {
            type: 'line', yMin: trade.sl, yMax: trade.sl,
            borderColor: 'rgba(239,68,68,0.5)', borderWidth: 1.5, borderDash: [6,3],
            label: { content: '止损 ' + fmtPrice(trade.sl), display: true, position: 'start',
                     backgroundColor: 'rgba(239,68,68,0.8)', font: {size: 9} }
        };
    }
    // TP line
    if (trade.tp) {
        annotations.tpLine = {
            type: 'line', yMin: trade.tp, yMax: trade.tp,
            borderColor: 'rgba(16,185,129,0.5)', borderWidth: 1.5, borderDash: [6,3],
            label: { content: '止盈 ' + fmtPrice(trade.tp), display: true, position: 'start',
                     backgroundColor: 'rgba(16,185,129,0.8)', font: {size: 9}, yAdjust: -15 }
        };
    }

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Close',
                data: closes,
                borderColor: '#e2e8f0',
                backgroundColor: 'rgba(226,232,240,0.05)',
                fill: true,
                tension: 0.2,
                pointRadius: 0,
                borderWidth: 1.5,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                annotation: { annotations: annotations }
            },
            scales: {
                x: { ticks: { color: '#64748b', maxTicksLimit: 12, maxRotation: 45 }, grid: { color: '#1e293b' } },
                y: { ticks: { color: '#64748b' }, grid: { color: '#1e293b' } }
            }
        }
    });
}

function closeModal() {
    document.getElementById('tradeModal').classList.remove('active');
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function refreshAll() {
    loadStats();
    loadPositions();
    loadSignals();
    loadTrades();
    loadPnlChart();
}

// === Telegram ===

async function loadTgStatus() {
    try {
        const r = await fetch('/api/telegram/status');
        const s = await r.json();
        const dot = document.getElementById('tgDot');
        const txt = document.getElementById('tgStatusText');
        const btn = document.getElementById('tgAuthBtn');
        const grp = document.getElementById('tgGroup');
        const last = document.getElementById('tgLastMsg');

        if (s.listening) {
            dot.style.background = '#10b981';
            dot.style.boxShadow = '0 0 8px rgba(16,185,129,0.5)';
            txt.textContent = 'Telegram: 监听中';
            btn.style.display = 'none';
            grp.textContent = s.group_name || '';
        } else if (s.connected) {
            dot.style.background = '#f59e0b';
            dot.style.boxShadow = '0 0 8px rgba(245,158,11,0.5)';
            txt.textContent = 'Telegram: 已连接(未监听)';
            btn.textContent = '重新连接';
        } else {
            dot.style.background = '#ef4444';
            dot.style.boxShadow = '0 0 8px rgba(239,68,68,0.4)';
            txt.textContent = 'Telegram: 未连接';
            btn.style.display = '';
            btn.textContent = '连接 Telegram';
        }
        if (s.error) txt.textContent += ' - ' + s.error;
        if (s.last_message) last.textContent = s.last_message;
    } catch(e) {}
}

function showTgAuth() {
    const panel = document.getElementById('tgAuthPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

async function tgSendCode() {
    const phone = document.getElementById('tgPhone').value.trim();
    if (!phone) { alert('请输入手机号'); return; }
    document.getElementById('tgSendBtn').textContent = '发送中...';
    const r = await fetch('/api/telegram/auth/send-code', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({phone: phone})
    });
    const res = await r.json();
    document.getElementById('tgSendBtn').textContent = '发送验证码';
    if (res.ok) {
        document.getElementById('tgCodePanel').style.display = 'block';
        alert('验证码已发送到你的 Telegram 应用');
    } else {
        alert(res.error || '发送失败');
    }
}

async function tgVerify() {
    const code = document.getElementById('tgCode').value.trim();
    const pw = document.getElementById('tg2FA').value.trim();
    if (!code) { alert('请输入验证码'); return; }
    const r = await fetch('/api/telegram/auth/verify', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({code: code, password: pw})
    });
    const res = await r.json();
    if (res.needs_2fa) {
        alert('需要两步验证密码，请填写后重试');
    } else if (res.ok) {
        alert('认证成功! 用户: ' + res.user + '，开始监听群消息');
        document.getElementById('tgAuthPanel').style.display = 'none';
        loadTgStatus();
    } else {
        alert(res.error || '验证失败');
    }
}

async function fetchHistory() {
    const r = await fetch('/api/telegram/fetch-history', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({limit: 30})
    });
    const res = await r.json();
    if (res.error) { alert(res.error); return; }
    const msgs = res.messages || [];
    const parsed = msgs.filter(m => m.parsed);
    alert('获取到 ' + msgs.length + ' 条消息，其中 ' + parsed.length + ' 条识别为信号');
    // Auto-create parsed signals
    for (const m of parsed) {
        const p = m.parsed;
        await fetch('/api/signal', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                symbol: p.symbol, direction: p.direction,
                entry_price: p.entry_price, stop_loss: p.stop_loss || 0,
                take_profit: p.take_profit || 0, leverage: p.leverage || 10,
                raw_message: m.text
            })
        });
    }
    if (parsed.length > 0) refreshAll();
}

// Auto-fill signal time with now
function setDefaultSignalTime() {
    const now = new Date();
    const pad = n => String(n).padStart(2,'0');
    document.getElementById('fSignalTime').value = now.getFullYear() + '-' + pad(now.getMonth()+1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
}
setDefaultSignalTime();

// Init
loadConfig();
loadCategories();
refreshAll();
loadTgStatus();
// Auto refresh every 30s
setInterval(() => {
    loadStats();
    loadPositions();
    loadTgStatus();
}, 30000);
</script>
</body>
</html>
'''

# ===== Main =====
if __name__ == '__main__':
    init_db()
    # Start position monitor thread
    monitor_thread = threading.Thread(target=position_monitor, daemon=True)
    monitor_thread.start()
    # Auto-start Telegram listener if session exists
    if os.path.exists(TG_SESSION_PATH + '.session'):
        print("[Telegram] Session found, starting listener...")
        start_telegram_listener()
    print("=" * 50)
    print("Telegram Signal Tracker")
    print(f"http://0.0.0.0:5112/")
    print("=" * 50)
    app.run(host='0.0.0.0', port=5112, debug=False)
