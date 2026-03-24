#!/usr/bin/env python3
"""
Telegram Signal Forwarder + Paper Trader - 所长VIP策略
监听「所长（VIP策略）气运加身」群消息 → 转发到「vip- trading」群 + 模拟交易
Port: 5114
"""

import os
import sys
import re
import json
import time
import sqlite3
import asyncio
import threading
import logging
from datetime import datetime, timedelta, timezone

from flask import Flask, request, jsonify, render_template_string
from telethon import TelegramClient, events
import requests

# ============================================================
# 配置
# ============================================================
PORT = 5114
DB_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'forwarder.db')
SESSION_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'tg_session')
BINANCE_FUTURES_API = 'https://fapi.binance.com'

# Telegram API (Telethon userbot)
TG_API_ID = 37356394
TG_API_HASH = '02b91c774b0ae70701daaff905cbd295'

# 监听的群名
TG_SOURCE_GROUP = '所长（VIP策略）气运加身'

# 转发目标: @tgsigl01_bot 发送到 vip- trading 群
BOT_TOKEN = '8777086789:AAHea8llTZaoXOQS95QyB4_JldSl4QoFrl0'

# ============================================================
# Logging
# ============================================================
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[logging.StreamHandler(sys.stdout)]
)
logger = logging.getLogger('forwarder')

# ============================================================
# Flask App
# ============================================================
app = Flask(__name__)

# ============================================================
# Persistent Telegram Event Loop
# ============================================================
_tg_loop = None
_tg_loop_thread = None
_tg_client = None
_listener_running = False
_listener_status = {'connected': False, 'group_found': False, 'group_name': '', 'error': '', 'last_message': ''}


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


# ============================================================
# Database
# ============================================================
def get_db():
    conn = sqlite3.connect(DB_PATH, timeout=10)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA journal_mode=WAL")
    return conn


def init_db():
    conn = get_db()
    conn.executescript("""
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT DEFAULT (datetime('now')),
            source_group TEXT,
            sender_name TEXT DEFAULT '',
            message_text TEXT,
            forwarded INTEGER DEFAULT 0,
            forward_error TEXT DEFAULT '',
            is_signal INTEGER DEFAULT 0,
            signal_id INTEGER DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS signals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT DEFAULT (datetime('now')),
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
            opened_at TEXT,
            closed_at TEXT,
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

        CREATE TABLE IF NOT EXISTS config (
            key TEXT PRIMARY KEY,
            value TEXT
        );
    """)

    # 默认配置
    defaults = {
        'target_chat_id': '-1003561694064',
        'auto_forward': '1',
        'forward_prefix': '',
        'source_group': TG_SOURCE_GROUP,
        'initial_capital': '10000',
        'position_pct': '10',
        'auto_trade': '1',
        'default_leverage': '10',
        'fee_rate': '0.05',
        'funding_rate': '0.01',
        'max_positions': '5',
    }
    for k, v in defaults.items():
        conn.execute("INSERT OR IGNORE INTO config (key, value) VALUES (?, ?)", (k, v))

    # 默认别名
    default_aliases = {
        '大餅': 'BTC', '大饼': 'BTC', '比特币': 'BTC', '比特幣': 'BTC', '餅': 'BTC', '饼': 'BTC', '比特': 'BTC',
        '以太': 'ETH', '以太坊': 'ETH', '以太币': 'ETH', '以太幣': 'ETH', '姨太': 'ETH', 'E太': 'ETH', '二餅': 'ETH', '二饼': 'ETH',
        '索拉納': 'SOL', '索拉纳': 'SOL',
        '狗狗幣': 'DOGE', '狗狗币': 'DOGE', '狗幣': 'DOGE', '狗币': 'DOGE', '狗子': 'DOGE',
        '瑞波': 'XRP',
        '幣安幣': 'BNB', '币安币': 'BNB',
        '青蛙': 'PEPE',
        '柴犬': 'SHIB',
    }
    for alias, symbol in default_aliases.items():
        conn.execute('INSERT OR IGNORE INTO symbol_aliases (alias, symbol) VALUES (?, ?)', (alias, symbol))

    conn.commit()
    conn.close()
    logger.info("Database initialized")


def get_config_value(key, default=''):
    try:
        conn = get_db()
        row = conn.execute("SELECT value FROM config WHERE key=?", (key,)).fetchone()
        conn.close()
        return row['value'] if row else default
    except Exception:
        return default


def set_config_value(key, value):
    conn = get_db()
    conn.execute("INSERT OR REPLACE INTO config (key, value) VALUES (?, ?)", (key, str(value)))
    conn.commit()
    conn.close()


def get_all_config():
    conn = get_db()
    rows = conn.execute("SELECT key, value FROM config").fetchall()
    conn.close()
    return {r['key']: r['value'] for r in rows}


# ============================================================
# Price
# ============================================================
def get_binance_price(symbol):
    try:
        sym = symbol.upper()
        if not sym.endswith('USDT'):
            sym += 'USDT'
        resp = requests.get(f'{BINANCE_FUTURES_API}/fapi/v1/ticker/price', params={'symbol': sym}, timeout=5)
        return float(resp.json()['price'])
    except Exception as e:
        logger.error(f"Price error {symbol}: {e}")
        return None


# ============================================================
# Symbol Alias
# ============================================================
_alias_cache = {}
_alias_cache_time = 0


def get_symbol_aliases():
    global _alias_cache, _alias_cache_time
    now = time.time()
    if now - _alias_cache_time < 60 and _alias_cache:
        return _alias_cache
    try:
        conn = get_db()
        rows = conn.execute('SELECT alias, symbol FROM symbol_aliases').fetchall()
        conn.close()
        _alias_cache = {r['alias']: r['symbol'] for r in rows}
        _alias_cache_time = now
    except:
        pass
    return _alias_cache


def resolve_symbol_alias(text):
    aliases = get_symbol_aliases()
    for alias in sorted(aliases.keys(), key=len, reverse=True):
        if alias in text:
            return aliases[alias], alias
    return None, None


# ============================================================
# Signal Parser
# ============================================================
def parse_signal_message(text):
    """解析信号消息，提取交易信息"""
    if not text:
        return None
    text = text.strip()

    result = {
        'symbol': None, 'direction': None, 'stop_loss': None,
        'take_profit': None, 'leverage': None, 'entry_price': None
    }

    # Step 1: 别名 (以太币 → ETH)
    alias_sym, _ = resolve_symbol_alias(text)
    if alias_sym:
        result['symbol'] = alias_sym + 'USDT' if not alias_sym.endswith('USDT') else alias_sym

    # Step 2: 英文币种 (#BTC, ETHUSDT)
    if not result['symbol']:
        sym_match = re.search(r'[#\$]?([A-Z]{2,10})(USDT|/USDT)?', text.upper())
        if sym_match:
            sym = sym_match.group(1)
            if sym not in ('USDT', 'USD', 'THE', 'FOR', 'AND', 'NOT', 'ALL', 'BUT', 'SL', 'TP'):
                result['symbol'] = sym + 'USDT' if not sym.endswith('USDT') else sym

    # 方向
    text_upper = text.upper()
    if any(w in text for w in ['做多', '輕倉多', '轻仓多', '加多', '開多', '开多', '进多', '反弹']) or \
       any(w in text_upper for w in ['LONG', 'BUY']) or \
       (re.search(r'(?<![做開开加輕轻])多(?!空)', text) and '做空' not in text):
        result['direction'] = 'LONG'
    elif any(w in text for w in ['做空', '輕倉空', '轻仓空', '加空', '開空', '开空', '进空']) or \
         any(w in text_upper for w in ['SHORT', 'SELL']) or \
         (re.search(r'(?<![做開开加輕轻])空(?!多)', text) and '做多' not in text):
        result['direction'] = 'SHORT'

    # 止损
    sl_match = re.search(r'(?:止損|止损|SL|stop.?loss|防守|停損|停损)[:\s：]*(\d+\.?\d*)', text, re.IGNORECASE)
    if sl_match:
        result['stop_loss'] = float(sl_match.group(1))

    # 止盈 - 多个目标取第一个
    tp_matches = re.findall(r'(?:止盈|TP|take.?profit|目標|目标|到價|到价)[:\s：]*(\d+\.?\d*)', text, re.IGNORECASE)
    if tp_matches:
        result['take_profit'] = float(tp_matches[0])
    else:
        # 支持 "止盈：2075-2085-2105-2125" 格式
        tp_range = re.search(r'(?:止盈|TP|目標|目标)[:\s：]*(\d+\.?\d*)(?:\s*[-~]\s*\d+\.?\d*)*', text, re.IGNORECASE)
        if tp_range:
            result['take_profit'] = float(tp_range.group(1))

    # 入场价 - 支持范围
    entry_match = re.search(r'(?:入場|入场|entry|開倉|开仓|價格|价格|price|進場|进场|市價|市价|委托|委託)[:\s：-]*(\d+\.?\d*)\s*[-~]\s*(\d+\.?\d*)', text, re.IGNORECASE)
    if entry_match:
        p1, p2 = float(entry_match.group(1)), float(entry_match.group(2))
        result['entry_price'] = round((p1 + p2) / 2, 6)
    else:
        entry_match = re.search(r'(?:入場|入场|entry|開倉|开仓|價格|价格|price|進場|进场|市價|市价|委托|委託)[:\s：-]*(\d+\.?\d*)', text, re.IGNORECASE)
        if entry_match:
            result['entry_price'] = float(entry_match.group(1))

    # "附近" pattern
    if not result['entry_price']:
        nearby_range = re.search(r'(\d+\.?\d*)\s*[-~]\s*(\d+\.?\d*)\s*附近', text)
        if nearby_range:
            p1, p2 = float(nearby_range.group(1)), float(nearby_range.group(2))
            result['entry_price'] = round((p1 + p2) / 2, 6)
        else:
            nearby_single = re.search(r'(\d+\.?\d*)\s*附近', text)
            if nearby_single:
                result['entry_price'] = float(nearby_single.group(1))

    # "委托 XXXX附近" — special for 所长 format
    if not result['entry_price']:
        delegate = re.search(r'委[托託]\s*(\d+\.?\d*)', text)
        if delegate:
            result['entry_price'] = float(delegate.group(1))

    # 杠杆
    lev_match = re.search(r'(\d+)\s*[-~]\s*(\d+)\s*[xX倍]', text)
    if lev_match:
        result['leverage'] = int(lev_match.group(2))
    else:
        lev_match = re.search(r'(\d+)\s*[xX倍]', text)
        if lev_match:
            result['leverage'] = int(lev_match.group(1))

    # 需要至少: symbol + direction + (sl or tp or entry_price)
    if result['symbol'] and result['direction'] and (result['stop_loss'] or result['take_profit'] or result['entry_price']):
        return result

    return None


# ============================================================
# Capital & Trade Logic
# ============================================================
def get_capital():
    config = get_all_config()
    initial = float(config.get('initial_capital', '10000'))
    conn = get_db()
    row = conn.execute('SELECT COALESCE(SUM(pnl), 0) as total_pnl, COALESCE(SUM(fees), 0) as total_fees FROM trades WHERE status=?', ('closed',)).fetchone()
    total_pnl = row['total_pnl']
    total_fees = row['total_fees']
    row2 = conn.execute('SELECT COALESCE(SUM(position_size), 0) as used FROM trades WHERE status=?', ('open',)).fetchone()
    used = row2['used']
    open_count = conn.execute('SELECT COUNT(*) FROM trades WHERE status=?', ('open',)).fetchone()[0]
    conn.close()
    total = initial + total_pnl - total_fees
    available = total - used
    return {'total': round(total, 2), 'available': round(available, 2), 'used': round(used, 2), 'initial': initial, 'open_count': open_count}


def auto_open_trade(signal_id):
    """自动开仓模拟交易"""
    try:
        conn = get_db()
        signal = conn.execute('SELECT * FROM signals WHERE id=?', (signal_id,)).fetchone()
        if not signal or signal['status'] != 'pending':
            conn.close()
            return False

        config = get_all_config()

        # 检查最大持仓
        cap = get_capital()
        max_pos = int(config.get('max_positions', '5'))
        if cap['open_count'] >= max_pos:
            logger.warning(f"Max positions reached ({max_pos}), skip {signal['symbol']}")
            conn.close()
            return False

        # 获取价格
        price = get_binance_price(signal['symbol'])
        if price is None:
            logger.error(f"Cannot get price for {signal['symbol']}")
            conn.close()
            return False

        entry_price = signal['entry_price'] if signal['entry_price'] else price
        leverage = signal['leverage'] or int(config.get('default_leverage', '10'))

        # 仓位大小
        pct = float(config.get('position_pct', '10'))
        position_size = min(cap['available'] * (pct / 100), cap['available'] * 0.5)
        if position_size < 10:
            logger.warning(f"Insufficient capital ({cap['available']:.0f}U)")
            conn.close()
            return False

        now = datetime.now(timezone.utc).isoformat()

        conn.execute('''
            INSERT INTO trades (signal_id, opened_at, symbol, direction, entry_price, leverage, position_size, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'open')
        ''', (signal_id, now, signal['symbol'], signal['direction'], entry_price, leverage, round(position_size, 2)))
        conn.execute('UPDATE signals SET status=?, entry_price=? WHERE id=?', ('active', entry_price, signal_id))
        conn.commit()
        conn.close()

        logger.info(f"[Trade] OPEN {signal['symbol']} {signal['direction']} @ {entry_price:.6g}, size={position_size:.0f}U, lev={leverage}x")

        # 通知
        send_trade_notification(signal, entry_price, position_size, leverage)
        return True
    except Exception as e:
        logger.error(f"Auto open error: {e}")
        try:
            conn.close()
        except:
            pass
        return False


def close_trade(trade_id, exit_price, reason):
    """平仓并计算盈亏"""
    conn = get_db()
    trade = conn.execute('SELECT * FROM trades WHERE id=?', (trade_id,)).fetchone()
    if not trade or trade['status'] != 'open':
        conn.close()
        return None

    now = datetime.now(timezone.utc).isoformat()
    entry = trade['entry_price']
    direction = trade['direction']
    leverage = trade['leverage']
    size = trade['position_size']

    # PnL
    if direction == 'LONG':
        pnl_pct = ((exit_price - entry) / entry) * leverage * 100
    else:
        pnl_pct = ((entry - exit_price) / entry) * leverage * 100
    pnl = size * (pnl_pct / 100)

    # Fees
    config = get_all_config()
    fee_rate = float(config.get('fee_rate', '0.05'))
    funding_rate = float(config.get('funding_rate', '0.01'))
    notional = size * leverage
    entry_fee = notional * (fee_rate / 100)
    exit_fee = notional * (fee_rate / 100)
    # Funding
    try:
        opened = datetime.fromisoformat(trade['opened_at'].replace('Z', '+00:00'))
        closed = datetime.fromisoformat(now.replace('Z', '+00:00'))
        hours = max((closed - opened).total_seconds() / 3600, 0)
    except:
        hours = 0
    funding_fee = notional * (funding_rate / 100) * (hours / 8)
    fees = round(entry_fee + exit_fee + funding_fee, 4)
    pnl_after_fees = pnl - fees

    conn.execute('''
        UPDATE trades SET exit_price=?, closed_at=?, pnl=?, pnl_pct=?, fees=?, close_reason=?, status='closed'
        WHERE id=?
    ''', (exit_price, now, round(pnl_after_fees, 4), round(pnl_pct, 2), fees, reason, trade_id))
    conn.execute('UPDATE signals SET status=? WHERE id=?', ('closed', trade['signal_id']))

    # Daily PnL
    today = datetime.now(timezone.utc).strftime('%Y-%m-%d')
    existing = conn.execute('SELECT * FROM daily_pnl WHERE date=?', (today,)).fetchone()
    if existing:
        conn.execute('UPDATE daily_pnl SET pnl=?, trades_count=?, win_count=? WHERE date=?',
                     (round(existing['pnl'] + pnl_after_fees, 4), existing['trades_count'] + 1,
                      existing['win_count'] + (1 if pnl_after_fees > 0 else 0), today))
    else:
        conn.execute('INSERT INTO daily_pnl (date, pnl, trades_count, win_count) VALUES (?, ?, ?, ?)',
                     (today, round(pnl_after_fees, 4), 1, 1 if pnl_after_fees > 0 else 0))

    # Cumulative
    rows = conn.execute('SELECT date, pnl FROM daily_pnl ORDER BY date').fetchall()
    cum = 0
    for r in rows:
        cum += r['pnl']
        conn.execute('UPDATE daily_pnl SET cumulative_pnl=? WHERE date=?', (round(cum, 4), r['date']))

    conn.commit()
    conn.close()

    result = {'pnl': round(pnl_after_fees, 4), 'pnl_pct': round(pnl_pct, 2), 'fees': fees, 'exit_price': exit_price}
    logger.info(f"[Trade] CLOSE {trade['symbol']} {direction} @ {exit_price:.6g} ({reason}) PnL: {pnl_after_fees:+.2f}U ({pnl_pct:+.1f}%)")

    # 通知
    threading.Thread(target=send_close_notification, args=(dict(trade), reason, result), daemon=True).start()
    return result


# ============================================================
# Position Monitor
# ============================================================
def position_monitor():
    logger.info("[Monitor] Position monitor started")
    while True:
        try:
            conn = get_db()
            open_trades = conn.execute('''
                SELECT t.*, s.stop_loss, s.take_profit
                FROM trades t JOIN signals s ON t.signal_id = s.id
                WHERE t.status = 'open'
            ''').fetchall()
            conn.close()

            for trade in open_trades:
                price = get_binance_price(trade['symbol'])
                if price is None:
                    continue

                direction = trade['direction']
                sl = trade['stop_loss']
                tp = trade['take_profit']
                close_reason = None

                if direction == 'LONG':
                    if sl and price <= sl:
                        close_reason = 'sl'
                    elif tp and price >= tp:
                        close_reason = 'tp'
                elif direction == 'SHORT':
                    if sl and price >= sl:
                        close_reason = 'sl'
                    elif tp and price <= tp:
                        close_reason = 'tp'

                if close_reason:
                    close_trade(trade['id'], price, close_reason)

        except Exception as e:
            logger.error(f"Monitor error: {e}")

        time.sleep(30)


# ============================================================
# Bot Notifications
# ============================================================
def send_bot_message(text, chat_id=None):
    if not chat_id:
        chat_id = get_config_value('target_chat_id', '')
    if not chat_id:
        return False, "target_chat_id not set"
    try:
        url = f"https://api.telegram.org/bot{BOT_TOKEN}/sendMessage"
        resp = requests.post(url, json={'chat_id': chat_id, 'text': text, 'parse_mode': 'HTML'}, timeout=10)
        data = resp.json()
        if data.get('ok'):
            return True, ''
        return False, data.get('description', 'unknown')
    except Exception as e:
        return False, str(e)


def send_trade_notification(signal, entry_price, size, leverage):
    try:
        sym = signal['symbol'].replace('USDT', '/USDT')
        dir_emoji = '🟢 做多 LONG' if signal['direction'] == 'LONG' else '🔴 做空 SHORT'
        now_str = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M UTC')

        text = f"📡 <b>所长VIP · 模拟开仓</b>\n"
        text += f"━━━━━━━━━━━━━━━\n"
        text += f"💰 货币: <b>{sym}</b>\n"
        text += f"📊 方向: <b>{dir_emoji}</b>\n"
        text += f"🎯 开仓价: <code>{entry_price}</code>\n"
        if signal['stop_loss']:
            text += f"🛑 止损: <code>{signal['stop_loss']}</code>\n"
        if signal['take_profit']:
            text += f"✅ 止盈: <code>{signal['take_profit']}</code>\n"
        text += f"⚡ 杠杆: <code>{leverage}x</code>\n"
        text += f"💵 仓位: <code>{size:.0f} USDT</code>\n"
        text += f"🕐 时间: <code>{now_str}</code>\n"
        text += f"━━━━━━━━━━━━━━━\n"

        raw = signal['raw_message'] or ''
        if raw:
            text += f"💬 原始信息:\n<blockquote>{raw[:300]}</blockquote>"

        send_bot_message(text)
    except Exception as e:
        logger.error(f"Trade notification error: {e}")


def send_close_notification(trade, reason, pnl_info):
    try:
        sym = trade['symbol'].replace('USDT', '/USDT')
        pnl = pnl_info['pnl']
        pnl_pct = pnl_info['pnl_pct']
        now_str = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M UTC')

        if reason == 'tp':
            header = '✅ <b>止盈平仓</b>'
        elif reason == 'sl':
            header = '🛑 <b>止损平仓</b>'
        else:
            header = '📤 <b>手动平仓</b>'

        pnl_emoji = '🟢' if pnl >= 0 else '🔴'
        dir_str = '做多' if trade['direction'] == 'LONG' else '做空'

        text = f"{header} · 所长VIP\n"
        text += f"━━━━━━━━━━━━━━━\n"
        text += f"💰 货币: <b>{sym}</b> ({dir_str})\n"
        text += f"🎯 开仓价: <code>{trade['entry_price']}</code>\n"
        text += f"📤 平仓价: <code>{pnl_info['exit_price']}</code>\n"
        text += f"⚡ 杠杆: <code>{trade['leverage']}x</code>\n"
        text += f"💵 仓位: <code>{trade['position_size']:.0f} USDT</code>\n"
        text += f"━━━━━━━━━━━━━━━\n"
        text += f"{pnl_emoji} 盈亏: <b>{pnl:+.2f} USDT ({pnl_pct:+.1f}%)</b>\n"
        text += f"💸 手续费: <code>{pnl_info['fees']:.2f} USDT</code>\n"
        text += f"🕐 时间: <code>{now_str}</code>\n"

        send_bot_message(text)
    except Exception as e:
        logger.error(f"Close notification error: {e}")


# ============================================================
# Message Handler (转发 + 解析信号)
# ============================================================
def forward_message(source_group, sender_name, message_text):
    """保存消息、转发、解析信号"""
    conn = get_db()
    forwarded = 0
    forward_error = ''
    is_signal = 0
    signal_id = 0

    # 转发
    auto_forward = get_config_value('auto_forward', '1')
    prefix = get_config_value('forward_prefix', '')
    if auto_forward == '1':
        forward_text = ''
        if prefix:
            forward_text = f"{prefix}\n"
        if sender_name:
            forward_text += f"👤 {sender_name}:\n"
        forward_text += message_text
        ok, err = send_bot_message(forward_text)
        forwarded = 1 if ok else 0
        forward_error = err

    # 解析信号
    parsed = parse_signal_message(message_text)
    if parsed:
        is_signal = 1
        leverage = parsed['leverage'] or int(get_config_value('default_leverage', '10'))

        conn.execute('''
            INSERT INTO signals (symbol, direction, entry_price, stop_loss, take_profit, leverage, status, raw_message)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)
        ''', (parsed['symbol'], parsed['direction'], parsed['entry_price'],
              parsed['stop_loss'], parsed['take_profit'], leverage, message_text))
        signal_id = conn.execute('SELECT last_insert_rowid()').fetchone()[0]
        conn.commit()

        logger.info(f"[Signal] {parsed['symbol']} {parsed['direction']} entry={parsed['entry_price']} sl={parsed['stop_loss']} tp={parsed['take_profit']} lev={leverage}x")

        # 自动开仓
        auto_trade = get_config_value('auto_trade', '1')
        if auto_trade == '1':
            threading.Thread(target=auto_open_trade, args=(signal_id,), daemon=True).start()

    conn.execute(
        "INSERT INTO messages (source_group, sender_name, message_text, forwarded, forward_error, is_signal, signal_id) VALUES (?, ?, ?, ?, ?, ?, ?)",
        (source_group, sender_name, message_text, forwarded, forward_error, is_signal, signal_id)
    )
    conn.commit()
    conn.close()

    _listener_status['last_message'] = f"{datetime.now().strftime('%H:%M:%S')} - {message_text[:50]}"
    return forwarded, forward_error


# ============================================================
# Telegram Listener
# ============================================================
_phone_code_hash = None


async def _start_listener():
    global _tg_client, _listener_running

    _listener_status['error'] = ''
    _listener_status['connected'] = False
    _listener_status['group_found'] = False

    try:
        _tg_client = TelegramClient(SESSION_PATH, TG_API_ID, TG_API_HASH, loop=get_tg_loop())
        await _tg_client.connect()

        if not await _tg_client.is_user_authorized():
            _listener_status['error'] = 'Not authorized'
            return

        _listener_status['connected'] = True
        me = await _tg_client.get_me()
        logger.info(f"Telegram connected as: {me.first_name} ({me.phone})")

        source_group_name = get_config_value('source_group', TG_SOURCE_GROUP)
        target_entity = None

        async for dialog in _tg_client.iter_dialogs():
            if source_group_name in (dialog.name or ''):
                target_entity = dialog.entity
                _listener_status['group_found'] = True
                _listener_status['group_name'] = dialog.name
                logger.info(f"Found source group: {dialog.name}")
                break

        if not target_entity:
            _listener_status['error'] = f'Group not found: {source_group_name}'
            return

        @_tg_client.on(events.NewMessage(chats=target_entity))
        async def handler(event):
            try:
                text = event.message.text or event.message.message or ''
                if not text.strip():
                    return
                sender = await event.get_sender()
                sender_name = ''
                if sender:
                    sender_name = getattr(sender, 'first_name', '') or ''
                    last = getattr(sender, 'last_name', '') or ''
                    if last:
                        sender_name += f' {last}'
                    username = getattr(sender, 'username', '') or ''
                    if username:
                        sender_name += f' (@{username})'
                logger.info(f"New message from {sender_name}: {text[:100]}")
                forward_message(source_group_name, sender_name, text)
            except Exception as e:
                logger.error(f"Handler error: {e}")

        _listener_running = True
        logger.info("Listener started, waiting for messages...")
        await _tg_client.run_until_disconnected()

    except Exception as e:
        _listener_status['error'] = str(e)
        logger.error(f"Listener error: {e}")
    finally:
        _listener_running = False


def start_telegram_listener():
    loop = get_tg_loop()
    future = asyncio.run_coroutine_threadsafe(_start_listener(), loop)

    def _on_done(f):
        try:
            f.result()
        except Exception as e:
            logger.error(f"Listener future error: {e}")
            _listener_status['error'] = str(e)

    future.add_done_callback(_on_done)


def stop_telegram_listener():
    global _tg_client, _listener_running
    if _tg_client:
        loop = get_tg_loop()
        asyncio.run_coroutine_threadsafe(_tg_client.disconnect(), loop)
        _listener_running = False
        _listener_status['connected'] = False


# ============================================================
# API Routes
# ============================================================

@app.route('/api/telegram/status')
def api_tg_status():
    return jsonify({
        'session_exists': os.path.exists(SESSION_PATH + '.session'),
        'listener_running': _listener_running,
        **_listener_status
    })


@app.route('/api/telegram/auth/send-code', methods=['POST'])
def api_tg_send_code():
    global _phone_code_hash
    phone = request.json.get('phone', '')
    if not phone:
        return jsonify({'error': 'phone required'}), 400

    async def _send():
        global _phone_code_hash, _tg_client
        client = TelegramClient(SESSION_PATH, TG_API_ID, TG_API_HASH, loop=get_tg_loop())
        await client.connect()
        result = await client.send_code_request(phone)
        _phone_code_hash = result.phone_code_hash
        _tg_client = client
        return True

    loop = get_tg_loop()
    future = asyncio.run_coroutine_threadsafe(_send(), loop)
    try:
        future.result(timeout=30)
        return jsonify({'ok': True, 'message': 'Code sent'})
    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/api/telegram/auth/verify', methods=['POST'])
def api_tg_verify():
    global _phone_code_hash
    phone = request.json.get('phone', '')
    code = request.json.get('code', '')
    password = request.json.get('password', '')
    if not phone or not code:
        return jsonify({'error': 'phone and code required'}), 400

    async def _verify():
        global _tg_client, _phone_code_hash
        if not _tg_client:
            return False, 'No client'
        try:
            await _tg_client.sign_in(phone, code, phone_code_hash=_phone_code_hash)
        except Exception as e:
            if 'Two-step' in str(e) or 'password' in str(e).lower():
                if password:
                    await _tg_client.sign_in(password=password)
                else:
                    return False, 'Two-step verification required'
            else:
                raise
        _phone_code_hash = None
        return True, 'OK'

    loop = get_tg_loop()
    future = asyncio.run_coroutine_threadsafe(_verify(), loop)
    try:
        ok, msg = future.result(timeout=30)
        if ok:
            start_telegram_listener()
            return jsonify({'ok': True, 'message': 'Authenticated'})
        return jsonify({'error': msg}), 400
    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/api/telegram/restart', methods=['POST'])
def api_tg_restart():
    stop_telegram_listener()
    time.sleep(1)
    start_telegram_listener()
    return jsonify({'ok': True})


@app.route('/api/stats')
def api_stats():
    cap = get_capital()
    config = get_all_config()
    conn = get_db()
    msg_total = conn.execute("SELECT COUNT(*) FROM messages").fetchone()[0]
    msg_fwd = conn.execute("SELECT COUNT(*) FROM messages WHERE forwarded=1").fetchone()[0]
    today = datetime.now().strftime('%Y-%m-%d')
    msg_today = conn.execute("SELECT COUNT(*) FROM messages WHERE created_at LIKE ?", (f'{today}%',)).fetchone()[0]
    closed = conn.execute('SELECT COUNT(*) as cnt, COALESCE(SUM(pnl),0) as total_pnl, COALESCE(SUM(fees),0) as total_fees FROM trades WHERE status=?', ('closed',)).fetchone()
    wins = conn.execute('SELECT COUNT(*) FROM trades WHERE status=? AND pnl > 0', ('closed',)).fetchone()[0]
    today_pnl_row = conn.execute('SELECT COALESCE(pnl,0) as pnl FROM daily_pnl WHERE date=?', (today,)).fetchone()
    signals_total = conn.execute('SELECT COUNT(*) FROM signals').fetchone()[0]
    conn.close()

    total_trades = closed['cnt']
    win_rate = round(wins / total_trades * 100, 1) if total_trades > 0 else 0

    return jsonify({
        'msg_total': msg_total, 'msg_forwarded': msg_fwd, 'msg_today': msg_today,
        'listener_running': _listener_running,
        'group_name': _listener_status.get('group_name', ''),
        'total_capital': cap['total'], 'available': cap['available'],
        'initial_capital': cap['initial'],
        'total_pnl': round(closed['total_pnl'], 2),
        'total_fees': round(closed['total_fees'], 2),
        'pnl_pct': round(closed['total_pnl'] / cap['initial'] * 100, 2) if cap['initial'] > 0 else 0,
        'total_trades': total_trades, 'win_count': wins, 'win_rate': win_rate,
        'open_positions': cap['open_count'],
        'today_pnl': round(today_pnl_row['pnl'], 2) if today_pnl_row else 0,
        'signals_total': signals_total,
    })


@app.route('/api/messages')
def api_messages():
    page = request.args.get('page', 1, type=int)
    per_page = request.args.get('per_page', 50, type=int)
    offset = (page - 1) * per_page
    conn = get_db()
    total = conn.execute("SELECT COUNT(*) FROM messages").fetchone()[0]
    rows = conn.execute("SELECT * FROM messages ORDER BY id DESC LIMIT ? OFFSET ?", (per_page, offset)).fetchall()
    conn.close()
    return jsonify({'total': total, 'page': page, 'per_page': per_page, 'messages': [dict(r) for r in rows]})


@app.route('/api/signals')
def api_signals():
    conn = get_db()
    rows = conn.execute('SELECT * FROM signals ORDER BY id DESC LIMIT 200').fetchall()
    conn.close()
    return jsonify([dict(r) for r in rows])


@app.route('/api/trades')
def api_trades():
    conn = get_db()
    rows = conn.execute('''
        SELECT t.*, s.stop_loss as sl, s.take_profit as tp, s.raw_message
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
        price = get_binance_price(t['symbol'])
        if price:
            entry = t['entry_price']
            lev = t['leverage']
            if t['direction'] == 'LONG':
                upnl_pct = ((price - entry) / entry) * lev * 100
            else:
                upnl_pct = ((entry - price) / entry) * lev * 100
            td['current_price'] = price
            td['unrealized_pnl'] = round(t['position_size'] * (upnl_pct / 100), 2)
            td['unrealized_pct'] = round(upnl_pct, 2)
        result.append(td)
    return jsonify(result)


@app.route('/api/daily-pnl')
def api_daily_pnl():
    conn = get_db()
    rows = conn.execute('SELECT * FROM daily_pnl ORDER BY date').fetchall()
    conn.close()
    return jsonify([dict(r) for r in rows])


@app.route('/api/config', methods=['GET', 'PUT'])
def api_config():
    if request.method == 'GET':
        return jsonify(get_all_config())
    data = request.json or {}
    for k, v in data.items():
        set_config_value(k, v)
    return jsonify({'ok': True})


@app.route('/api/signal/<int:sid>/close', methods=['PUT'])
def api_close_signal(sid):
    conn = get_db()
    trade = conn.execute('SELECT * FROM trades WHERE signal_id=? AND status=?', (sid, 'open')).fetchone()
    if not trade:
        conn.close()
        return jsonify({'error': 'No open trade'}), 404
    conn.close()
    price = get_binance_price(trade['symbol'])
    if not price:
        return jsonify({'error': 'Cannot get price'}), 500
    result = close_trade(trade['id'], price, 'manual')
    return jsonify({'ok': True, **result})


@app.route('/api/signal/<int:sid>/cancel', methods=['PUT'])
def api_cancel_signal(sid):
    conn = get_db()
    conn.execute('UPDATE signals SET status=? WHERE id=? AND status=?', ('cancelled', sid, 'pending'))
    conn.commit()
    conn.close()
    return jsonify({'ok': True})


@app.route('/api/aliases', methods=['GET'])
def api_aliases():
    conn = get_db()
    rows = conn.execute('SELECT alias, symbol FROM symbol_aliases ORDER BY symbol, alias').fetchall()
    conn.close()
    return jsonify([dict(r) for r in rows])


@app.route('/api/aliases', methods=['POST'])
def api_add_alias():
    data = request.json
    alias = (data.get('alias') or '').strip()
    symbol = (data.get('symbol') or '').strip().upper()
    if not alias or not symbol:
        return jsonify({'error': 'alias and symbol required'}), 400
    conn = get_db()
    conn.execute('INSERT OR REPLACE INTO symbol_aliases (alias, symbol) VALUES (?, ?)', (alias, symbol))
    conn.commit()
    conn.close()
    global _alias_cache_time
    _alias_cache_time = 0
    return jsonify({'ok': True})


@app.route('/api/forward/test', methods=['POST'])
def api_forward_test():
    text = request.json.get('text', 'Test from forwarder')
    ok, err = send_bot_message(text)
    return jsonify({'ok': ok, 'error': err})


# ============================================================
# Dashboard HTML
# ============================================================
DASHBOARD_HTML = """
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>所长VIP - 信号转发 + 模拟交易</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: -apple-system, 'Segoe UI', sans-serif; background: linear-gradient(135deg, #0c1222 0%, #1a1a3e 50%, #162036 100%); color: #e0e0e0; min-height:100vh; }
.container { max-width:1200px; margin:0 auto; padding:20px; }
h1 { text-align:center; font-size:1.6em; margin-bottom:4px; background: linear-gradient(90deg, #60a5fa, #a78bfa); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.subtitle { text-align:center; color:#888; font-size:0.82em; margin-bottom:16px; }
.stats { display:grid; grid-template-columns: repeat(6,1fr); gap:10px; margin-bottom:16px; }
.stat-card { background: rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:12px; padding:14px 10px; text-align:center; }
.stat-card .value { font-size:1.5em; font-weight:700; color:#60a5fa; }
.stat-card .label { font-size:0.75em; color:#888; margin-top:2px; }
.stat-card.green .value { color:#34d399; }
.stat-card.red .value { color:#f87171; }
.stat-card.yellow .value { color:#fbbf24; }
.section { background: rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:16px; margin-bottom:12px; }
.section h2 { font-size:1em; margin-bottom:10px; color:#a78bfa; }
.tabs { display:flex; gap:6px; margin-bottom:12px; }
.tab { padding:6px 14px; border-radius:8px; font-size:0.82em; cursor:pointer; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#aaa; }
.tab.active { background:rgba(99,102,241,0.3); border-color:#6366f1; color:#fff; }
.badge { padding:3px 8px; border-radius:5px; font-size:0.75em; font-weight:600; }
.badge.green { background:rgba(52,211,153,0.2); color:#34d399; }
.badge.red { background:rgba(248,113,113,0.2); color:#f87171; }
.badge.yellow { background:rgba(251,191,36,0.2); color:#fbbf24; }
.badge.blue { background:rgba(96,165,250,0.2); color:#60a5fa; }
input,button,select { font-family:inherit; font-size:0.85em; }
input[type=text],input[type=password],input[type=number] { background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); color:#e0e0e0; padding:6px 10px; border-radius:6px; width:180px; }
button { padding:6px 14px; border:none; border-radius:6px; cursor:pointer; font-weight:600; }
.btn-primary { background:linear-gradient(135deg,#6366f1,#8b5cf6); color:white; }
.btn-danger { background:linear-gradient(135deg,#ef4444,#dc2626); color:white; }
.btn-success { background:linear-gradient(135deg,#10b981,#059669); color:white; }
.btn-sm { padding:4px 10px; font-size:0.78em; }
table { width:100%; border-collapse:collapse; font-size:0.82em; }
th { text-align:left; padding:8px 6px; color:#888; border-bottom:1px solid rgba(255,255,255,0.1); }
td { padding:8px 6px; border-bottom:1px solid rgba(255,255,255,0.05); }
tr:hover { background:rgba(255,255,255,0.03); }
.pnl-pos { color:#34d399; } .pnl-neg { color:#f87171; }
.flex-gap { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.config-grid { display:grid; grid-template-columns:1fr 2fr; gap:6px; align-items:center; max-width:500px; }
.config-grid label { color:#aaa; font-size:0.82em; }
#authSection { display:none; }
.hidden { display:none; }
@media(max-width:800px) { .stats { grid-template-columns:repeat(3,1fr); } }
@media(max-width:500px) { .stats { grid-template-columns:repeat(2,1fr); } }
</style>
</head>
<body>
<div class="container">
<h1>📡 所长VIP策略 · 模拟交易</h1>
<p class="subtitle">转发 + 自动解析信号 + 模拟开仓 · 本金 10,000U</p>

<div class="stats">
    <div class="stat-card" id="sCapital"><div class="value">-</div><div class="label">总资产 (U)</div></div>
    <div class="stat-card" id="sPnl"><div class="value">-</div><div class="label">总盈亏</div></div>
    <div class="stat-card" id="sToday"><div class="value">-</div><div class="label">今日盈亏</div></div>
    <div class="stat-card" id="sWinRate"><div class="value">-</div><div class="label">胜率</div></div>
    <div class="stat-card" id="sPositions"><div class="value">-</div><div class="label">持仓数</div></div>
    <div class="stat-card" id="sStatus"><div class="value">-</div><div class="label">监听状态</div></div>
</div>

<!-- Tabs -->
<div class="tabs">
    <div class="tab active" onclick="switchTab('positions')">持仓</div>
    <div class="tab" onclick="switchTab('trades')">交易记录</div>
    <div class="tab" onclick="switchTab('signals')">信号</div>
    <div class="tab" onclick="switchTab('messages')">消息</div>
    <div class="tab" onclick="switchTab('config')">设置</div>
</div>

<!-- Positions -->
<div class="section tab-content" id="tab-positions">
    <h2>📊 当前持仓</h2>
    <table>
        <thead><tr><th>币种</th><th>方向</th><th>开仓价</th><th>现价</th><th>杠杆</th><th>仓位</th><th>未实现盈亏</th><th>SL/TP</th><th>操作</th></tr></thead>
        <tbody id="posBody"><tr><td colspan="9" style="color:#888">加载中...</td></tr></tbody>
    </table>
</div>

<!-- Trades -->
<div class="section tab-content hidden" id="tab-trades">
    <h2>📋 交易记录</h2>
    <table>
        <thead><tr><th>时间</th><th>币种</th><th>方向</th><th>开仓</th><th>平仓</th><th>杠杆</th><th>仓位</th><th>盈亏</th><th>原因</th></tr></thead>
        <tbody id="tradeBody"><tr><td colspan="9" style="color:#888">加载中...</td></tr></tbody>
    </table>
</div>

<!-- Signals -->
<div class="section tab-content hidden" id="tab-signals">
    <h2>📡 信号列表</h2>
    <table>
        <thead><tr><th>时间</th><th>币种</th><th>方向</th><th>入场</th><th>止损</th><th>止盈</th><th>杠杆</th><th>状态</th><th>操作</th></tr></thead>
        <tbody id="sigBody"><tr><td colspan="9" style="color:#888">加载中...</td></tr></tbody>
    </table>
</div>

<!-- Messages -->
<div class="section tab-content hidden" id="tab-messages">
    <h2>💬 消息记录</h2>
    <table>
        <thead><tr><th>时间</th><th>发送者</th><th>消息</th><th>转发</th><th>信号</th></tr></thead>
        <tbody id="msgBody"><tr><td colspan="5" style="color:#888">加载中...</td></tr></tbody>
    </table>
    <div class="flex-gap" style="margin-top:8px;justify-content:center;">
        <button class="btn-sm btn-primary" onclick="loadMessages(msgPage-1)">上一页</button>
        <span id="pageInfo" style="font-size:0.82em;color:#888;">1/1</span>
        <button class="btn-sm btn-primary" onclick="loadMessages(msgPage+1)">下一页</button>
    </div>
</div>

<!-- Config -->
<div class="section tab-content hidden" id="tab-config">
    <h2>⚙️ 设置</h2>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div>
            <h3 style="font-size:0.9em;color:#60a5fa;margin-bottom:8px;">Telegram</h3>
            <div class="flex-gap" id="tgStatusRow" style="margin-bottom:8px;"><span>加载中...</span></div>
            <div class="flex-gap">
                <button class="btn-primary btn-sm" onclick="showAuth()">认证</button>
                <button class="btn-success btn-sm" onclick="restartListener()">重启监听</button>
            </div>
            <div id="authSection" style="margin-top:8px;">
                <div class="flex-gap"><input type="text" id="phone" placeholder="+86..."><button class="btn-primary btn-sm" onclick="sendCode()">发送验证码</button></div>
                <div class="flex-gap" style="margin-top:6px;"><input type="text" id="code" placeholder="验证码"><input type="password" id="pwd" placeholder="两步密码(可选)"><button class="btn-success btn-sm" onclick="verifyCode()">验证</button></div>
                <div id="authMsg" style="margin-top:4px;font-size:0.82em;"></div>
            </div>
            <div class="config-grid" style="margin-top:12px;">
                <label>目标群 Chat ID:</label><input type="text" id="cfgTarget">
                <label>自动转发:</label><label><input type="checkbox" id="cfgAutoFwd"> 开启</label>
                <label>消息前缀:</label><input type="text" id="cfgPrefix">
                <label>监听群名:</label><input type="text" id="cfgSource">
            </div>
        </div>
        <div>
            <h3 style="font-size:0.9em;color:#60a5fa;margin-bottom:8px;">模拟交易</h3>
            <div class="config-grid">
                <label>初始本金 (U):</label><input type="number" id="cfgCapital">
                <label>仓位比例 (%):</label><input type="number" id="cfgPosPct">
                <label>默认杠杆:</label><input type="number" id="cfgLeverage">
                <label>最大持仓:</label><input type="number" id="cfgMaxPos">
                <label>手续费率 (%):</label><input type="number" id="cfgFeeRate" step="0.01">
                <label>自动开仓:</label><label><input type="checkbox" id="cfgAutoTrade"> 开启</label>
            </div>
        </div>
    </div>
    <div style="margin-top:12px;"><button class="btn-primary" onclick="saveConfig()">保存配置</button>
    <button class="btn-primary btn-sm" onclick="testForward()" style="margin-left:8px;">测试转发</button>
    <span id="testResult" style="margin-left:6px;font-size:0.82em;"></span></div>
</div>
</div>

<script>
let msgPage = 1;

function api(method, url, data) {
    const opts = { method, headers: {'Content-Type':'application/json'} };
    if (data) opts.body = JSON.stringify(data);
    return fetch(url, opts).then(r => r.json());
}

function switchTab(name) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name).classList.remove('hidden');
    event.target.classList.add('active');
    if (name === 'positions') loadPositions();
    if (name === 'trades') loadTrades();
    if (name === 'signals') loadSignals();
    if (name === 'messages') loadMessages(1);
    if (name === 'config') { loadConfig(); refreshTgStatus(); }
}

function refreshStats() {
    api('GET', '/api/stats').then(d => {
        qs('#sCapital .value').textContent = d.total_capital.toLocaleString();
        const pnlEl = qs('#sPnl .value');
        pnlEl.textContent = (d.total_pnl >= 0 ? '+' : '') + d.total_pnl.toFixed(1);
        qs('#sPnl').className = 'stat-card ' + (d.total_pnl >= 0 ? 'green' : 'red');
        const todayEl = qs('#sToday .value');
        todayEl.textContent = (d.today_pnl >= 0 ? '+' : '') + d.today_pnl.toFixed(1);
        qs('#sToday').className = 'stat-card ' + (d.today_pnl >= 0 ? 'green' : 'red');
        qs('#sWinRate .value').textContent = d.total_trades > 0 ? d.win_rate + '%' : '-';
        qs('#sPositions .value').textContent = d.open_positions;
        qs('#sPositions').className = 'stat-card' + (d.open_positions > 0 ? ' yellow' : '');
        qs('#sStatus .value').textContent = d.listener_running ? '运行中' : '未运行';
        qs('#sStatus').className = 'stat-card ' + (d.listener_running ? 'green' : 'red');
    });
}

function qs(sel) { return document.querySelector(sel); }

function loadPositions() {
    api('GET', '/api/positions').then(data => {
        const tb = document.getElementById('posBody');
        if (!data.length) { tb.innerHTML = '<tr><td colspan="9" style="color:#888;">暂无持仓</td></tr>'; return; }
        tb.innerHTML = data.map(t => {
            const dir = t.direction === 'LONG' ? '<span style="color:#34d399">🟢 多</span>' : '<span style="color:#f87171">🔴 空</span>';
            const upnl = t.unrealized_pnl || 0;
            const upct = t.unrealized_pct || 0;
            const cls = upnl >= 0 ? 'pnl-pos' : 'pnl-neg';
            return '<tr><td>' + t.symbol.replace('USDT','') + '</td><td>' + dir + '</td><td>' + t.entry_price + '</td><td>' + (t.current_price||'-') + '</td><td>' + t.leverage + 'x</td><td>' + t.position_size + '</td><td class="' + cls + '">' + upnl.toFixed(1) + ' (' + upct.toFixed(1) + '%)</td><td>' + (t.sl||'-') + ' / ' + (t.tp||'-') + '</td><td><button class="btn-danger btn-sm" onclick="closePos(' + t.signal_id + ')">平仓</button></td></tr>';
        }).join('');
    });
}

function loadTrades() {
    api('GET', '/api/trades').then(data => {
        const tb = document.getElementById('tradeBody');
        if (!data.length) { tb.innerHTML = '<tr><td colspan="9" style="color:#888;">暂无交易</td></tr>'; return; }
        tb.innerHTML = data.map(t => {
            const time = (t.opened_at||'').substring(5,16);
            const dir = t.direction === 'LONG' ? '🟢多' : '🔴空';
            const pnl = t.pnl || 0;
            const cls = pnl >= 0 ? 'pnl-pos' : 'pnl-neg';
            const reason = {'tp':'止盈','sl':'止损','manual':'手动'}[t.close_reason] || t.status;
            return '<tr><td>' + time + '</td><td>' + t.symbol.replace('USDT','') + '</td><td>' + dir + '</td><td>' + t.entry_price + '</td><td>' + (t.exit_price||'-') + '</td><td>' + t.leverage + 'x</td><td>' + t.position_size + '</td><td class="' + cls + '">' + pnl.toFixed(1) + ' (' + (t.pnl_pct||0).toFixed(1) + '%)</td><td>' + reason + '</td></tr>';
        }).join('');
    });
}

function loadSignals() {
    api('GET', '/api/signals').then(data => {
        const tb = document.getElementById('sigBody');
        if (!data.length) { tb.innerHTML = '<tr><td colspan="9" style="color:#888;">暂无信号</td></tr>'; return; }
        tb.innerHTML = data.map(s => {
            const time = (s.created_at||'').substring(5,16);
            const dir = s.direction === 'LONG' ? '🟢多' : '🔴空';
            const st = {'pending':'<span class="badge yellow">待处理</span>','active':'<span class="badge green">持仓中</span>','closed':'<span class="badge blue">已平仓</span>','cancelled':'<span class="badge red">已取消</span>'}[s.status] || s.status;
            let actions = '';
            if (s.status === 'active') actions = '<button class="btn-danger btn-sm" onclick="closePos(' + s.id + ')">平仓</button>';
            if (s.status === 'pending') actions = '<button class="btn-danger btn-sm" onclick="cancelSig(' + s.id + ')">取消</button>';
            return '<tr><td>' + time + '</td><td>' + s.symbol.replace('USDT','') + '</td><td>' + dir + '</td><td>' + (s.entry_price||'-') + '</td><td>' + (s.stop_loss||'-') + '</td><td>' + (s.take_profit||'-') + '</td><td>' + s.leverage + 'x</td><td>' + st + '</td><td>' + actions + '</td></tr>';
        }).join('');
    });
}

function loadMessages(page) {
    if (page < 1) return;
    api('GET', '/api/messages?page=' + page + '&per_page=30').then(d => {
        msgPage = d.page;
        const tp = Math.ceil(d.total / d.per_page) || 1;
        document.getElementById('pageInfo').textContent = msgPage + '/' + tp;
        const tb = document.getElementById('msgBody');
        if (!d.messages.length) { tb.innerHTML = '<tr><td colspan="5" style="color:#888;">暂无消息</td></tr>'; return; }
        tb.innerHTML = d.messages.map(m => {
            const time = m.created_at ? m.created_at.substring(5,16) : '';
            const fwd = m.forwarded ? '<span class="badge green">✓</span>' : '<span class="badge red">✗</span>';
            const sig = m.is_signal ? '<span class="badge blue">信号#' + m.signal_id + '</span>' : '';
            const text = (m.message_text||'').substring(0,150).replace(/</g,'&lt;');
            return '<tr><td>' + time + '</td><td>' + (m.sender_name||'').replace(/</g,'&lt;').substring(0,15) + '</td><td style="max-width:400px;word-break:break-all;white-space:pre-wrap;">' + text + '</td><td>' + fwd + '</td><td>' + sig + '</td></tr>';
        }).join('');
    });
}

function closePos(sid) { if(confirm('确认平仓?')) api('PUT', '/api/signal/' + sid + '/close').then(d => { if(d.ok) { loadPositions(); loadTrades(); refreshStats(); } else alert(d.error); }); }
function cancelSig(sid) { api('PUT', '/api/signal/' + sid + '/cancel').then(() => loadSignals()); }

function loadConfig() {
    api('GET', '/api/config').then(d => {
        document.getElementById('cfgTarget').value = d.target_chat_id || '';
        document.getElementById('cfgAutoFwd').checked = d.auto_forward === '1';
        document.getElementById('cfgPrefix').value = d.forward_prefix || '';
        document.getElementById('cfgSource').value = d.source_group || '';
        document.getElementById('cfgCapital').value = d.initial_capital || '10000';
        document.getElementById('cfgPosPct').value = d.position_pct || '10';
        document.getElementById('cfgLeverage').value = d.default_leverage || '10';
        document.getElementById('cfgMaxPos').value = d.max_positions || '5';
        document.getElementById('cfgFeeRate').value = d.fee_rate || '0.05';
        document.getElementById('cfgAutoTrade').checked = d.auto_trade === '1';
    });
}

function saveConfig() {
    api('PUT', '/api/config', {
        target_chat_id: document.getElementById('cfgTarget').value,
        auto_forward: document.getElementById('cfgAutoFwd').checked ? '1' : '0',
        forward_prefix: document.getElementById('cfgPrefix').value,
        source_group: document.getElementById('cfgSource').value,
        initial_capital: document.getElementById('cfgCapital').value,
        position_pct: document.getElementById('cfgPosPct').value,
        default_leverage: document.getElementById('cfgLeverage').value,
        max_positions: document.getElementById('cfgMaxPos').value,
        fee_rate: document.getElementById('cfgFeeRate').value,
        auto_trade: document.getElementById('cfgAutoTrade').checked ? '1' : '0',
    }).then(() => { refreshStats(); alert('已保存'); });
}

function refreshTgStatus() {
    api('GET', '/api/telegram/status').then(d => {
        const row = document.getElementById('tgStatusRow');
        let html = '';
        if (d.listener_running && d.connected) {
            html += '<span class="badge green">已连接</span>';
            if (d.group_found) html += '<span class="badge green">' + d.group_name + '</span>';
        } else if (d.session_exists) {
            html += '<span class="badge yellow">有Session</span>';
            if (d.error) html += '<span class="badge red">' + d.error + '</span>';
        } else {
            html += '<span class="badge red">未认证</span>';
        }
        row.innerHTML = html;
    });
}

function showAuth() { const s = document.getElementById('authSection'); s.style.display = s.style.display === 'none' ? 'block' : 'none'; }
function sendCode() { const p = document.getElementById('phone').value; document.getElementById('authMsg').textContent = '...'; api('POST', '/api/telegram/auth/send-code', {phone:p}).then(d => document.getElementById('authMsg').textContent = d.ok ? '✅ 已发送' : '❌ ' + d.error); }
function verifyCode() { api('POST', '/api/telegram/auth/verify', {phone:document.getElementById('phone').value, code:document.getElementById('code').value, password:document.getElementById('pwd').value}).then(d => { document.getElementById('authMsg').textContent = d.ok ? '✅ 认证成功' : '❌ ' + (d.error||''); if(d.ok) setTimeout(()=>{refreshTgStatus();refreshStats();},3000); }); }
function restartListener() { api('POST', '/api/telegram/restart').then(d => { alert(d.ok ? '已重启' : d.error); setTimeout(()=>{refreshTgStatus();refreshStats();},3000); }); }
function testForward() { document.getElementById('testResult').textContent = '...'; api('POST', '/api/forward/test', {text:'🧪 测试 ' + new Date().toLocaleTimeString()}).then(d => document.getElementById('testResult').textContent = d.ok ? '✅' : '❌ ' + d.error); }

// Init
refreshStats();
loadPositions();
setInterval(refreshStats, 30000);
setInterval(loadPositions, 30000);
</script>
</body>
</html>
"""


@app.route('/')
def dashboard():
    return render_template_string(DASHBOARD_HTML)


# ============================================================
# Main
# ============================================================
if __name__ == '__main__':
    init_db()
    logger.info(f"Starting TG Signal Forwarder + Paper Trader on port {PORT}")

    # 启动持仓监控
    monitor_thread = threading.Thread(target=position_monitor, daemon=True)
    monitor_thread.start()

    # 自动启动监听
    if os.path.exists(SESSION_PATH + '.session'):
        logger.info("Session found, starting listener...")
        start_telegram_listener()

    app.run(host='0.0.0.0', port=PORT, debug=False)
