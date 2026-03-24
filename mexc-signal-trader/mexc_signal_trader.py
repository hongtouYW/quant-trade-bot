#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
MEXC Signal Trader - 抓取飞机群 vip點位策略 信号，自动在 MEXC 开单
Port: 5113
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
import ccxt
import traceback
from datetime import datetime, timedelta, timezone

app = Flask(__name__)

DB_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'mexc_signals.db')

# ===== MEXC Config =====
MEXC_API_KEY = os.environ.get('MEXC_API_KEY', 'mx0vglC6HmoMd7bHEd')
MEXC_API_SECRET = os.environ.get('MEXC_API_SECRET', 'b309f4ef0c47466e90dddb7e0f9ebd88')

# ===== Telegram Listener Config (Telethon user client) =====
TG_API_ID = 37356394
TG_API_HASH = '02b91c774b0ae70701daaff905cbd295'
TG_GROUP = 'kokoworld886'
TG_SESSION_PATH = os.environ.get('TG_SESSION_PATH',
    os.path.join(os.path.dirname(os.path.abspath(__file__)), 'tg_session'))

# ===== Telegram Bot Config (发送通知到群) =====
TG_BOT_TOKEN = '8777086789:AAHea8llTZaoXOQS95QyB4_JldSl4QoFrl0'
TG_CHAT_ID = '-5242590434'

# ===== Signal Filter =====
SIGNAL_CATEGORY = 'vip點位策略'

# ===== State =====
tg_client = None
tg_auth_phone = None
tg_auth_hash = None
tg_status = {'connected': False, 'listening': False, 'last_message': None, 'error': None}

_tg_loop = None
_tg_loop_thread = None

exchange = None  # ccxt MEXC instance

# ===== Persistent Telethon Event Loop =====

def _run_tg_loop(loop):
    asyncio.set_event_loop(loop)
    loop.run_forever()

def get_tg_loop():
    global _tg_loop, _tg_loop_thread
    if _tg_loop is None or _tg_loop.is_closed():
        _tg_loop = asyncio.new_event_loop()
        _tg_loop_thread = threading.Thread(target=_run_tg_loop, args=(_tg_loop,), daemon=True)
        _tg_loop_thread.start()
    return _tg_loop

# ===== MEXC Exchange =====

def init_exchange():
    """Initialize MEXC exchange via ccxt"""
    global exchange
    if not MEXC_API_KEY or not MEXC_API_SECRET:
        print("[MEXC] WARNING: API keys not set! Set MEXC_API_KEY and MEXC_API_SECRET env vars.")
        return False
    try:
        exchange = ccxt.mexc({
            'apiKey': MEXC_API_KEY,
            'secret': MEXC_API_SECRET,
            'options': {
                'defaultType': 'swap',  # USDT-M futures
            },
            'enableRateLimit': True,
        })
        exchange.load_markets()
        balance = exchange.fetch_balance()
        usdt = balance.get('USDT', {})
        print(f"[MEXC] Connected! USDT Balance: {usdt.get('total', 0)}")
        return True
    except Exception as e:
        print(f"[MEXC] Init failed: {e}")
        exchange = None
        return False

def get_mexc_price(symbol):
    """Get current price from MEXC"""
    global exchange
    try:
        if exchange is None:
            return None
        # Normalize symbol: BTCUSDT -> BTC/USDT:USDT
        sym = symbol.upper().replace('USDT', '')
        ccxt_symbol = f"{sym}/USDT:USDT"
        ticker = exchange.fetch_ticker(ccxt_symbol)
        return float(ticker['last'])
    except Exception as e:
        # Fallback to Binance API for price
        try:
            sym = symbol.upper()
            if not sym.endswith('USDT'):
                sym = sym + 'USDT'
            resp = requests.get(f'https://fapi.binance.com/fapi/v1/ticker/price',
                              params={'symbol': sym}, timeout=5)
            data = resp.json()
            return float(data['price'])
        except:
            print(f"[Price Error] {symbol}: {e}")
            return None

def get_mexc_balance():
    """Get MEXC account balance"""
    global exchange
    if exchange is None:
        return {'total': 0, 'free': 0, 'used': 0}
    try:
        balance = exchange.fetch_balance()
        usdt = balance.get('USDT', {})
        return {
            'total': float(usdt.get('total', 0)),
            'free': float(usdt.get('free', 0)),
            'used': float(usdt.get('used', 0))
        }
    except Exception as e:
        print(f"[MEXC Balance Error] {e}")
        return {'total': 0, 'free': 0, 'used': 0}

def mexc_open_order(symbol, direction, leverage, position_size_usdt, stop_loss=None, take_profit=None):
    """Open a real order on MEXC
    Returns dict with order details or None on failure"""
    global exchange
    if exchange is None:
        print("[MEXC] Exchange not initialized!")
        return None

    try:
        # Normalize symbol
        sym = symbol.upper().replace('USDT', '')
        ccxt_symbol = f"{sym}/USDT:USDT"

        # Set leverage
        try:
            exchange.set_leverage(leverage, ccxt_symbol)
        except Exception as e:
            print(f"[MEXC] Set leverage warning: {e}")

        # Set margin mode to cross
        try:
            exchange.set_margin_mode('cross', ccxt_symbol)
        except Exception as e:
            print(f"[MEXC] Set margin mode warning: {e}")

        # Get current price for amount calculation
        ticker = exchange.fetch_ticker(ccxt_symbol)
        current_price = float(ticker['last'])

        # Calculate amount (in base currency)
        # position_size_usdt is the margin, notional = margin * leverage
        notional_usdt = position_size_usdt * leverage
        amount = notional_usdt / current_price

        # Round amount to market precision
        market = exchange.market(ccxt_symbol)
        amount = exchange.amount_to_precision(ccxt_symbol, amount)
        amount = float(amount)

        if amount <= 0:
            print(f"[MEXC] Amount too small for {symbol}")
            return None

        # Place market order
        side = 'buy' if direction == 'LONG' else 'sell'
        order = exchange.create_order(
            symbol=ccxt_symbol,
            type='market',
            side=side,
            amount=amount,
        )

        entry_price = float(order.get('average') or order.get('price') or current_price)
        filled = float(order.get('filled') or amount)

        result = {
            'order_id': order.get('id'),
            'symbol': ccxt_symbol,
            'direction': direction,
            'entry_price': entry_price,
            'amount': filled,
            'leverage': leverage,
            'position_size_usdt': position_size_usdt,
            'notional_usdt': round(filled * entry_price, 2),
        }

        print(f"[MEXC] Opened {direction} {symbol} @ {entry_price}, amount={filled}, leverage={leverage}x")

        # Set stop loss and take profit if provided
        if stop_loss:
            try:
                sl_side = 'sell' if direction == 'LONG' else 'buy'
                sl_params = {'stopPrice': stop_loss, 'reduceOnly': True}
                exchange.create_order(
                    symbol=ccxt_symbol,
                    type='stop_market',
                    side=sl_side,
                    amount=filled,
                    params=sl_params,
                )
                result['sl_set'] = True
                print(f"[MEXC] SL set @ {stop_loss}")
            except Exception as e:
                print(f"[MEXC] SL failed: {e}")
                result['sl_set'] = False

        if take_profit:
            try:
                tp_side = 'sell' if direction == 'LONG' else 'buy'
                tp_params = {'stopPrice': take_profit, 'reduceOnly': True}
                exchange.create_order(
                    symbol=ccxt_symbol,
                    type='take_profit_market',
                    side=tp_side,
                    amount=filled,
                    params=tp_params,
                )
                result['tp_set'] = True
                print(f"[MEXC] TP set @ {take_profit}")
            except Exception as e:
                print(f"[MEXC] TP failed: {e}")
                result['tp_set'] = False

        return result

    except Exception as e:
        print(f"[MEXC] Order failed: {e}")
        traceback.print_exc()
        return None

def mexc_close_position(symbol, direction, amount=None):
    """Close a position on MEXC"""
    global exchange
    if exchange is None:
        return None

    try:
        sym = symbol.upper().replace('USDT', '').split('/')[0].split(':')[0]
        ccxt_symbol = f"{sym}/USDT:USDT"

        # If amount not specified, close entire position
        if not amount:
            positions = exchange.fetch_positions([ccxt_symbol])
            for pos in positions:
                if float(pos.get('contracts', 0)) > 0:
                    amount = float(pos['contracts'])
                    break
            if not amount:
                print(f"[MEXC] No open position for {symbol}")
                return None

        # Close by placing opposite order
        side = 'sell' if direction == 'LONG' else 'buy'
        order = exchange.create_order(
            symbol=ccxt_symbol,
            type='market',
            side=side,
            amount=amount,
            params={'reduceOnly': True},
        )

        exit_price = float(order.get('average') or order.get('price') or 0)
        print(f"[MEXC] Closed {direction} {symbol} @ {exit_price}")
        return {
            'order_id': order.get('id'),
            'exit_price': exit_price,
            'filled': float(order.get('filled') or amount),
        }

    except Exception as e:
        print(f"[MEXC] Close failed: {e}")
        traceback.print_exc()
        return None

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
            source TEXT DEFAULT 'telegram',
            category TEXT DEFAULT 'vip點位策略',
            symbol TEXT NOT NULL,
            direction TEXT NOT NULL,
            entry_price REAL,
            stop_loss REAL,
            take_profit REAL,
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
            amount REAL,
            position_size REAL,
            notional REAL,
            pnl REAL,
            pnl_pct REAL,
            fees REAL,
            close_reason TEXT,
            status TEXT DEFAULT 'open',
            mexc_order_id TEXT,
            mexc_close_order_id TEXT
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

        CREATE TABLE IF NOT EXISTS symbol_aliases (
            alias TEXT PRIMARY KEY,
            symbol TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS trade_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            level TEXT DEFAULT 'INFO',
            message TEXT
        );
    ''')

    # Seed default symbol aliases
    default_aliases = {
        '大餅': 'BTC', '大饼': 'BTC', '比特币': 'BTC', '比特幣': 'BTC', '餅': 'BTC', '饼': 'BTC',
        '以太': 'ETH', '以太坊': 'ETH', '姨太': 'ETH', 'E太': 'ETH', '二餅': 'ETH', '二饼': 'ETH',
        '索拉納': 'SOL', '索拉纳': 'SOL',
        '狗狗幣': 'DOGE', '狗狗币': 'DOGE', '狗幣': 'DOGE', '狗币': 'DOGE', '狗子': 'DOGE',
        '瑞波': 'XRP',
        '幣安幣': 'BNB', '币安币': 'BNB',
        '青蛙': 'PEPE',
        '柴犬': 'SHIB',
    }
    for alias, symbol in default_aliases.items():
        conn.execute('INSERT OR IGNORE INTO symbol_aliases (alias, symbol) VALUES (?, ?)', (alias, symbol))

    # Default config
    defaults = {
        'position_size_usdt': '200',  # 每笔下单金额 (保证金)
        'max_positions': '5',         # 最大持仓数
        'auto_trade': '1',            # 自动开单 1=on 0=off
        'default_leverage': '10',     # 默认杠杆
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

def add_log(msg, level='INFO'):
    try:
        conn = get_db()
        conn.execute('INSERT INTO trade_log (level, message) VALUES (?, ?)', (level, msg))
        conn.commit()
        conn.close()
    except:
        pass

# ===== Signal Parser =====

def resolve_symbol_alias(text):
    """Check if text contains a symbol alias"""
    conn = get_db()
    aliases = conn.execute('SELECT alias, symbol FROM symbol_aliases ORDER BY LENGTH(alias) DESC').fetchall()
    conn.close()
    for row in aliases:
        if row['alias'] in text:
            return row['symbol'], row['alias']
    return None, None

def parse_vip_signal(text):
    """Parse vip點位策略 signal format.
    Returns dict with symbol, direction, stop_loss, take_profit, leverage, entry_price or None.
    """
    if not text:
        return None
    text = text.strip()

    result = {
        'symbol': None, 'direction': None, 'stop_loss': None,
        'take_profit': None, 'leverage': None, 'entry_price': None
    }

    # Step 1: Check aliases
    alias_sym, matched_alias = resolve_symbol_alias(text)
    if alias_sym:
        result['symbol'] = alias_sym + 'USDT' if not alias_sym.endswith('USDT') else alias_sym

    # Step 2: Extract symbol from English text (e.g. #BTC, ETHUSDT, $SOL)
    if not result['symbol']:
        sym_match = re.search(r'[#\$]?([A-Z]{2,10})(USDT|/USDT)?', text.upper())
        if sym_match:
            sym = sym_match.group(1)
            if sym in ('USDT', 'USD', 'THE', 'FOR', 'AND', 'NOT', 'ALL', 'BUT', 'VIP'):
                sym = None
            else:
                result['symbol'] = sym + 'USDT' if not sym.endswith('USDT') else sym

    # Extract direction
    text_upper = text.upper()
    if any(w in text for w in ['做多', '輕倉多', '轻仓多', '加多', '開多', '开多', '進多', '进多']) or \
       any(w in text_upper for w in ['LONG', 'BUY']) or \
       (re.search(r'(?<![做開开加輕轻進进])多(?!空)', text) and '做空' not in text):
        result['direction'] = 'LONG'
    elif any(w in text for w in ['做空', '輕倉空', '轻仓空', '加空', '開空', '开空', '進空', '进空']) or \
         any(w in text_upper for w in ['SHORT', 'SELL']) or \
         (re.search(r'(?<![做開开加輕轻進进])空(?!多)', text) and '做多' not in text):
        result['direction'] = 'SHORT'

    # Stop loss
    sl_match = re.search(r'(?:止損|止损|SL|stop.?loss|防守|停損|停损)[:\s：]*(\d+\.?\d*)', text, re.IGNORECASE)
    if sl_match:
        result['stop_loss'] = float(sl_match.group(1))

    # Take profit — support multiple targets
    tp_match = re.search(r'(?:止盈|TP|take.?profit|目標|目标|到價|到价)[:\s：]*(\d+\.?\d*)', text, re.IGNORECASE)
    if tp_match:
        result['take_profit'] = float(tp_match.group(1))

    # Entry price — support range format
    entry_match = re.search(r'(?:入場|入场|entry|開倉|开仓|價格|价格|price|進場|进场|市價|市价|點位|点位)[:\s：-]*(\d+\.?\d*)\s*[-~]\s*(\d+\.?\d*)', text, re.IGNORECASE)
    if entry_match:
        p1, p2 = float(entry_match.group(1)), float(entry_match.group(2))
        result['entry_price'] = round((p1 + p2) / 2, 6)
    else:
        entry_match = re.search(r'(?:入場|入场|entry|開倉|开仓|價格|价格|price|進場|进场|市價|市价|點位|点位)[:\s：-]*(\d+\.?\d*)', text, re.IGNORECASE)
        if entry_match:
            result['entry_price'] = float(entry_match.group(1))

    # Fallback entry: "附近" pattern
    if not result['entry_price']:
        nearby_range = re.search(r'(\d+\.?\d*)\s*[-~]\s*(\d+\.?\d*)\s*附近', text)
        if nearby_range:
            p1, p2 = float(nearby_range.group(1)), float(nearby_range.group(2))
            result['entry_price'] = round((p1 + p2) / 2, 6)
        else:
            nearby_single = re.search(r'(\d+\.?\d*)\s*附近', text)
            if nearby_single:
                result['entry_price'] = float(nearby_single.group(1))

    # Take profit range fallback
    if not result['take_profit']:
        tp_range = re.search(r'(?:止盈|TP|take.?profit|目標|目标|到價|到价)[:\s：]*(\d+\.?\d*)\s*[-~]\s*(\d+\.?\d*)', text, re.IGNORECASE)
        if tp_range:
            result['take_profit'] = float(tp_range.group(1))

    # Leverage
    lev_match = re.search(r'(\d+)\s*[-~]\s*(\d+)\s*[xX倍]', text)
    if lev_match:
        result['leverage'] = int(lev_match.group(2))
    else:
        lev_match = re.search(r'(\d+)\s*[xX倍]', text)
        if lev_match:
            result['leverage'] = int(lev_match.group(1))

    # Minimum: symbol + direction + (sl or tp or entry_price)
    if result['symbol'] and result['direction'] and (result['stop_loss'] or result['take_profit'] or result['entry_price']):
        return result

    return None

# ===== Auto Trade Logic =====

def auto_open_trade(signal_id):
    """Auto-open a real trade on MEXC from a signal"""
    try:
        conn = get_db()
        signal = conn.execute('SELECT * FROM signals WHERE id=?', (signal_id,)).fetchone()
        if not signal or signal['status'] != 'pending':
            conn.close()
            return False

        config = get_config()

        # Check auto_trade
        if config.get('auto_trade', '1') != '1':
            print(f"[Auto-Trade] Auto-trade disabled, signal #{signal_id} stays pending")
            conn.close()
            return False

        # Check max positions
        max_pos = int(config.get('max_positions', 5))
        open_count = conn.execute('SELECT COUNT(*) as cnt FROM trades WHERE status=?', ('open',)).fetchone()['cnt']
        if open_count >= max_pos:
            print(f"[Auto-Trade] Max positions ({max_pos}) reached, skipping")
            add_log(f"跳过 {signal['symbol']}: 已达最大持仓数 {max_pos}", 'WARN')
            conn.close()
            return False

        position_size = float(config.get('position_size_usdt', 200))
        leverage = signal['leverage'] or int(config.get('default_leverage', 10))

        # Open on MEXC
        order_result = mexc_open_order(
            symbol=signal['symbol'],
            direction=signal['direction'],
            leverage=leverage,
            position_size_usdt=position_size,
            stop_loss=signal['stop_loss'],
            take_profit=signal['take_profit'],
        )

        if order_result is None:
            print(f"[Auto-Trade] MEXC order failed for {signal['symbol']}")
            add_log(f"MEXC下单失败: {signal['symbol']} {signal['direction']}", 'ERROR')
            conn.close()
            return False

        now = datetime.now(timezone.utc).isoformat()

        conn.execute('''
            INSERT INTO trades (signal_id, opened_at, symbol, direction, entry_price, leverage,
                               amount, position_size, notional, status, mexc_order_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', ?)
        ''', (
            signal_id, now, signal['symbol'], signal['direction'],
            order_result['entry_price'], leverage,
            order_result['amount'], position_size,
            order_result.get('notional_usdt', 0),
            order_result.get('order_id', ''),
        ))
        conn.execute('UPDATE signals SET status=?, entry_price=? WHERE id=?',
                     ('active', order_result['entry_price'], signal_id))
        conn.commit()
        conn.close()

        add_log(f"开仓成功: {signal['symbol']} {signal['direction']} @ {order_result['entry_price']}, "
                f"size={position_size}U, lev={leverage}x")

        return True

    except Exception as e:
        print(f"[Auto-Trade Error] {e}")
        traceback.print_exc()
        add_log(f"开仓异常: {e}", 'ERROR')
        return False

# ===== Position Monitor =====

monitor_running = False

def position_monitor():
    """Background thread: check MEXC positions every 30s for TP/SL"""
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
                price = get_mexc_price(symbol)
                if price is None:
                    continue

                direction = trade['direction']
                sl = trade['stop_loss']
                tp = trade['take_profit']
                close_reason = None

                if sl and direction == 'LONG' and price <= sl:
                    close_reason = 'sl'
                elif sl and direction == 'SHORT' and price >= sl:
                    close_reason = 'sl'
                elif tp and direction == 'LONG' and price >= tp:
                    close_reason = 'tp'
                elif tp and direction == 'SHORT' and price <= tp:
                    close_reason = 'tp'

                if close_reason:
                    close_trade(trade['id'], price, close_reason)

        except Exception as e:
            print(f"[Monitor Error] {e}")

        time.sleep(30)

def close_trade(trade_id, exit_price, reason):
    """Close a trade and record PnL"""
    conn = get_db()
    trade = conn.execute('SELECT * FROM trades WHERE id = ?', (trade_id,)).fetchone()
    if not trade or trade['status'] != 'open':
        conn.close()
        return None

    # Close on MEXC
    close_result = mexc_close_position(trade['symbol'], trade['direction'], trade['amount'])

    if close_result:
        exit_price = close_result['exit_price'] or exit_price

    now = datetime.now(timezone.utc).isoformat()
    entry = trade['entry_price']
    direction = trade['direction']
    leverage = trade['leverage']
    size = trade['position_size']

    if direction == 'LONG':
        pnl_pct = ((exit_price - entry) / entry) * leverage * 100
    else:
        pnl_pct = ((entry - exit_price) / entry) * leverage * 100
    pnl = size * (pnl_pct / 100)

    # Estimate fees (0.06% maker+taker per side for MEXC futures)
    fees = size * leverage * 0.0006 * 2

    pnl_after_fees = pnl - fees

    conn.execute('''
        UPDATE trades SET exit_price=?, closed_at=?, pnl=?, pnl_pct=?, fees=?,
                         close_reason=?, status='closed', mexc_close_order_id=?
        WHERE id=?
    ''', (exit_price, now, round(pnl_after_fees, 4), round(pnl_pct, 2), round(fees, 4),
          reason, close_result.get('order_id', '') if close_result else '', trade_id))

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

    result = {'pnl': round(pnl_after_fees, 4), 'pnl_pct': round(pnl_pct, 2), 'fees': round(fees, 4), 'exit_price': exit_price}
    add_log(f"平仓 {trade['symbol']} {direction} @ {exit_price} | "
            f"PnL: {pnl_after_fees:+.2f}U ({pnl_pct:+.1f}%) | {reason.upper()}")

    # Send close notification
    threading.Thread(target=send_tg_close_notification, args=(dict(trade), reason, result), daemon=True).start()

    return result

# ===== Telegram Bot Notification =====

def send_tg_notification(signal, raw_message=None, trade_info=None):
    """Send new signal + trade notification to Telegram group"""
    try:
        direction_emoji = '🟢 做多 LONG' if signal['direction'] == 'LONG' else '🔴 做空 SHORT'
        sym = signal['symbol'].replace('USDT', '/USDT')
        now_str = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M UTC')

        price = get_mexc_price(signal['symbol'])
        price_str = f"<code>{price}</code>" if price else "获取失败"

        entry = signal.get('entry_price')
        entry_str = f"<code>{entry}</code>" if entry else price_str

        text = f"📡 <b>MEXC · 新信号开仓</b>\n"
        text += f"━━━━━━━━━━━━━━━\n"
        text += f"💰 货币: <b>{sym}</b>\n"
        text += f"📊 方向: <b>{direction_emoji}</b>\n"
        text += f"🎯 开仓价: {entry_str}\n"
        text += f"📈 当前价: {price_str}\n"
        if signal.get('stop_loss'):
            text += f"🛑 止损: <code>{signal['stop_loss']}</code>\n"
        if signal.get('take_profit'):
            text += f"✅ 止盈: <code>{signal['take_profit']}</code>\n"
        text += f"⚡ 杠杆: <code>{signal.get('leverage') or 10}x</code>\n"
        text += f"🕐 时间: <code>{now_str}</code>\n"

        if trade_info:
            text += f"💵 保证金: <code>{trade_info.get('size', '—')} USDT</code>\n"

        text += f"━━━━━━━━━━━━━━━\n"
        text += f"🏦 交易所: <b>MEXC (实盘)</b>\n"

        if raw_message:
            raw_short = raw_message[:300].replace('<', '&lt;').replace('>', '&gt;')
            text += f"💬 原始信息:\n<blockquote>{raw_short}</blockquote>"

        url = f'https://api.telegram.org/bot{TG_BOT_TOKEN}/sendMessage'
        resp = requests.post(url, json={
            'chat_id': TG_CHAT_ID,
            'text': text,
            'parse_mode': 'HTML',
        }, timeout=10)
        if resp.status_code == 200:
            print(f"[TG Bot] Open notification sent: {signal['symbol']} {signal['direction']}")
        else:
            print(f"[TG Bot] Send failed: {resp.status_code} {resp.text}")
    except Exception as e:
        print(f"[TG Bot] Error: {e}")

def send_tg_close_notification(trade, reason, pnl_info):
    """Send trade close notification to Telegram group"""
    try:
        sym = trade['symbol'].replace('USDT', '/USDT')
        direction = trade['direction']
        now_str = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M UTC')

        if reason == 'tp':
            header = '✅ <b>MEXC · 止盈平仓</b>'
        elif reason == 'sl':
            header = '🛑 <b>MEXC · 止损平仓</b>'
        else:
            header = '📤 <b>MEXC · 手动平仓</b>'

        pnl = pnl_info.get('pnl', 0)
        pnl_pct = pnl_info.get('pnl_pct', 0)
        fees = pnl_info.get('fees', 0)
        pnl_emoji = '🟢' if pnl >= 0 else '🔴'
        direction_str = '做多' if direction == 'LONG' else '做空'

        text = f"{header}\n"
        text += f"━━━━━━━━━━━━━━━\n"
        text += f"💰 货币: <b>{sym}</b> ({direction_str})\n"
        text += f"🎯 开仓价: <code>{trade['entry_price']}</code>\n"
        text += f"📤 平仓价: <code>{pnl_info.get('exit_price', '—')}</code>\n"
        text += f"⚡ 杠杆: <code>{trade['leverage']}x</code>\n"
        text += f"💵 保证金: <code>{trade['position_size']} USDT</code>\n"
        text += f"━━━━━━━━━━━━━━━\n"
        text += f"{pnl_emoji} 盈亏: <code>{pnl:+.2f} USDT ({pnl_pct:+.2f}%)</code>\n"
        text += f"💸 手续费: <code>{fees:.2f} USDT</code>\n"
        text += f"🕐 时间: <code>{now_str}</code>\n"
        text += f"🏦 交易所: <b>MEXC (实盘)</b>\n"

        url = f'https://api.telegram.org/bot{TG_BOT_TOKEN}/sendMessage'
        resp = requests.post(url, json={
            'chat_id': TG_CHAT_ID,
            'text': text,
            'parse_mode': 'HTML',
        }, timeout=10)
        if resp.status_code == 200:
            print(f"[TG Bot] Close notification sent: {sym} {reason} PnL={pnl:+.2f}")
        else:
            print(f"[TG Bot] Close send failed: {resp.status_code} {resp.text}")
    except Exception as e:
        print(f"[TG Bot Close] Error: {e}")

# ===== Telegram Listener =====

def is_vip_signal(text):
    """Check if a message is from the vip點位策略 category.
    We look for keywords that indicate VIP signals."""
    if not text:
        return False
    keywords = ['vip', 'VIP', '點位', '点位', '策略', 'vip點位', 'vip点位',
                '信號', '信号', '止損', '止损', '止盈', '目標', '目标',
                '做多', '做空', 'LONG', 'SHORT', '開多', '開空', '开多', '开空']
    text_lower = text.lower()
    # Must contain at least direction + some price info to be a signal
    has_direction = any(w in text for w in ['做多', '做空', '開多', '開空', '开多', '开空']) or \
                   any(w in text.upper() for w in ['LONG', 'SHORT'])
    has_price = bool(re.search(r'\d+\.?\d*', text))
    return has_direction and has_price

def start_telegram_listener():
    """Start Telethon client on the persistent event loop"""
    print("[Telegram] Starting listener...")
    loop = get_tg_loop()
    future = asyncio.run_coroutine_threadsafe(_run_telegram_listener(), loop)
    # Log if future fails
    def _on_done(f):
        try:
            f.result()
        except Exception as e:
            print(f"[Telegram] Listener crashed: {e}")
    future.add_done_callback(_on_done)

async def _run_telegram_listener():
    global tg_client, tg_status
    from telethon import TelegramClient, events
    try:
        print("[Telegram] Connecting...")
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

        # Find the group
        target = None
        async for dialog in tg_client.iter_dialogs():
            if TG_GROUP.lower() in (dialog.name or '').lower():
                target = dialog.entity
                break

        if not target:
            try:
                target = await tg_client.get_entity(TG_GROUP)
            except:
                tg_status['error'] = f'Cannot find group: {TG_GROUP}'
                print(f"[Telegram] Cannot find group: {TG_GROUP}")

        if target:
            tg_status['listening'] = True
            tg_status['group_name'] = getattr(target, 'title', TG_GROUP)
            print(f"[Telegram] Listening to: {tg_status['group_name']} (filtering: {SIGNAL_CATEGORY})")

            @tg_client.on(events.NewMessage(chats=target))
            async def handler(event):
                msg_text = event.message.text
                if not msg_text:
                    return
                tg_status['last_message'] = msg_text[:100]
                print(f"[TG Message] {msg_text[:80]}")

                # Filter: only process messages that look like trading signals
                if not is_vip_signal(msg_text):
                    return

                signal = parse_vip_signal(msg_text)
                if signal:
                    print(f"[VIP Signal] Parsed: {signal['symbol']} {signal['direction']}")
                    msg_time = event.message.date.strftime('%Y-%m-%d %H:%M:%S') if event.message.date else None

                    conn = get_db()
                    conn.execute('''
                        INSERT INTO signals (symbol, direction, entry_price, stop_loss, take_profit,
                                           leverage, raw_message, source, status, category, signal_time)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'telegram', 'pending', ?, ?)
                    ''', (
                        signal['symbol'], signal['direction'], signal.get('entry_price'),
                        signal.get('stop_loss') or 0, signal.get('take_profit') or 0,
                        signal.get('leverage') or int(get_config().get('default_leverage', 10)),
                        msg_text,
                        SIGNAL_CATEGORY,
                        msg_time
                    ))
                    conn.commit()
                    signal_id = conn.execute('SELECT last_insert_rowid()').fetchone()[0]
                    conn.close()

                    add_log(f"新信号: {signal['symbol']} {signal['direction']} (from TG)")

                    # Auto-open trade on MEXC
                    trade_info = None
                    if auto_open_trade(signal_id):
                        c2 = get_db()
                        t = c2.execute('SELECT position_size FROM trades WHERE signal_id=?', (signal_id,)).fetchone()
                        if t:
                            trade_info = {'size': round(t['position_size'], 2)}
                        c2.close()
                    else:
                        print(f"[VIP Signal] Auto-trade failed for {signal['symbol']}, stays pending")

                    # Telegram notification
                    threading.Thread(target=send_tg_notification, args=(signal, msg_text, trade_info), daemon=True).start()

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
        start_telegram_listener()
        return jsonify(result)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

# ===== API Endpoints =====

@app.route('/api/stats')
def api_stats():
    bal = get_mexc_balance()
    conn = get_db()
    closed = conn.execute('SELECT COUNT(*) as cnt, COALESCE(SUM(pnl),0) as total_pnl, COALESCE(SUM(fees),0) as total_fees FROM trades WHERE status=?', ('closed',)).fetchone()
    wins = conn.execute('SELECT COUNT(*) as cnt FROM trades WHERE status=? AND pnl > 0', ('closed',)).fetchone()
    open_count = conn.execute('SELECT COUNT(*) as cnt FROM trades WHERE status=?', ('open',)).fetchone()
    today = datetime.now(timezone.utc).strftime('%Y-%m-%d')
    today_row = conn.execute('SELECT COALESCE(pnl, 0) as pnl FROM daily_pnl WHERE date=?', (today,)).fetchone()
    pending = conn.execute('SELECT COUNT(*) as cnt FROM signals WHERE status=?', ('pending',)).fetchone()
    conn.close()

    total_trades = closed['cnt']
    win_rate = round(wins['cnt'] / total_trades * 100, 1) if total_trades > 0 else 0

    return jsonify({
        'mexc_balance': bal['total'],
        'mexc_free': bal['free'],
        'mexc_used': bal['used'],
        'total_pnl': round(closed['total_pnl'], 2),
        'total_fees': round(closed['total_fees'], 2),
        'pnl_pct': round(closed['total_pnl'] / bal['total'] * 100, 2) if bal['total'] > 0 else 0,
        'total_trades': total_trades,
        'win_count': wins['cnt'],
        'win_rate': win_rate,
        'open_positions': open_count['cnt'],
        'today_pnl': round(today_row['pnl'], 2) if today_row else 0,
        'pending_signals': pending['cnt'],
        'exchange_connected': exchange is not None,
    })

@app.route('/api/signals')
def api_signals():
    status = request.args.get('status', '')
    conn = get_db()
    conditions = []
    params = []
    if status:
        conditions.append('status=?')
        params.append(status)
    where = ' WHERE ' + ' AND '.join(conditions) if conditions else ''
    rows = conn.execute(f'SELECT * FROM signals{where} ORDER BY COALESCE(signal_time, created_at) DESC LIMIT 200', params).fetchall()
    conn.close()
    return jsonify([dict(r) for r in rows])

@app.route('/api/signal', methods=['POST'])
def api_add_signal():
    data = request.json
    required = ['symbol', 'direction']
    for f in required:
        if f not in data or data[f] is None:
            return jsonify({'error': f'Missing field: {f}'}), 400

    symbol = data['symbol'].upper().strip()
    if not symbol.endswith('USDT'):
        symbol = symbol + 'USDT'
    direction = data['direction'].upper()
    if direction not in ('LONG', 'SHORT'):
        return jsonify({'error': 'Direction must be LONG or SHORT'}), 400

    conn = get_db()
    conn.execute('''
        INSERT INTO signals (symbol, direction, entry_price, stop_loss, take_profit, leverage, raw_message, notes, category)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ''', (symbol, direction,
          data.get('entry_price'),
          float(data.get('stop_loss', 0)),
          float(data.get('take_profit', 0)),
          int(data.get('leverage', 10)),
          data.get('raw_message', ''),
          data.get('notes', ''),
          data.get('category', SIGNAL_CATEGORY)))
    signal_id = conn.execute('SELECT last_insert_rowid()').fetchone()[0]
    conn.commit()
    conn.close()
    return jsonify({'ok': True, 'id': signal_id})

@app.route('/api/signal/<int:sid>/activate', methods=['PUT'])
def api_activate_signal(sid):
    success = auto_open_trade(sid)
    if success:
        return jsonify({'ok': True})
    return jsonify({'error': 'Failed to open trade'}), 500

@app.route('/api/signal/<int:sid>/close', methods=['PUT'])
def api_close_signal(sid):
    conn = get_db()
    trade = conn.execute('SELECT * FROM trades WHERE signal_id=? AND status=?', (sid, 'open')).fetchone()
    if not trade:
        conn.close()
        return jsonify({'error': 'No open trade for this signal'}), 404
    conn.close()
    price = get_mexc_price(trade['symbol'])
    if price is None:
        return jsonify({'error': 'Cannot get price'}), 500
    result = close_trade(trade['id'], price, 'manual')
    return jsonify({'ok': True, **(result or {})})

@app.route('/api/signal/<int:sid>/cancel', methods=['PUT'])
def api_cancel_signal(sid):
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
    conn = get_db()
    rows = conn.execute('''
        SELECT t.*, s.stop_loss as sl, s.take_profit as tp, s.raw_message, s.notes
        FROM trades t JOIN signals s ON t.signal_id = s.id
        ORDER BY t.opened_at DESC LIMIT 200
    ''').fetchall()
    conn.close()
    return jsonify([dict(r) for r in rows])

@app.route('/api/positions')
def api_positions():
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
        price = get_mexc_price(t['symbol'])
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

@app.route('/api/config', methods=['POST'])
def api_save_config():
    data = request.json
    conn = get_db()
    for k, v in data.items():
        conn.execute('INSERT OR REPLACE INTO account_config (key, value) VALUES (?, ?)', (k, str(v)))
    conn.commit()
    conn.close()
    return jsonify({'ok': True})

@app.route('/api/logs')
def api_logs():
    conn = get_db()
    rows = conn.execute('SELECT * FROM trade_log ORDER BY created_at DESC LIMIT 100').fetchall()
    conn.close()
    return jsonify([dict(r) for r in rows])

@app.route('/api/exchange/status')
def api_exchange_status():
    bal = get_mexc_balance()
    return jsonify({
        'connected': exchange is not None,
        'balance': bal,
        'api_key_set': bool(MEXC_API_KEY),
    })

@app.route('/api/aliases', methods=['GET'])
def api_get_aliases():
    conn = get_db()
    rows = conn.execute('SELECT * FROM symbol_aliases ORDER BY alias').fetchall()
    conn.close()
    return jsonify([dict(r) for r in rows])

@app.route('/api/aliases', methods=['POST'])
def api_add_alias():
    data = request.json
    alias = data.get('alias', '').strip()
    symbol = data.get('symbol', '').upper().strip()
    if not alias or not symbol:
        return jsonify({'error': 'alias and symbol required'}), 400
    conn = get_db()
    conn.execute('INSERT OR REPLACE INTO symbol_aliases (alias, symbol) VALUES (?, ?)', (alias, symbol))
    conn.commit()
    conn.close()
    return jsonify({'ok': True})

@app.route('/api/aliases/<alias>', methods=['DELETE'])
def api_delete_alias(alias):
    conn = get_db()
    conn.execute('DELETE FROM symbol_aliases WHERE alias=?', (alias,))
    conn.commit()
    conn.close()
    return jsonify({'ok': True})

# ===== Dashboard HTML =====

@app.route('/')
def index():
    return render_template_string(PAGE_HTML)

PAGE_HTML = r'''<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MEXC Signal Trader</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
    background: radial-gradient(ellipse at 20% 50%, rgba(245,158,11,0.04) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(16,185,129,0.04) 0%, transparent 50%);
    pointer-events: none; z-index: 0;
}
.container { max-width: 1440px; margin: 0 auto; padding: 20px 24px; position: relative; z-index: 1; }

.header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 20px 28px; margin-bottom: 24px;
    background: rgba(15,23,42,0.6); backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.06); border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.2);
}
.header h1 {
    font-size: 24px; font-weight: 700;
    background: linear-gradient(135deg, #f59e0b, #10b981);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}
.header-right { display: flex; align-items: center; gap: 12px; }
.exchange-badge {
    display: flex; align-items: center; gap: 6px; padding: 6px 14px;
    border-radius: 8px; font-size: 12px; font-weight: 600;
}
.exchange-badge.connected { background: rgba(16,185,129,0.12); color: #34d399; border: 1px solid rgba(16,185,129,0.2); }
.exchange-badge.disconnected { background: rgba(239,68,68,0.12); color: #f87171; border: 1px solid rgba(239,68,68,0.2); }

.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card {
    background: rgba(15,23,42,0.5); backdrop-filter: blur(16px);
    border: 1px solid rgba(255,255,255,0.06); border-radius: 14px;
    padding: 20px 22px; position: relative; overflow: hidden;
    transition: transform .2s ease;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(0,0,0,0.2); }
.stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; border-radius:14px 14px 0 0; }
.stat-card:nth-child(1)::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.stat-card:nth-child(2)::before { background: linear-gradient(90deg, #10b981, #34d399); }
.stat-card:nth-child(3)::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
.stat-card:nth-child(4)::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }
.stat-card .label { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px; font-weight:600; }
.stat-card .value { font-size:26px; font-weight:800; }
.stat-card .sub { font-size:12px; color:#64748b; margin-top:6px; }
.green { color: #10b981; } .red { color: #ef4444; } .blue { color: #38bdf8; } .yellow { color: #fbbf24; }

.section {
    background: rgba(15,23,42,0.5); backdrop-filter: blur(16px);
    border: 1px solid rgba(255,255,255,0.06); border-radius: 16px;
    padding: 24px; margin-bottom: 20px;
}
.section-title {
    font-size:15px; font-weight:700; margin-bottom:18px; color:#e2e8f0;
    display:flex; align-items:center; gap:8px;
    padding-bottom:12px; border-bottom:1px solid rgba(255,255,255,0.05);
}

table { width:100%; border-collapse:collapse; font-size:13px; }
th { text-align:left; padding:12px 14px; background:rgba(0,0,0,0.2); color:#64748b; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.8px; border-bottom:1px solid rgba(255,255,255,0.06); }
td { padding:11px 14px; border-bottom:1px solid rgba(255,255,255,0.04); }
tr { transition: background .15s ease; }
tr:hover { background: rgba(245,158,11,0.04); }

.badge { display:inline-block; padding:3px 10px; border-radius:6px; font-size:11px; font-weight:700; }
.badge-long { background:rgba(16,185,129,0.12); color:#34d399; border:1px solid rgba(16,185,129,0.15); }
.badge-short { background:rgba(239,68,68,0.12); color:#f87171; border:1px solid rgba(239,68,68,0.15); }
.badge-pending { background:rgba(245,158,11,0.12); color:#fbbf24; border:1px solid rgba(245,158,11,0.15); }
.badge-active { background:rgba(59,130,246,0.12); color:#60a5fa; border:1px solid rgba(59,130,246,0.15); }
.badge-closed { background:rgba(100,116,139,0.12); color:#94a3b8; border:1px solid rgba(100,116,139,0.15); }

.btn { padding:8px 18px; border:none; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; transition:all .2s ease; }
.btn-primary { background:linear-gradient(135deg, #3b82f6, #2563eb); color:white; }
.btn-success { background:linear-gradient(135deg, #10b981, #059669); color:white; }
.btn-danger { background:linear-gradient(135deg, #ef4444, #dc2626); color:white; }
.btn-warning { background:linear-gradient(135deg, #f59e0b, #d97706); color:white; }
.btn-sm { padding:5px 12px; font-size:11px; border-radius:6px; }

.tab-bar { display:flex; gap:4px; margin-bottom:16px; }
.tab { padding:7px 18px; border-radius:8px; font-size:13px; font-weight:500; cursor:pointer; background:transparent; color:#64748b; border:none; transition:all .2s; }
.tab.active { background:rgba(245,158,11,0.12); color:#fbbf24; font-weight:600; }
.tab:hover { color:#e2e8f0; background:rgba(255,255,255,0.04); }

.config-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; }
.form-group { display:flex; flex-direction:column; gap:5px; }
.form-group label { font-size:11px; color:#94a3b8; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
.form-group input, .form-group select {
    background:rgba(0,0,0,0.25); border:1px solid rgba(255,255,255,0.08);
    color:#e2e8f0; padding:10px 14px; border-radius:10px; font-size:14px; outline:none;
}
.form-group input:focus { border-color:rgba(245,158,11,0.5); box-shadow:0 0 0 3px rgba(245,158,11,0.1); }

.chart-container { height:300px; margin-top:12px; }

.log-entry { padding:6px 12px; font-size:12px; border-bottom:1px solid rgba(255,255,255,0.03); font-family:monospace; }
.log-entry .time { color:#64748b; margin-right:8px; }
.log-entry .level-ERROR { color:#ef4444; } .log-entry .level-WARN { color:#fbbf24; } .log-entry .level-INFO { color:#94a3b8; }

@media (max-width:900px) { .stats-grid { grid-template-columns:repeat(2,1fr); } .config-grid { grid-template-columns:repeat(2,1fr); } .header { flex-direction:column; gap:14px; } }
@media (max-width:480px) { .stats-grid { grid-template-columns:1fr; } }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>MEXC Signal Trader</h1>
        <div class="header-right">
            <div id="exchangeBadge" class="exchange-badge disconnected">MEXC: 未连接</div>
            <div id="tgBadge" class="exchange-badge disconnected">TG: 未连接</div>
        </div>
    </div>

    <!-- Telegram Auth -->
    <div class="section" id="tgSection" style="padding:16px 24px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <span id="tgDot" style="width:10px;height:10px;border-radius:50%;background:#ef4444;display:inline-block;"></span>
                <span id="tgStatusText" style="font-size:14px;font-weight:600;">Telegram: 未连接</span>
                <span id="tgGroup" style="font-size:12px;color:#64748b;"></span>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <span id="tgLastMsg" style="font-size:11px;color:#475569;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                <button class="btn btn-primary btn-sm" id="tgAuthBtn" onclick="showTgAuth()">连接 Telegram</button>
            </div>
        </div>
        <div id="tgAuthPanel" style="display:none;margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,0.06);">
            <div style="display:flex;gap:10px;align-items:end;">
                <div class="form-group" style="flex:1;"><label>手机号</label><input type="text" id="tgPhone" placeholder="+8613800138000" /></div>
                <button class="btn btn-primary btn-sm" onclick="tgSendCode()">发送验证码</button>
            </div>
            <div id="tgCodePanel" style="display:none;margin-top:10px;">
                <div style="display:flex;gap:10px;align-items:end;">
                    <div class="form-group" style="flex:1;"><label>验证码</label><input type="text" id="tgCode" placeholder="12345" /></div>
                    <div class="form-group" style="flex:1;"><label>两步验证 (可选)</label><input type="password" id="tg2FA" placeholder="" /></div>
                    <button class="btn btn-success btn-sm" onclick="tgVerify()">验证</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card"><div class="label">MEXC 余额</div><div class="value yellow" id="statBalance">-</div><div class="sub" id="statBalanceSub">-</div></div>
        <div class="stat-card"><div class="label">总盈亏</div><div class="value" id="statPnl">-</div><div class="sub" id="statPnlSub">-</div></div>
        <div class="stat-card"><div class="label">胜率</div><div class="value" id="statWinRate">-</div><div class="sub" id="statWinRateSub">-</div></div>
        <div class="stat-card"><div class="label">持仓</div><div class="value blue" id="statPositions">-</div><div class="sub" id="statPositionsSub">-</div></div>
    </div>

    <!-- Open Positions -->
    <div class="section">
        <div class="section-title">当前持仓 <span id="posCount" style="color:#475569;font-size:12px;font-weight:400;"></span></div>
        <div id="positionsTable"><div style="text-align:center;padding:20px;color:#64748b;">暂无持仓</div></div>
    </div>

    <!-- Signals -->
    <div class="section">
        <div class="section-title">信号列表 (vip點位策略)</div>
        <div class="tab-bar">
            <button class="tab active" onclick="filterSignals('pending',this)">待处理</button>
            <button class="tab" onclick="filterSignals('active',this)">进行中</button>
            <button class="tab" onclick="filterSignals('closed',this)">已完成</button>
            <button class="tab" onclick="filterSignals('',this)">全部</button>
        </div>
        <div id="signalsTable"><div style="text-align:center;padding:20px;color:#64748b;">暂无信号</div></div>
    </div>

    <!-- PnL Curve -->
    <div class="section">
        <div class="section-title">资金曲线</div>
        <div class="chart-container"><canvas id="pnlChart"></canvas></div>
    </div>

    <!-- Trade History -->
    <div class="section">
        <div class="section-title">交易历史</div>
        <div id="tradesTable"><div style="text-align:center;padding:20px;color:#64748b;">暂无交易</div></div>
    </div>

    <!-- Settings -->
    <div class="section">
        <div class="section-title">设置</div>
        <div class="config-grid">
            <div class="form-group"><label>每笔保证金 (USDT)</label><input type="number" id="cfgSize" value="200" /></div>
            <div class="form-group"><label>最大持仓数</label><input type="number" id="cfgMaxPos" value="5" /></div>
            <div class="form-group"><label>默认杠杆</label><input type="number" id="cfgLev" value="10" /></div>
            <div class="form-group">
                <label>自动开单</label>
                <select id="cfgAuto"><option value="1">开启</option><option value="0">关闭</option></select>
            </div>
        </div>
        <div style="margin-top:12px;"><button class="btn btn-primary" onclick="saveConfig()">保存设置</button></div>
    </div>

    <!-- Logs -->
    <div class="section">
        <div class="section-title">操作日志</div>
        <div id="logsContainer" style="max-height:300px;overflow-y:auto;"></div>
    </div>
</div>

<script>
let pnlChart = null;
let currentFilter = 'pending';

function fmtPrice(p) { if(!p&&p!==0) return '-'; p=parseFloat(p); if(p>=1000) return p.toFixed(2); if(p>=1) return p.toFixed(4); return p.toFixed(6); }
function fmtPnl(v) { if(!v&&v!==0) return '-'; v=parseFloat(v); return '<span class="'+(v>=0?'green':'red')+'">'+(v>=0?'+':'')+v.toFixed(2)+'</span>'; }
function fmtTime(ts) { if(!ts) return '-'; if(!ts.includes('Z')&&!ts.includes('+')) ts=ts.replace(' ','T')+'Z'; const d=new Date(ts); return d.toLocaleDateString('zh-CN',{month:'2-digit',day:'2-digit'})+' '+d.toLocaleTimeString('zh-CN',{hour:'2-digit',minute:'2-digit'}); }

async function loadStats() {
    const r = await fetch('/api/stats');
    const s = await r.json();

    document.getElementById('statBalance').textContent = s.mexc_balance.toFixed(2) + 'U';
    document.getElementById('statBalanceSub').textContent = '可用: ' + s.mexc_free.toFixed(2) + 'U | 已用: ' + s.mexc_used.toFixed(2) + 'U';

    const pnlEl = document.getElementById('statPnl');
    pnlEl.textContent = (s.total_pnl>=0?'+':'') + s.total_pnl.toFixed(2) + 'U';
    pnlEl.className = 'value ' + (s.total_pnl>=0?'green':'red');
    document.getElementById('statPnlSub').innerHTML = '今日: ' + fmtPnl(s.today_pnl) + ' | 费用: ' + s.total_fees.toFixed(2) + 'U';

    const wrEl = document.getElementById('statWinRate');
    wrEl.textContent = s.win_rate + '%';
    wrEl.className = 'value ' + (s.win_rate>=50?'green':s.total_trades>0?'red':'blue');
    document.getElementById('statWinRateSub').textContent = s.win_count + '胜 / ' + s.total_trades + '笔';

    document.getElementById('statPositions').textContent = s.open_positions;
    document.getElementById('statPositionsSub').textContent = '待处理: ' + s.pending_signals;

    const eb = document.getElementById('exchangeBadge');
    if (s.exchange_connected) { eb.className='exchange-badge connected'; eb.textContent='MEXC: 已连接'; }
    else { eb.className='exchange-badge disconnected'; eb.textContent='MEXC: 未连接'; }
}

async function loadPositions() {
    const r = await fetch('/api/positions');
    const positions = await r.json();
    const el = document.getElementById('positionsTable');
    document.getElementById('posCount').textContent = positions.length>0 ? '('+positions.length+')' : '';
    if (positions.length===0) { el.innerHTML='<div style="text-align:center;padding:20px;color:#64748b;">暂无持仓</div>'; return; }
    let html = '<table><thead><tr><th>币种</th><th>方向</th><th>入场价</th><th>当前价</th><th>止损</th><th>止盈</th><th>保证金</th><th>杠杆</th><th>盈亏</th><th>操作</th></tr></thead><tbody>';
    for (const p of positions) {
        const dir = p.direction==='LONG'?'<span class="badge badge-long">LONG</span>':'<span class="badge badge-short">SHORT</span>';
        const c = (p.unrealized_pnl||0)>=0?'green':'red';
        html += '<tr><td><strong>'+p.symbol+'</strong></td><td>'+dir+'</td><td>'+fmtPrice(p.entry_price)+'</td><td>'+fmtPrice(p.current_price)+'</td><td class="red">'+fmtPrice(p.sl)+'</td><td class="green">'+fmtPrice(p.tp)+'</td><td>'+(p.position_size||0).toFixed(2)+'U</td><td>'+p.leverage+'x</td><td class="'+c+'">'+(p.unrealized_pnl>=0?'+':'')+(p.unrealized_pnl||0).toFixed(2)+'U ('+(p.unrealized_pct>=0?'+':'')+(p.unrealized_pct||0).toFixed(1)+'%)</td><td><button class="btn btn-danger btn-sm" onclick="closeSignal('+p.signal_id+')">平仓</button></td></tr>';
    }
    html += '</tbody></table>';
    el.innerHTML = html;
}

async function loadSignals() {
    const r = await fetch('/api/signals?status='+currentFilter);
    const signals = await r.json();
    const el = document.getElementById('signalsTable');
    if (signals.length===0) { el.innerHTML='<div style="text-align:center;padding:20px;color:#64748b;">暂无信号</div>'; return; }
    let html = '<table><thead><tr><th>时间</th><th>币种</th><th>方向</th><th>入场价</th><th>止损</th><th>止盈</th><th>杠杆</th><th>状态</th><th>操作</th></tr></thead><tbody>';
    for (const s of signals) {
        const dir = s.direction==='LONG'?'<span class="badge badge-long">LONG</span>':'<span class="badge badge-short">SHORT</span>';
        const st = '<span class="badge badge-'+s.status+'">'+s.status.toUpperCase()+'</span>';
        let act = '';
        if (s.status==='pending') act='<button class="btn btn-success btn-sm" onclick="activateSignal('+s.id+')">开仓</button> <button class="btn btn-warning btn-sm" onclick="cancelSignal('+s.id+')">取消</button>';
        else if (s.status==='active') act='<button class="btn btn-danger btn-sm" onclick="closeSignal('+s.id+')">平仓</button>';
        html += '<tr><td>'+fmtTime(s.signal_time||s.created_at)+'</td><td><strong>'+s.symbol+'</strong></td><td>'+dir+'</td><td>'+fmtPrice(s.entry_price)+'</td><td class="red">'+fmtPrice(s.stop_loss)+'</td><td class="green">'+fmtPrice(s.take_profit)+'</td><td>'+(s.leverage||10)+'x</td><td>'+st+'</td><td>'+act+'</td></tr>';
    }
    html += '</tbody></table>';
    el.innerHTML = html;
}

async function loadTrades() {
    const r = await fetch('/api/trades');
    const trades = await r.json();
    const el = document.getElementById('tradesTable');
    if (trades.length===0) { el.innerHTML='<div style="text-align:center;padding:20px;color:#64748b;">暂无交易</div>'; return; }
    let html = '<table><thead><tr><th>开仓时间</th><th>币种</th><th>方向</th><th>入场</th><th>平仓</th><th>杠杆</th><th>保证金</th><th>盈亏</th><th>原因</th></tr></thead><tbody>';
    for (const t of trades) {
        const dir = t.direction==='LONG'?'<span class="badge badge-long">LONG</span>':'<span class="badge badge-short">SHORT</span>';
        html += '<tr><td>'+fmtTime(t.opened_at)+'</td><td><strong>'+t.symbol+'</strong></td><td>'+dir+'</td><td>'+fmtPrice(t.entry_price)+'</td><td>'+fmtPrice(t.exit_price)+'</td><td>'+t.leverage+'x</td><td>'+(t.position_size||0).toFixed(2)+'U</td><td>'+fmtPnl(t.pnl)+'</td><td>'+(t.close_reason||t.status).toUpperCase()+'</td></tr>';
    }
    html += '</tbody></table>';
    el.innerHTML = html;
}

async function loadPnlChart() {
    const r = await fetch('/api/daily-pnl');
    const data = await r.json();
    if (!data.length) return;
    const ctx = document.getElementById('pnlChart').getContext('2d');
    if (pnlChart) pnlChart.destroy();
    pnlChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(d=>d.date),
            datasets: [{
                label: '累计盈亏 (USDT)',
                data: data.map(d=>d.cumulative_pnl||0),
                borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.1)',
                fill: true, tension: 0.3, pointRadius: 3,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#94a3b8' } } },
            scales: {
                x: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.04)' } },
                y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.04)' } }
            }
        }
    });
}

async function loadLogs() {
    const r = await fetch('/api/logs');
    const logs = await r.json();
    const el = document.getElementById('logsContainer');
    if (!logs.length) { el.innerHTML='<div style="text-align:center;padding:20px;color:#64748b;">暂无日志</div>'; return; }
    let html = '';
    for (const l of logs) {
        html += '<div class="log-entry"><span class="time">'+fmtTime(l.created_at)+'</span><span class="level-'+l.level+'">['+l.level+']</span> '+l.message+'</div>';
    }
    el.innerHTML = html;
}

async function loadConfig() {
    const r = await fetch('/api/config');
    const c = await r.json();
    document.getElementById('cfgSize').value = c.position_size_usdt || 200;
    document.getElementById('cfgMaxPos').value = c.max_positions || 5;
    document.getElementById('cfgLev').value = c.default_leverage || 10;
    document.getElementById('cfgAuto').value = c.auto_trade || '1';
}

async function saveConfig() {
    await fetch('/api/config', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({
        position_size_usdt: document.getElementById('cfgSize').value,
        max_positions: document.getElementById('cfgMaxPos').value,
        default_leverage: document.getElementById('cfgLev').value,
        auto_trade: document.getElementById('cfgAuto').value,
    })});
    alert('设置已保存');
}

function filterSignals(status, el) {
    currentFilter = status;
    document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
    if(el) el.classList.add('active');
    loadSignals();
}

async function activateSignal(id) {
    if (!confirm('确认在 MEXC 开仓？')) return;
    const r = await fetch('/api/signal/'+id+'/activate', {method:'PUT'});
    const d = await r.json();
    if (d.ok) loadAll(); else alert(d.error||'Failed');
}
async function closeSignal(id) {
    if (!confirm('确认平仓？')) return;
    const r = await fetch('/api/signal/'+id+'/close', {method:'PUT'});
    const d = await r.json();
    if (d.ok) loadAll(); else alert(d.error||'Failed');
}
async function cancelSignal(id) {
    const r = await fetch('/api/signal/'+id+'/cancel', {method:'PUT'});
    const d = await r.json();
    if (d.ok) loadSignals(); else alert(d.error||'Failed');
}

// Telegram
async function loadTgStatus() {
    const r = await fetch('/api/telegram/status');
    const s = await r.json();
    const dot = document.getElementById('tgDot');
    const txt = document.getElementById('tgStatusText');
    const badge = document.getElementById('tgBadge');
    if (s.connected && s.listening) {
        dot.style.background='#10b981'; txt.textContent='Telegram: 监听中';
        badge.className='exchange-badge connected'; badge.textContent='TG: '+s.group_name;
        document.getElementById('tgAuthBtn').style.display='none';
    } else if (s.connected) {
        dot.style.background='#fbbf24'; txt.textContent='Telegram: 已连接';
        badge.className='exchange-badge connected'; badge.textContent='TG: 已连接';
    } else {
        dot.style.background='#ef4444'; txt.textContent='Telegram: '+(s.error||'未连接');
        badge.className='exchange-badge disconnected'; badge.textContent='TG: 未连接';
    }
    if (s.last_message) document.getElementById('tgLastMsg').textContent = s.last_message;
}

function showTgAuth() { document.getElementById('tgAuthPanel').style.display = document.getElementById('tgAuthPanel').style.display==='none'?'block':'none'; }
async function tgSendCode() {
    const phone = document.getElementById('tgPhone').value.trim();
    if (!phone) return alert('请输入手机号');
    const r = await fetch('/api/telegram/auth/send-code', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({phone})});
    const d = await r.json();
    if (d.ok) document.getElementById('tgCodePanel').style.display='block';
    else alert(d.error||'Failed');
}
async function tgVerify() {
    const code = document.getElementById('tgCode').value.trim();
    const pw = document.getElementById('tg2FA').value.trim();
    const r = await fetch('/api/telegram/auth/verify', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({code, password:pw})});
    const d = await r.json();
    if (d.ok) { alert('Telegram 认证成功!'); loadTgStatus(); }
    else if (d.needs_2fa) alert('请输入两步验证密码');
    else alert(d.error||'Failed');
}

function loadAll() { loadStats(); loadPositions(); loadSignals(); loadTrades(); loadPnlChart(); loadTgStatus(); loadLogs(); }
loadAll(); loadConfig();
setInterval(()=>{ loadStats(); loadPositions(); loadTgStatus(); }, 15000);
setInterval(()=>{ loadSignals(); loadTrades(); loadLogs(); }, 30000);
setInterval(loadPnlChart, 60000);
</script>
</body>
</html>
'''

# ===== Main =====

if __name__ == '__main__':
    print("=" * 50)
    print("  MEXC Signal Trader - vip點位策略")
    print("=" * 50)

    init_db()
    print("[DB] Initialized")

    # Init MEXC
    if init_exchange():
        print("[MEXC] Exchange ready")
    else:
        print("[MEXC] Exchange not connected - set MEXC_API_KEY and MEXC_API_SECRET env vars")

    # Start position monitor
    monitor_thread = threading.Thread(target=position_monitor, daemon=True)
    monitor_thread.start()

    # Start Telegram listener
    start_telegram_listener()

    app.run(host='0.0.0.0', port=5113, debug=False)
