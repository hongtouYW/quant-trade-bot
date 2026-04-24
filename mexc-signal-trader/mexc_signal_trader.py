#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Signal Trader - 抓取飞机群 vip點位策略 信号，自动在 Bitget 合约开单
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
import hmac
import hashlib
from datetime import datetime, timedelta, timezone

app = Flask(__name__)

DB_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'mexc_signals.db')

# ===== Bitget Config =====
EXCHANGE_API_KEY = os.environ.get('EXCHANGE_API_KEY', 'bg_1d2f0f0c862ab0bc2a1bce9e0f910c22')
EXCHANGE_API_SECRET = os.environ.get('EXCHANGE_API_SECRET', '7e2f272689659ebca18fda1ab054b3b9bd89594b7c61ff163a9ac02315b742a8')
EXCHANGE_PASSWORD = os.environ.get('EXCHANGE_PASSWORD', 'qweqweqweqwe')
EXCHANGE_NAME = 'bitget'
MAX_LEVERAGE = 25  # 基于回测优化: 29-50x 全亏 (-2689U), 20x 赚 (+970U)
MIN_LEVERAGE = 20
ETH_MAX_LEVERAGE = 15  # ETH 单独降杠杆 (ETH 做空亏 -1074U 最大亏损源)

# ===== Paper Trading Mode =====
PAPER_TRADING = True
PAPER_INITIAL_CAPITAL = 10000

# ===== Telegram Listener Config (Telethon user client) =====
TG_API_ID = 37356394
TG_API_HASH = '02b91c774b0ae70701daaff905cbd295'
TG_GROUPS = {
    'LUKE加密集中营策略群': {'priority': 1, 'parser': 'luke', 'notify_chat_id': '-5294992522'},
    # 'vip點位策略': {'priority': 2, 'parser': 'vip'},      # 暂停
    # 'Sias加密起飛': {'priority': 3, 'parser': 'sias'},     # 暂停
}
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

exchange = None  # ccxt Binance futures instance

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

# ===== Binance Exchange =====

def init_exchange():
    """Initialize exchange. Paper mode: skip API connection."""
    global exchange
    if PAPER_TRADING:
        exchange = True
        print(f"[Paper] Paper trading mode! Virtual capital: {PAPER_INITIAL_CAPITAL}U", flush=True)
        return True
    if not EXCHANGE_API_KEY or not EXCHANGE_API_SECRET:
        print("[Bitget] WARNING: API keys not set!", flush=True)
        return False
    try:
        exchange = ccxt.bitget({
            'apiKey': EXCHANGE_API_KEY,
            'secret': EXCHANGE_API_SECRET,
            'password': EXCHANGE_PASSWORD,
            'options': {'defaultType': 'swap'},
            'enableRateLimit': True,
        })
        exchange.load_markets()
        bal = exchange.fetch_balance()
        usdt = bal.get('USDT', {})
        total = usdt.get('total', 0)
        free = usdt.get('free', 0)
        print(f"[Bitget] Connected! USDT: total={total} free={free}", flush=True)
        return True
    except Exception as e:
        print(f"[Bitget] Init failed: {e}", flush=True)
        exchange = None
        return False

def get_exchange_price(symbol):
    """Get current price via ccxt or Binance fallback"""
    try:
        sym = symbol.upper().replace('/','').replace(':','').replace('_','')
        if not sym.endswith('USDT'):
            sym = sym + 'USDT'
        base = sym.replace('USDT', '')
        if exchange and not PAPER_TRADING:
            ticker = exchange.fetch_ticker(f'{base}/USDT:USDT')
            return float(ticker['last'])
        # Bitget REST API (primary for paper trading)
        try:
            resp2 = requests.get(f'https://api.bitget.com/api/v2/mix/market/ticker?productType=USDT-FUTURES&symbol={sym}', timeout=5)
            data2 = resp2.json()
            if data2.get('data') and data2['data'].get('lastPr'):
                return float(data2['data']['lastPr'])
        except:
            pass
        # Binance fallback
        try:
            resp = requests.get(f'https://api.binance.com/api/v3/ticker/price', params={'symbol': sym}, timeout=5)
            data = resp.json()
            if data.get('price'):
                return float(data['price'])
        except:
            pass
        # MEXC fallback
        try:
            resp3 = requests.get(f'https://api.mexc.com/api/v3/ticker/price?symbol={sym}', timeout=5)
            data3 = resp3.json()
            if data3.get('price'):
                return float(data3['price'])
        except:
            pass
        return None
    except Exception as e:
        print(f"[Price Error] {symbol}: {e}")
        return None

def get_exchange_balance():
    """Get balance. Paper mode: calculate from initial capital + PnL."""
    try:
        if PAPER_TRADING:
            conn = get_db()
            total_pnl = conn.execute('SELECT COALESCE(SUM(pnl), 0) FROM trades WHERE status="closed"').fetchone()[0]
            open_margin = conn.execute('SELECT COALESCE(SUM(position_size), 0) FROM trades WHERE status="open"').fetchone()[0]
            conn.close()
            total = round(PAPER_INITIAL_CAPITAL + total_pnl, 2)
            used = round(open_margin, 2)
            free = round(total - used, 2)
            return {'total': total, 'free': free, 'used': used}
        if not exchange:
            return {'total': 0, 'free': 0, 'used': 0}
        bal = exchange.fetch_balance()
        usdt = bal.get('USDT', {})
        return {
            'total': round(float(usdt.get('total', 0)), 2),
            'free': round(float(usdt.get('free', 0)), 2),
            'used': round(float(usdt.get('used', 0)), 2),
        }
    except Exception as e:
        print(f"[Balance Error] {e}")
        return {'total': 0, 'free': 0, 'used': 0}

def exchange_open_order(symbol, direction, leverage, position_size_usdt, stop_loss=None, take_profit=None):
    """Open a futures position. Paper mode: simulate; Live mode: Bitget via ccxt."""
    try:
        # Normalize symbol
        sym = symbol.upper().replace('/','').replace(':','').replace('_','')
        if not sym.endswith('USDT'):
            sym = sym + 'USDT'
        base = sym.replace('USDT', '')
        ccxt_symbol = f'{base}/USDT:USDT'

        if PAPER_TRADING:
            # Paper trading: get price and simulate
            current_price = get_exchange_price(sym)
            if not current_price:
                print(f"[Paper] Cannot get price for {sym}, skip", flush=True)
                return None

            lev = min(int(leverage), MAX_LEVERAGE)
            lev = max(lev, MIN_LEVERAGE)
            notional = position_size_usdt * lev
            qty = notional / current_price

            print(f"[Paper] Opened {direction} {base} qty={qty:.4f} lev={lev}x @ {current_price} margin={position_size_usdt}U", flush=True)
            return {
                'order_id': f'paper_{int(time.time())}',
                'symbol': sym,
                'direction': direction,
                'entry_price': current_price,
                'amount': qty,
                'leverage': lev,
                'position_size_usdt': position_size_usdt,
                'notional_usdt': round(qty * current_price, 2),
            }

        # Cap leverage
        lev = min(int(leverage), MAX_LEVERAGE)

        # Set leverage for both sides
        try:
            exchange.set_leverage(lev, ccxt_symbol, params={'marginCoin': 'USDT', 'holdSide': 'long'})
            exchange.set_leverage(lev, ccxt_symbol, params={'marginCoin': 'USDT', 'holdSide': 'short'})
        except Exception as e:
            print(f"[Bitget] Set leverage warning: {e}", flush=True)

        # Get current price
        ticker = exchange.fetch_ticker(ccxt_symbol)
        current_price = float(ticker['last'])

        # Calculate quantity: notional = position_size * leverage, qty = notional / price
        notional = position_size_usdt * lev
        qty = notional / current_price

        # Round to market precision
        qty = float(exchange.amount_to_precision(ccxt_symbol, qty))

        # Check max position size from exchange
        try:
            market = exchange.market(ccxt_symbol)
            max_amt = market.get('limits', {}).get('amount', {}).get('max')
            if max_amt and qty > max_amt:
                print(f"[Bitget] qty {qty} > max {max_amt}, capping", flush=True)
                qty = float(exchange.amount_to_precision(ccxt_symbol, max_amt * 0.95))
        except:
            pass

        # Check position level limit from Bitget API
        try:
            sym_clean = sym.replace('/', '').replace(':','')
            lever_info = requests.get(f'https://api.bitget.com/api/v2/mix/market/query-position-lever?symbol={sym_clean}&productType=USDT-FUTURES&marginCoin=USDT', timeout=5).json()
            if lever_info.get('data'):
                # Find the tier for our leverage
                max_qty_for_lev = None
                for tier in lever_info['data']:
                    if int(tier.get('leverage', 0)) >= lev:
                        max_qty_for_lev = float(tier['endUnit'])
                        break
                if not max_qty_for_lev:
                    # Use lowest tier
                    max_qty_for_lev = float(lever_info['data'][0]['endUnit'])

                if qty > max_qty_for_lev:
                    old_qty = qty
                    qty = float(exchange.amount_to_precision(ccxt_symbol, max_qty_for_lev * 0.9))
                    position_size_usdt = round((qty * current_price) / lev, 2)
                    if position_size_usdt < 5:
                        print(f"[Bitget] {base} positionLevel max={max_qty_for_lev} at {lev}x, margin={position_size_usdt}U too small, skip", flush=True)
                        return None
                    print(f"[Bitget] Capped qty {old_qty}→{qty} (positionLevel max={max_qty_for_lev} at {lev}x), margin={position_size_usdt}U", flush=True)
        except Exception as e:
            print(f"[Bitget] Position level check warning: {e}", flush=True)

        # Also check ccxt market max amount
        try:
            market = exchange.market(ccxt_symbol)
            max_amt = market.get('limits', {}).get('amount', {}).get('max')
            if max_amt and qty > max_amt:
                qty = float(exchange.amount_to_precision(ccxt_symbol, max_amt * 0.9))
                position_size_usdt = round((qty * current_price) / lev, 2)
                print(f"[Bitget] Capped qty to market max={max_amt}, margin={position_size_usdt}U", flush=True)
        except:
            pass

        # Check minimum notional (Bitget requires >= 5 USDT)
        notional_value = qty * current_price
        if notional_value < 5:
            print(f"[Bitget] Notional {notional_value:.2f}U < 5U min, {base} too small to trade, skip", flush=True)
            return None

        # Open position
        side = 'buy' if direction == 'LONG' else 'sell'

        print(f"[Bitget] Opening {direction} {base} qty={qty} lev={lev}x @ ~{current_price} notional={notional_value:.1f}U", flush=True)
        order = exchange.create_order(ccxt_symbol, 'market', side, qty, params={
            'marginCoin': 'USDT',
            'tradeSide': 'open',
        })

        entry_price = float(order.get('average') or current_price)
        filled_qty = float(order.get('filled') or qty)

        # Verify position actually exists on exchange
        import time as _t
        _t.sleep(1)
        try:
            positions = exchange.fetch_positions([ccxt_symbol])
            has_pos = any(float(p.get('contracts', 0)) > 0 for p in positions)
            if not has_pos:
                print(f"[Bitget] WARNING: Order placed but no position found! Order may have failed.", flush=True)
                return None
        except:
            pass

        ret = {
            'order_id': str(order['id']),
            'symbol': sym,
            'direction': direction,
            'entry_price': entry_price,
            'amount': filled_qty,
            'leverage': lev,
            'position_size_usdt': position_size_usdt,
            'notional_usdt': round(filled_qty * entry_price, 2),
        }

        print(f"[Bitget] Opened {direction} {base} @ {entry_price}, qty={filled_qty}, lev={lev}x, margin={position_size_usdt}U", flush=True)

        # Set stop loss
        if stop_loss and stop_loss > 0:
            try:
                exchange.set_stop_loss(ccxt_symbol, stop_loss, params={'marginCoin': 'USDT', 'holdSide': direction.lower()})
                ret['sl_set'] = True
                print(f"[Bitget] SL set @ {stop_loss}", flush=True)
            except Exception as e:
                print(f"[Bitget] SL failed: {e}", flush=True)

        # Set take profit
        if take_profit and take_profit > 0:
            try:
                exchange.set_take_profit(ccxt_symbol, take_profit, params={'marginCoin': 'USDT', 'holdSide': direction.lower()})
                ret['tp_set'] = True
                print(f"[Bitget] TP set @ {take_profit}", flush=True)
            except Exception as e:
                print(f"[Bitget] TP failed: {e}", flush=True)

        return ret

    except Exception as e:
        print(f"[Bitget] Order failed: {e}", flush=True)
        traceback.print_exc()
        return None

def exchange_close_position(symbol, direction, amount=None, vol=None):
    """Close a futures position. Paper mode: simulate; Live mode: Bitget flash close."""
    try:
        sym = symbol.upper().replace('/','').replace(':','').replace('_','')
        if not sym.endswith('USDT'):
            sym = sym + 'USDT'
        base = sym.replace('USDT', '')
        ccxt_symbol = f'{base}/USDT:USDT'

        if PAPER_TRADING:
            current_price = get_exchange_price(sym)
            if not current_price:
                return None
            print(f"[Paper] Closed {direction} {base} @ {current_price}", flush=True)
            return {
                'order_id': f'paper_close_{int(time.time())}',
                'exit_price': current_price,
                'filled': amount or 0,
            }

        hold_side = 'long' if direction == 'LONG' else 'short'

        # Get current price before close
        ticker = exchange.fetch_ticker(ccxt_symbol)
        current_price = float(ticker['last'])

        print(f"[Bitget] Flash closing {direction} {base} @ ~{current_price}", flush=True)

        # Use Bitget flash close API (works reliably)
        result = exchange.private_mix_post_v2_mix_order_close_positions({
            'symbol': sym,
            'productType': 'USDT-FUTURES',
            'holdSide': hold_side,
        })

        if result.get('code') == '00000':
            success = result.get('data', {}).get('successList', [])
            order_id = success[0].get('orderId', '') if success else ''
            print(f"[Bitget] Closed {direction} {base} @ ~{current_price}", flush=True)
            return {
                'order_id': str(order_id),
                'exit_price': current_price,
                'filled': amount or 0,
            }
        else:
            print(f"[Bitget] Flash close failed: {result}", flush=True)
            return None

    except Exception as e:
        print(f"[Bitget] Close failed: {e}", flush=True)
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

    # Migrations
    for sql in [
        "ALTER TABLE signals ADD COLUMN cancel_reason TEXT",
    ]:
        try:
            conn.execute(sql)
        except:
            pass

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

def parse_sias_signal(text):
    """Parse Sias加密起飛 signal format.
    Examples:
        #NOM 0.00282-285附近短空
        #B3 0.000425附近追多
        #ETH 2070-55附近多
        #1000RATS 0.0516 附近空單
    """
    if not text or '#' not in text:
        return None

    # Extract #SYMBOL
    sym_match = re.search(r'#(\d*[A-Za-z]{1,10})', text)
    if not sym_match:
        return None
    symbol = sym_match.group(1).upper() + 'USDT'

    # Direction: 多/追多 = LONG, 空/短空/空單 = SHORT
    direction = None
    if any(w in text for w in ['空', '短空', '空單', '空单']):
        direction = 'SHORT'
    elif any(w in text for w in ['多', '追多', '做多']):
        direction = 'LONG'

    if not direction:
        return None

    # Entry price - first number after symbol
    price_match = re.search(r'(\d+\.?\d*)', text[sym_match.end():])
    entry_price = float(price_match.group(1)) if price_match else None

    # No explicit SL/TP in Sias format, use defaults
    # SL: 3% for major, 5% for others
    sl = None
    tp = None
    if entry_price:
        major = symbol.replace('USDT','') in ['BTC','ETH','SOL','XRP','BNB']
        sl_pct = 0.03 if major else 0.05
        tp_pct = 0.06 if major else 0.10
        if direction == 'LONG':
            sl = round(entry_price * (1 - sl_pct), 6)
            tp = round(entry_price * (1 + tp_pct), 6)
        else:
            sl = round(entry_price * (1 + sl_pct), 6)
            tp = round(entry_price * (1 - tp_pct), 6)

    return {
        'symbol': symbol,
        'direction': direction,
        'entry_price': entry_price,
        'stop_loss': sl,
        'take_profit': tp,
        'leverage': None,
    }

def parse_luke_signal(text):
    """Parse LUKE加密集中营 signal format.
    標的：ETH / 方向：合約空单 / 進場：2080附近 / 止损：2121 / 止盈：2051-25-2000
    """
    if not text:
        return None
    sym_match = re.search(r'標的[：:]\s*(\d*[A-Za-z]{2,10})', text)
    if not sym_match:
        return None
    symbol = sym_match.group(1).upper() + 'USDT'

    direction = None
    if any(w in text for w in ['空单', '空單', '做空', '開空', '开空']):
        direction = 'SHORT'
    elif any(w in text for w in ['多单', '多單', '做多', '開多', '开多']):
        direction = 'LONG'
    if not direction:
        return None

    entry = None
    entry_match = re.search(r'(?:進場|进场|入場|入场)[：:]\s*(\d+\.?\d*)', text)
    if entry_match:
        entry = float(entry_match.group(1))

    sl = None
    sl_match = re.search(r'(?:止[损損])[：:]\s*(\d+\.?\d*)', text)
    if sl_match:
        sl = float(sl_match.group(1))

    tp = None
    tp_match = re.search(r'(?:止盈)[：:]\s*(\d+\.?\d*)', text)
    if tp_match:
        tp = float(tp_match.group(1))

    return {
        'symbol': symbol, 'direction': direction, 'entry_price': entry,
        'stop_loss': sl, 'take_profit': tp, 'leverage': None,
    }

def is_luke_signal(text):
    if not text:
        return False
    return '標的' in text and ('方向' in text or '進場' in text or '进场' in text)

def is_sias_signal(text):
    """Check if message is a Sias trading signal"""
    if not text or '#' not in text:
        return False
    has_symbol = bool(re.search(r'#\d*[A-Za-z]{1,10}', text))
    has_direction = any(w in text for w in ['多', '空', '追多', '短空', '空單', '空单'])
    has_price = bool(re.search(r'\d+\.?\d*', text))
    return has_symbol and has_direction and has_price

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

    # Step 2: Extract symbol from English text (e.g. BTC/USDT, #BTC, ETHUSDT, $SOL)
    if not result['symbol']:
        # First try "XXX/USDT" format, including numeric prefixes like 1000RATS/USDT
        slash_match = re.search(r'(\d*[A-Z]{2,10})/USDT', text.upper())
        if slash_match:
            result['symbol'] = slash_match.group(1) + 'USDT'
        else:
            sym_match = re.search(r'[#\$]?(\d*[A-Z]{2,10})(USDT)?', text.upper())
            if sym_match:
                sym = sym_match.group(1)
                skip = ('USDT', 'USD', 'THE', 'FOR', 'AND', 'NOT', 'ALL', 'BUT', 'VIP',
                        'ENTRY', 'SL', 'TP', 'LONG', 'SHORT', 'BUY', 'SELL')
                if sym not in skip:
                    result['symbol'] = sym + 'USDT' if not sym.endswith('USDT') else sym

    # Extract direction — 🚀 = LONG, 👇 = SHORT in this group
    text_upper = text.upper()
    if any(w in text for w in ['做多', '輕倉多', '轻仓多', '加多', '開多', '开多', '進多', '进多', '🚀']) or \
       any(w in text_upper for w in ['LONG', 'BUY']):
        result['direction'] = 'LONG'
    elif any(w in text for w in ['做空', '輕倉空', '轻仓空', '加空', '開空', '开空', '進空', '进空', '👇']) or \
         any(w in text_upper for w in ['SHORT', 'SELL']):
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

# ===== Group TP Signal Detection =====

def ocr_extract_symbol(photo_obj):
    """Download photo and OCR to extract coin symbol (e.g. BTCUSDT → BTC)"""
    try:
        import pytesseract
        from PIL import Image
        import tempfile

        if not tg_client or not photo_obj:
            return None

        # Download photo to temp file
        loop = get_tg_loop()
        import asyncio
        tmp_path = tempfile.mktemp(suffix='.jpg')
        future = asyncio.run_coroutine_threadsafe(
            tg_client.download_media(photo_obj, tmp_path), loop
        )
        future.result(timeout=10)

        # OCR
        img = Image.open(tmp_path)
        text = pytesseract.image_to_string(img)

        # Clean up
        import os
        os.remove(tmp_path)

        # Find XXXUSDT pattern
        symbols = re.findall(r'(\d*[A-Z]{2,10})USDT', text)
        if symbols:
            return symbols[0]
        return None
    except Exception as e:
        print(f"[OCR] Error: {e}", flush=True)
        return None

def check_group_tp_signal(msg_text, msg_photo):
    """Check if group owner posted a TP/profit message and close matching positions.
    Triggers on: tp1/tp2/tp3, 止盈, 翻倍, X倍拿下, 止損 + matching coin name."""
    text = msg_text.lower() if msg_text else ''

    # TP keywords
    tp_keywords = ['tp1', 'tp2', 'tp3', '止盈', '翻倍', '倍拿下', '拿下', '到了', '減倉', '减仓', '套保']
    # SL keywords (group owner says stop loss)
    sl_keywords = ['止損', '止损', '遺憾止損']

    is_tp = any(k in text for k in tp_keywords)
    is_sl = any(k in text for k in sl_keywords)

    if not is_tp and not is_sl:
        return

    reason = 'group_tp' if is_tp else 'group_sl'
    action = '止盈' if is_tp else '止损'

    # Get open trades
    conn = get_db()
    open_trades = conn.execute('''
        SELECT t.*, s.symbol as sig_symbol
        FROM trades t JOIN signals s ON t.signal_id = s.id
        WHERE t.status = 'open'
    ''').fetchall()
    conn.close()

    if not open_trades:
        return

    # Try to match coin from message text
    matched_trades = []
    text_upper = (msg_text or '').upper()

    for trade in open_trades:
        symbol = trade['symbol'].upper().replace('USDT', '').replace('/', '').replace('_', '')
        # Check if coin name appears in the message
        if symbol in text_upper:
            matched_trades.append(trade)

    # If message has coin-like text (e.g. #CFG, $BTC)
    if not matched_trades:
        for trade in open_trades:
            symbol = trade['symbol'].upper().replace('USDT', '').replace('/', '').replace('_', '')
            if f'#{symbol}' in text_upper or f'${symbol}' in text_upper:
                matched_trades.append(trade)

    # If still no match and has photo, try OCR to detect coin from screenshot
    if not matched_trades and msg_photo and open_trades:
        try:
            ocr_symbol = ocr_extract_symbol(msg_photo)
            if ocr_symbol:
                for trade in open_trades:
                    trade_sym = trade['symbol'].upper().replace('USDT', '').replace('/', '').replace('_', '')
                    if trade_sym == ocr_symbol:
                        matched_trades.append(trade)
                        print(f"[Group TP] OCR matched: {ocr_symbol} → {trade['symbol']}", flush=True)
        except Exception as e:
            print(f"[Group TP] OCR failed: {e}", flush=True)

    for trade in matched_trades:
        print(f"[Group TP] Group owner {action}! Closing {trade['symbol']} {trade['direction']}", flush=True)
        price = get_exchange_price(trade['symbol'])
        if price:
            close_trade(trade['id'], price, reason)
            add_log(f"群主{action}: {trade['symbol']} {trade['direction']} → 自动平仓")

            # Send TG notification
            try:
                entry = trade['entry_price']
                lev = trade['leverage'] or 20
                direction = trade['direction']
                if direction == 'LONG':
                    pnl_pct = ((price - entry) / entry) * lev * 100
                else:
                    pnl_pct = ((entry - price) / entry) * lev * 100
                pnl = trade['position_size'] * (pnl_pct / 100)

                emoji = '🎯' if is_tp else '🛑'
                msg = f"""{emoji} 群主{action} - 自动平仓
━━━━━━━━━━━━━━━
💰 {trade['symbol']} {direction}
📍 入场: {entry}
📍 平仓: {price}
📊 ROI: {pnl_pct:+.1f}%
💵 盈亏: {pnl:+.2f} USDT
🔧 杠杆: {lev}x
📝 群消息: {(msg_text or '')[:50]}"""
                send_tg_notification(msg)
            except Exception as e:
                print(f"[Group TP] Notification error: {e}", flush=True)

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

        # Classify coin: major vs small
        MAJOR_COINS = ['BTC', 'ETH', 'SOL', 'XRP', 'BNB', 'ADA', 'DOGE', 'AVAX', 'DOT', 'LINK',
                       'XAG', 'MATIC', 'UNI', 'LTC', 'ATOM', 'FIL', 'APT', 'ARB', 'OP', 'SUI']
        base_coin = signal['symbol'].upper().replace('USDT', '').replace('/', '').replace('_', '')
        is_major = base_coin in MAJOR_COINS

        if is_major:
            position_size = 400  # 300-500U range, use 400
            lev_min, lev_max = 50, 100
        else:
            position_size = 200  # 100-300U range, use 200
            lev_min, lev_max = 10, 30

        # Skip if price deviated too far (>10%)
        entry_price_check = signal['entry_price']
        current_price_check = get_exchange_price(signal['symbol'])
        if entry_price_check and entry_price_check > 0 and current_price_check:
            diff_pct = abs(current_price_check - entry_price_check) / entry_price_check * 100
            if diff_pct > 10:
                reason = f"价格偏离{diff_pct:.1f}%>10%"
                print(f"[Auto-Trade] {signal['symbol']} {reason}, skip", flush=True)
                conn.execute("UPDATE signals SET status='cancelled', cancel_reason=? WHERE id=?", (reason, signal_id))
                conn.commit()
                conn.close()
                return False

        # Skip if current price already past TP
        tp_check = signal['take_profit']
        if tp_check and tp_check > 0 and current_price_check:
            direction = signal['direction']
            if direction == 'LONG' and current_price_check >= tp_check:
                reason = f"价格{current_price_check}已超TP{tp_check}"
                conn.execute("UPDATE signals SET status='cancelled', cancel_reason=? WHERE id=?", (reason, signal_id))
                conn.commit()
                conn.close()
                return False
            elif direction == 'SHORT' and current_price_check <= tp_check:
                reason = f"价格{current_price_check}已超TP{tp_check}"
                conn.execute("UPDATE signals SET status='cancelled', cancel_reason=? WHERE id=?", (reason, signal_id))
                conn.commit()
                conn.close()
                return False

        # Dynamic leverage based on SL distance, within tier limits
        entry_price = signal['entry_price']
        sl_price = signal['stop_loss']
        if entry_price and sl_price and entry_price > 0:
            sl_dist_pct = abs(entry_price - sl_price) / entry_price * 100
            if sl_dist_pct < 1:
                leverage = 100
            elif sl_dist_pct < 2:
                leverage = 75
            elif sl_dist_pct < 3:
                leverage = 50
            elif sl_dist_pct < 5:
                leverage = 30
            else:
                leverage = 20
            # Cap within tier limits
            leverage = max(min(leverage, lev_max), lev_min)
            # Safety: leverage * sl_dist < 50%
            max_safe_lev = int(50 / sl_dist_pct) if sl_dist_pct > 0 else 100
            leverage = min(leverage, max_safe_lev, MAX_LEVERAGE)
            leverage = max(leverage, MIN_LEVERAGE)
            print(f"[Auto-Trade] Dynamic leverage: SL dist={sl_dist_pct:.2f}% → {leverage}x", flush=True)
        else:
            leverage = signal['leverage'] or int(config.get('default_leverage', 20))
            leverage = max(min(leverage, MAX_LEVERAGE), MIN_LEVERAGE)

        # ETH 单独降杠杆 (回测: ETH SHORT 亏-1074U 最大亏损源)
        symbol_upper = signal['symbol'].upper()
        if 'ETH' in symbol_upper and 'ETC' not in symbol_upper:
            if leverage > ETH_MAX_LEVERAGE:
                print(f"[ETH Cap] {signal['symbol']} leverage {leverage}x → {ETH_MAX_LEVERAGE}x", flush=True)
                leverage = ETH_MAX_LEVERAGE

        # Open on MEXC
        order_result = exchange_open_order(
            symbol=signal['symbol'],
            direction=signal['direction'],
            leverage=leverage,
            position_size_usdt=position_size,
            stop_loss=signal['stop_loss'],
            take_profit=signal['take_profit'],
        )

        if order_result is None:
            print(f"[Auto-Trade] Order failed for {signal['symbol']}", flush=True)
            add_log(f"下单失败: {signal['symbol']} {signal['direction']}", 'ERROR')
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

# WebSocket price cache
ws_prices = {}
ws_connected = False

def start_ws_price_stream():
    """Start Binance WebSocket for real-time prices of watched symbols"""
    global ws_connected
    import websocket
    import _thread

    def get_watched_symbols():
        """Get all symbols we need to watch (open trades + pending signals)"""
        conn = get_db()
        symbols = set()
        for row in conn.execute("SELECT DISTINCT symbol FROM trades WHERE status='open'"):
            symbols.add(row['symbol'].lower().replace('/', ''))
        for row in conn.execute("SELECT DISTINCT symbol FROM signals WHERE status='pending' AND created_at > datetime('now', '-30 minutes')"):
            symbols.add(row['symbol'].lower().replace('/', ''))
        conn.close()
        return symbols

    def run_ws():
        global ws_connected
        while True:
            try:
                symbols = get_watched_symbols()
                if not symbols:
                    time.sleep(10)
                    continue

                streams = '/'.join([f'{s.lower()}@ticker' for s in symbols])
                url = f'wss://stream.binance.com:9443/stream?streams={streams}'
                print(f"[WS] Connecting to {len(symbols)} streams...", flush=True)

                def on_message(ws, message):
                    global ws_prices
                    try:
                        data = json.loads(message)
                        if 'data' in data:
                            d = data['data']
                            sym = d.get('s', '').upper()
                            price = float(d.get('c', 0))
                            if sym and price > 0:
                                ws_prices[sym] = price
                    except:
                        pass

                def on_open(ws):
                    global ws_connected
                    ws_connected = True
                    print(f"[WS] Connected, watching {len(symbols)} symbols", flush=True)

                def on_close(ws, code, msg):
                    global ws_connected
                    ws_connected = False
                    print(f"[WS] Disconnected, reconnecting...", flush=True)

                def on_error(ws, error):
                    print(f"[WS] Error: {error}", flush=True)

                ws = websocket.WebSocketApp(url,
                    on_message=on_message, on_open=on_open,
                    on_close=on_close, on_error=on_error)
                ws.run_forever(ping_interval=30, ping_timeout=10)
            except Exception as e:
                print(f"[WS] Error: {e}", flush=True)
            time.sleep(5)

    _thread.start_new_thread(run_ws, ())

def get_realtime_price(symbol):
    """Get price from WS cache, fallback to REST"""
    sym = symbol.upper().replace('/', '').replace('_', '').replace(':', '')
    if sym in ws_prices:
        return ws_prices[sym]
    return get_exchange_price(symbol)

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
                price = get_realtime_price(symbol)
                if price is None:
                    continue

                direction = trade['direction']
                sl = trade['stop_loss']
                tp = trade['take_profit']
                entry = trade['entry_price']
                leverage = trade['leverage'] or 20
                close_reason = None

                # Calculate current ROI %
                if direction == 'LONG':
                    roi_pct = ((price - entry) / entry) * leverage * 100
                else:
                    roi_pct = ((entry - price) / entry) * leverage * 100

                # Only use signal SL/TP, no hard stop
                if sl and direction == 'LONG' and price <= sl:
                    close_reason = 'sl'
                elif sl and direction == 'SHORT' and price >= sl:
                    close_reason = 'sl'
                elif tp and direction == 'LONG' and price >= tp:
                    close_reason = 'tp'
                elif tp and direction == 'SHORT' and price <= tp:
                    close_reason = 'tp'

                if close_reason:
                    result = close_trade(trade['id'], price, close_reason)
                    # If close failed 3+ times, mark as closed to stop retry spam
                    if result is None:
                        conn2 = get_db()
                        fail_count = conn2.execute(
                            "SELECT COUNT(*) as c FROM trade_log WHERE message LIKE ? AND message LIKE '%失败%'",
                            (f'%{symbol}%',)
                        ).fetchone()['c']
                        conn2.close()
                        if fail_count >= 3:
                            print(f"[Monitor] {symbol} close failed 3+ times, marking closed (no_position)", flush=True)
                            conn3 = get_db()
                            conn3.execute("UPDATE trades SET status='closed', close_reason='no_position' WHERE id=?", (trade['id'],))
                            conn3.commit()
                            conn3.close()

            # Auto-cancel pending signals older than 30 minutes
            conn = get_db()
            expired = conn.execute("UPDATE signals SET status='cancelled', cancel_reason='超时30分钟' WHERE status='pending' AND created_at <= datetime('now', '-30 minutes')").rowcount
            if expired > 0:
                conn.commit()
                print(f"[Monitor] Cancelled {expired} expired pending signals (>30min)", flush=True)

            # Check pending signals — retry if price now in entry zone
            pending = conn.execute('''
                SELECT * FROM signals WHERE status = 'pending'
                AND created_at > datetime('now', '-30 minutes')
            ''').fetchall()
            conn.close()

            for sig in pending:
                sym = sig['symbol']
                entry = sig['entry_price']
                tp = sig['take_profit']
                direction = sig['direction']
                if not entry or entry <= 0:
                    continue

                price = get_realtime_price(sym)
                if not price:
                    continue

                # Check if price is now in entry zone (within 1.5%)
                diff_pct = abs(price - entry) / entry * 100
                if diff_pct > 1.5:
                    # Also check if price is on the right side
                    if direction == 'LONG' and price > entry * 1.015:
                        continue
                    elif direction == 'SHORT' and price < entry * 0.985:
                        continue
                    elif diff_pct > 10:
                        continue

                # Skip if price already past TP
                if tp and tp > 0:
                    if direction == 'LONG' and price >= tp:
                        continue
                    elif direction == 'SHORT' and price <= tp:
                        continue

                # Price in zone, try to open
                print(f"[Pending] {sym} price {price} near entry {entry} ({diff_pct:.1f}%), opening!", flush=True)
                if auto_open_trade(sig['id']):
                    add_log(f"Pending信号触发: {sym} {direction} price={price}")

        except Exception as e:
            print(f"[Monitor Error] {e}")

        time.sleep(5)

def close_trade(trade_id, exit_price, reason):
    """Close a trade and record PnL"""
    conn = get_db()
    trade = conn.execute('SELECT * FROM trades WHERE id = ?', (trade_id,)).fetchone()
    if not trade or trade['status'] != 'open':
        conn.close()
        return None

    # Close on exchange
    close_result = exchange_close_position(trade['symbol'], trade['direction'], trade['amount'])

    if not close_result:
        print(f"[Close] Exchange close failed for {trade['symbol']}, trade stays open", flush=True)
        add_log(f"平仓失败: {trade['symbol']} {trade['direction']} (reason={reason})", 'ERROR')
        conn.close()
        return None

    if close_result.get('exit_price'):
        exit_price = close_result['exit_price']

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

    # Estimate fees: Bitget taker 0.06% per side (open + close)
    notional = size * leverage
    trading_fees = notional * 0.0006 * 2  # open + close

    # Estimate funding fees: ~0.01% per 8h, based on holding time
    opened_at = trade['opened_at']
    if opened_at:
        try:
            open_time = datetime.fromisoformat(opened_at.replace('Z', '+00:00'))
            hold_hours = (datetime.now(timezone.utc) - open_time).total_seconds() / 3600
            funding_periods = hold_hours / 8
            funding_fees = notional * 0.0001 * funding_periods  # 0.01% per 8h
        except:
            funding_fees = 0
    else:
        funding_fees = 0

    fees = round(trading_fees + funding_fees, 4)
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

def get_notify_chat_id(group_name):
    """Get the notification chat_id for a group (per-group or default)"""
    group_cfg = TG_GROUPS.get(group_name, {})
    custom_id = group_cfg.get('notify_chat_id')
    if custom_id:
        return custom_id
    return TG_CHAT_ID

def send_tg_notification(signal, raw_message=None, trade_info=None):
    """Send new signal + trade notification to Telegram group"""
    try:
        direction_emoji = '🟢 做多 LONG' if signal['direction'] == 'LONG' else '🔴 做空 SHORT'
        sym = signal['symbol'].replace('USDT', '/USDT')
        now_str = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M UTC')

        price = get_exchange_price(signal['symbol'])
        price_str = f"<code>{price}</code>" if price else "获取失败"

        entry = signal.get('entry_price')
        entry_str = f"<code>{entry}</code>" if entry else price_str

        group_name = signal.get('_group', 'vip點位策略')
        text = f"📡 <b>{group_name} · 新信号开仓</b>\n"
        text += f"━━━━━━━━━━━━━━━\n"
        text += f"💰 货币: <b>{sym}</b>\n"
        text += f"📊 方向: <b>{direction_emoji}</b>\n"
        text += f"🎯 开仓价: {entry_str}\n"
        text += f"📈 当前价: {price_str}\n"
        if signal.get('stop_loss'):
            text += f"🛑 止损: <code>{signal['stop_loss']}</code>\n"
        if signal.get('take_profit'):
            text += f"✅ 止盈: <code>{signal['take_profit']}</code>\n"
        text += f"⚡ 杠杆: <code>{signal.get('leverage') or 20}x</code>\n"
        text += f"🕐 时间: <code>{now_str}</code>\n"

        if trade_info:
            text += f"💵 保证金: <code>{trade_info.get('size', '—')} USDT</code>\n"

        text += f"━━━━━━━━━━━━━━━\n"
        text += f"🏦 交易所: <b>Bitget (实盘)</b>\n"

        if raw_message:
            raw_short = raw_message[:300].replace('<', '&lt;').replace('>', '&gt;')
            text += f"💬 原始信息:\n<blockquote>{raw_short}</blockquote>"

        chat_id = get_notify_chat_id(group_name)
        url = f'https://api.telegram.org/bot{TG_BOT_TOKEN}/sendMessage'
        resp = requests.post(url, json={
            'chat_id': chat_id,
            'text': text,
            'parse_mode': 'HTML',
        }, timeout=10)
        if resp.status_code == 200:
            print(f"[TG Bot] Open notification sent: {signal['symbol']} {signal['direction']} -> chat {chat_id}")
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

        # Get group name from signal category
        conn = get_db()
        sig = conn.execute('SELECT category FROM signals WHERE id=?', (trade.get('signal_id'),)).fetchone()
        conn.close()
        group_name = sig['category'] if sig and sig['category'] else 'Signal Trader'

        reason_labels = {
            'tp': ('✅', '自动止盈'),
            'sl': ('🛑', '自动止损'),
            'hard_sl_-50%': ('🛑', '硬止损 -50% ROI'),
            'group_tp': ('🎯', '群主止盈'),
            'group_sl': ('🛑', '群主止损'),
        }
        emoji, label = reason_labels.get(reason, ('📤', '手动平仓'))
        header = f'{emoji} <b>{group_name} · {label}</b>'

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
        text += f"🏦 交易所: <b>Bitget (实盘)</b>\n"

        chat_id = get_notify_chat_id(group_name)
        url = f'https://api.telegram.org/bot{TG_BOT_TOKEN}/sendMessage'
        resp = requests.post(url, json={
            'chat_id': chat_id,
            'text': text,
            'parse_mode': 'HTML',
        }, timeout=10)
        if resp.status_code == 200:
            print(f"[TG Bot] Close notification sent: {sym} {reason} PnL={pnl:+.2f} -> chat {chat_id}")
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
    # 🚀 = LONG, 👇 = SHORT in this group's format
    has_direction = any(w in text for w in ['做多', '做空', '開多', '開空', '开多', '开空', '🚀', '👇']) or \
                   any(w in text.upper() for w in ['LONG', 'SHORT'])
    has_price = bool(re.search(r'\d+\.?\d*', text))
    has_entry = 'ENTRY' in text.upper() or '入場' in text or '入场' in text or '市價' in text or '市价' in text
    return has_direction and has_price and has_entry

def start_telegram_listener():
    """Start Telethon client on the persistent event loop"""
    print("[Telegram] Starting listener...", flush=True)
    loop = get_tg_loop()
    future = asyncio.run_coroutine_threadsafe(_run_telegram_listener(), loop)
    # Log if future fails
    def _on_done(f):
        try:
            f.result()
        except Exception as e:
            import traceback
            print(f"[Telegram] Listener crashed: {e}", flush=True)
            traceback.print_exc()
    future.add_done_callback(_on_done)
    # Wait briefly to catch immediate errors
    import time
    time.sleep(2)
    print(f"[Telegram] Listener future state: {future.done()}, tg_status={tg_status}", flush=True)

async def _run_telegram_listener():
    global tg_client, tg_status
    from telethon import TelegramClient, events
    import sys
    try:
        print("[Telegram] Connecting...", flush=True)
        tg_client = TelegramClient(TG_SESSION_PATH, TG_API_ID, TG_API_HASH)
        await tg_client.connect()

        if not await tg_client.is_user_authorized():
            tg_status['error'] = 'Not authenticated. Use /api/telegram/auth to login.'
            tg_status['connected'] = False
            print("[Telegram] Not authorized!", flush=True)
            return

        me = await tg_client.get_me()
        tg_status['connected'] = True
        tg_status['error'] = None
        print(f"[Telegram] Connected as {me.first_name} ({me.phone})", flush=True)

        # Find all configured groups
        targets = {}
        async for dialog in tg_client.iter_dialogs():
            for group_name, group_cfg in TG_GROUPS.items():
                if group_name.lower() in (dialog.name or '').lower():
                    targets[group_name] = {'entity': dialog.entity, **group_cfg}
                    print(f"[Telegram] Found group: {dialog.name} (parser={group_cfg['parser']})", flush=True)

        if not targets:
            tg_status['error'] = f'Cannot find any groups'
            print(f"[Telegram] Cannot find any configured groups!", flush=True)

        if targets:
            tg_status['listening'] = True
            group_names = [getattr(t['entity'], 'title', n) for n, t in targets.items()]
            tg_status['group_name'] = ' + '.join(group_names)
            print(f"[Telegram] Listening to: {tg_status['group_name']}", flush=True)

            # Map entity IDs to group config
            entity_to_group = {}
            for name, cfg in targets.items():
                eid = cfg['entity'].id
                entity_to_group[eid] = {'name': name, **cfg}

            chat_entities = [cfg['entity'] for cfg in targets.values()]

            @tg_client.on(events.NewMessage(chats=chat_entities))
            async def handler(event):
                msg_text = event.message.text or ''
                chat_id = event.message.peer_id.channel_id if hasattr(event.message.peer_id, 'channel_id') else 0
                group_info = entity_to_group.get(chat_id, {})
                group_name = group_info.get('name', 'unknown')
                parser_type = group_info.get('parser', 'vip')
                priority = group_info.get('priority', 99)

                tg_status['last_message'] = msg_text[:100] if msg_text else '(photo)'
                if msg_text:
                    print(f"[TG:{group_name}] {msg_text[:80]}", flush=True)

                # Check if this is a TP/close signal from group owner (vip group only)
                if parser_type == 'vip':
                    check_group_tp_signal(msg_text, event.message.photo)

                if not msg_text:
                    return

                # Parse signal based on group type
                signal = None
                category = group_name

                if parser_type == 'vip' and is_vip_signal(msg_text):
                    signal = parse_vip_signal(msg_text)
                elif parser_type == 'sias' and is_sias_signal(msg_text):
                    signal = parse_sias_signal(msg_text)
                elif parser_type == 'luke' and is_luke_signal(msg_text):
                    signal = parse_luke_signal(msg_text)

                if signal:
                    print(f"[{group_name} Signal] Parsed: {signal['symbol']} {signal['direction']}", flush=True)
                    msg_time = event.message.date.strftime('%Y-%m-%d %H:%M:%S') if event.message.date else None

                    # Always save signal to DB for analysis
                    conn = get_db()
                    conn.execute('''
                        INSERT INTO signals (symbol, direction, entry_price, stop_loss, take_profit,
                                           leverage, raw_message, source, status, category, signal_time)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'telegram', 'pending', ?, ?)
                    ''', (
                        signal['symbol'], signal['direction'], signal.get('entry_price'),
                        signal.get('stop_loss') or 0, signal.get('take_profit') or 0,
                        signal.get('leverage') or int(get_config().get('default_leverage', 20)),
                        msg_text,
                        category,
                        msg_time
                    ))
                    conn.commit()
                    signal_id = conn.execute('SELECT last_insert_rowid()').fetchone()[0]

                    # Check for duplicate: same symbol already open
                    existing = conn.execute('''
                        SELECT t.id, s.category FROM trades t JOIN signals s ON t.signal_id = s.id
                        WHERE t.symbol = ? AND t.status = 'open'
                    ''', (signal['symbol'],)).fetchone()

                    if existing:
                        print(f"[{group_name}] {signal['symbol']} already open from {existing['category']}, skip trade", flush=True)
                        conn.execute("UPDATE signals SET status='skipped', cancel_reason=? WHERE id=?",
                                    (f"重复:{existing['category']}已持仓", signal_id))
                        conn.commit()
                        conn.close()
                        return
                    conn.close()

                    add_log(f"新信号: {signal['symbol']} {signal['direction']} (from {group_name})")

                    # Auto-open trade
                    trade_info = None
                    if auto_open_trade(signal_id):
                        c2 = get_db()
                        t = c2.execute('SELECT position_size FROM trades WHERE signal_id=?', (signal_id,)).fetchone()
                        if t:
                            trade_info = {'size': round(t['position_size'], 2)}
                        c2.close()
                    else:
                        print(f"[{group_name}] Auto-trade failed for {signal['symbol']}, stays pending", flush=True)

                    # Telegram notification
                    signal['_group'] = group_name
                    threading.Thread(target=send_tg_notification, args=(signal, msg_text, trade_info), daemon=True).start()

        await tg_client.run_until_disconnected()

    except Exception as e:
        tg_status['error'] = str(e)
        tg_status['connected'] = False
        import traceback
        print(f"[Telegram Error] {e}", flush=True)
        traceback.print_exc()

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
    bal = get_exchange_balance()
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
    price = get_exchange_price(trade['symbol'])
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
        price = get_exchange_price(t['symbol'])
        if price:
            entry = t['entry_price']
            direction = t['direction']
            leverage = t['leverage'] or 20
            margin = t['position_size'] or 300
            notional = margin * leverage
            amount = t['amount'] or 0

            # PnL
            if direction == 'LONG':
                unrealized_pct = ((price - entry) / entry) * leverage * 100
            else:
                unrealized_pct = ((entry - price) / entry) * leverage * 100
            unrealized_pnl = margin * (unrealized_pct / 100)

            # Estimated liquidation price (simplified: when loss = margin)
            if direction == 'LONG':
                liq_price = round(entry * (1 - 1/leverage * 0.9), 6)
            else:
                liq_price = round(entry * (1 + 1/leverage * 0.9), 6)

            # MMR (maintenance margin rate) ~ 0.5-1% for most pairs
            mmr = 0.005
            maintenance_margin = round(notional * mmr, 2)

            # Fees estimate
            trading_fees = round(notional * 0.0006 * 2, 2)
            hold_hours = 0
            if t['opened_at']:
                try:
                    ot = datetime.fromisoformat(t['opened_at'].replace('Z', '+00:00'))
                    hold_hours = round((datetime.now(timezone.utc) - ot).total_seconds() / 3600, 1)
                except:
                    pass
            funding_fees = round(notional * 0.0001 * (hold_hours / 8), 2)

            td['current_price'] = price
            td['mark_price'] = price  # Paper mode: mark = last
            td['unrealized_pnl'] = round(unrealized_pnl, 2)
            td['unrealized_pct'] = round(unrealized_pct, 2)
            td['margin'] = round(margin, 2)
            td['notional'] = round(notional, 2)
            td['liq_price'] = liq_price
            td['mmr'] = mmr
            td['maintenance_margin'] = maintenance_margin
            td['avg_price'] = entry
            td['trading_fees'] = trading_fees
            td['funding_fees'] = funding_fees
            td['total_fees'] = round(trading_fees + funding_fees, 2)
            td['hold_hours'] = hold_hours
            td['real_pnl'] = round(unrealized_pnl - trading_fees - funding_fees, 2)
            td['sl'] = t['sl']
            td['tp'] = t['tp']
        result.append(td)
    return jsonify(result)

@app.route('/api/daily-pnl')
def api_daily_pnl():
    conn = get_db()
    rows = conn.execute('SELECT * FROM daily_pnl ORDER BY date').fetchall()
    conn.close()
    return jsonify([dict(r) for r in rows])

@app.route('/api/backtest')
def api_backtest():
    """Return backtest results"""
    bt_file = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'backtest_results.json')
    if not os.path.exists(bt_file):
        return jsonify({'error': 'No backtest results found'}), 404
    with open(bt_file, 'r') as f:
        return jsonify(json.load(f))

@app.route('/api/signal-stats')
def api_signal_stats():
    """Daily signal statistics"""
    conn = get_db()
    rows = conn.execute('''
        SELECT date(created_at) as day,
               COUNT(*) as total,
               SUM(CASE WHEN status='active' OR status='closed' THEN 1 ELSE 0 END) as traded,
               SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
               SUM(CASE WHEN status='skipped' THEN 1 ELSE 0 END) as skipped,
               SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM signals
        GROUP BY date(created_at)
        ORDER BY day DESC
        LIMIT 30
    ''').fetchall()
    conn.close()
    return jsonify([dict(r) for r in rows])

@app.route('/api/backtest/trade/<int:idx>')
def api_backtest_trade(idx):
    """Return single backtest trade with candle data for charting"""
    detail_file = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'backtest_trades_detail.json')
    if not os.path.exists(detail_file):
        return jsonify({'error': 'No detail data'}), 404
    with open(detail_file, 'r') as f:
        trades = json.load(f)
    if idx < 0 or idx >= len(trades):
        return jsonify({'error': 'Invalid index'}), 404
    return jsonify(trades[idx])

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
    bal = get_exchange_balance()
    return jsonify({
        'connected': exchange is not None,
        'balance': bal,
        'api_key_set': bool(EXCHANGE_API_KEY),
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
<title>Signal Trader (Bitget)</title>
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
        <h1>Signal Trader (Bitget)</h1>
        <div class="header-right">
            <div id="exchangeBadge" class="exchange-badge disconnected">Bitget: 未连接</div>
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

    <!-- Backtest -->
    <div class="section" id="backtestSection">
        <div class="section-title">回测结果 (2026-01-01 → 2026-04-11) <button onclick="loadBacktest()" style="margin-left:12px;padding:4px 12px;background:#f59e0b;color:#000;border:none;border-radius:4px;cursor:pointer;font-size:12px;">加载回测</button></div>
        <div id="backtestContent"><div style="text-align:center;padding:20px;color:#64748b;">点击加载回测查看详细结果</div></div>
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
    if (s.exchange_connected) { eb.className='exchange-badge connected'; eb.textContent='Bitget: 已连接'; }
    else { eb.className='exchange-badge disconnected'; eb.textContent='Bitget: 未连接'; }
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

function showTradeDetail(idx) {
    // Create modal if not exists
    let modal = document.getElementById('tradeModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'tradeModal';
        modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:1000;display:flex;align-items:center;justify-content:center;';
        modal.onclick = (e)=>{if(e.target===modal)modal.style.display='none';};
        document.body.appendChild(modal);
    }
    modal.style.display = 'flex';
    modal.innerHTML = '<div style="background:#0f172a;border-radius:12px;padding:24px;max-width:800px;width:95%;max-height:90vh;overflow-y:auto;border:1px solid #334155;"><div style="text-align:center;color:#f59e0b;">加载中...</div></div>';

    fetch('/api/backtest/trade/'+idx).then(r=>r.json()).then(t=>{
        let c = t.real_pnl>=0?'#10b981':'#ef4444';
        let dirColor = t.direction=='LONG'?'#10b981':'#ef4444';
        let hitLabel = {tp:'✅ 止盈',sl:'🛑 止损',hard_sl:'💀 硬止损',timeout:'⏰ 超时'}[t.hit_type]||t.hit_type;

        let html = `<div style="background:#0f172a;border-radius:12px;padding:24px;max-width:800px;width:95%;max-height:90vh;overflow-y:auto;border:1px solid #334155;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h3 style="margin:0;color:#e2e8f0;">${t.symbol} <span style="color:${dirColor};">${t.direction}</span> ${t.leverage}x</h3>
                <button onclick="document.getElementById('tradeModal').style.display='none'" style="background:none;border:none;color:#94a3b8;font-size:24px;cursor:pointer;">✕</button>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px;font-size:13px;">
                <div style="background:#1e293b;padding:10px;border-radius:6px;">
                    <span style="color:#94a3b8;">信号时间:</span> <span style="color:#e2e8f0;">${t.open_time}</span>
                </div>
                <div style="background:#1e293b;padding:10px;border-radius:6px;">
                    <span style="color:#94a3b8;">平仓时间:</span> <span style="color:#e2e8f0;">${t.close_time||'—'}</span>
                </div>
                <div style="background:#1e293b;padding:10px;border-radius:6px;">
                    <span style="color:#94a3b8;">信号入场价:</span> <span style="color:#e2e8f0;">${t.signal_entry||'—'}</span>
                </div>
                <div style="background:#1e293b;padding:10px;border-radius:6px;">
                    <span style="color:#94a3b8;">实际入场价:</span> <span style="color:#f59e0b;">${t.actual_entry}</span>
                </div>
                <div style="background:#1e293b;padding:10px;border-radius:6px;">
                    <span style="color:#94a3b8;">止盈价:</span> <span style="color:#10b981;">${t.tp||'—'}</span>
                </div>
                <div style="background:#1e293b;padding:10px;border-radius:6px;">
                    <span style="color:#94a3b8;">止损价:</span> <span style="color:#ef4444;">${t.sl||'—'}</span>
                </div>
                <div style="background:#1e293b;padding:10px;border-radius:6px;">
                    <span style="color:#94a3b8;">平仓价:</span> <span style="color:${c};">${t.exit_price}</span>
                </div>
                <div style="background:#1e293b;padding:10px;border-radius:6px;">
                    <span style="color:#94a3b8;">结果:</span> <span>${hitLabel}</span>
                </div>
                <div style="background:#1e293b;padding:10px;border-radius:6px;">
                    <span style="color:#94a3b8;">保证金:</span> <span style="color:#e2e8f0;">${t.margin}U</span>
                </div>
                <div style="background:#1e293b;padding:10px;border-radius:6px;">
                    <span style="color:#94a3b8;">持仓时长:</span> <span style="color:#e2e8f0;">${t.hours_held}h</span>
                </div>
                <div style="background:#1e293b;padding:10px;border-radius:6px;">
                    <span style="color:#94a3b8;">盈亏:</span> <span style="color:${c};font-weight:700;">${t.real_pnl>=0?'+':''}${t.real_pnl.toFixed(2)}U (${t.pnl_pct>=0?'+':''}${t.pnl_pct.toFixed(1)}%)</span>
                </div>
                <div style="background:#1e293b;padding:10px;border-radius:6px;">
                    <span style="color:#94a3b8;">费用:</span> <span style="color:#64748b;">手续费${t.trading_fees}U + 资金费${t.funding_fees}U</span>
                </div>
            </div>

            <div style="margin-top:12px;">
                <canvas id="tradeChart" height="250"></canvas>
            </div>
        </div>`;
        modal.innerHTML = html;

        // Draw candle chart with entry/exit/sl/tp lines
        if (t.candles && t.candles.length > 0) {
            let labels = t.candles.map(c=>new Date(c.time).toLocaleString('zh',{month:'numeric',day:'numeric',hour:'2-digit',minute:'2-digit'}));
            let highs = t.candles.map(c=>c.high);
            let lows = t.candles.map(c=>c.low);
            let closes = t.candles.map(c=>c.close);

            let datasets = [
                {label:'价格',data:closes,borderColor:'#e2e8f0',pointRadius:0,borderWidth:1.5,fill:false},
                {label:'最高',data:highs,borderColor:'rgba(226,232,240,0.2)',pointRadius:0,borderWidth:0.5,fill:false},
                {label:'最低',data:lows,borderColor:'rgba(226,232,240,0.2)',pointRadius:0,borderWidth:0.5,fill:false},
            ];

            // Horizontal lines for entry, tp, sl, exit
            let entryLine = Array(labels.length).fill(t.actual_entry);
            datasets.push({label:'入场 '+t.actual_entry,data:entryLine,borderColor:'#f59e0b',borderDash:[5,5],pointRadius:0,borderWidth:1,fill:false});
            if(t.tp){let tpLine=Array(labels.length).fill(t.tp);datasets.push({label:'止盈 '+t.tp,data:tpLine,borderColor:'#10b981',borderDash:[3,3],pointRadius:0,borderWidth:1,fill:false});}
            if(t.sl){let slLine=Array(labels.length).fill(t.sl);datasets.push({label:'止损 '+t.sl,data:slLine,borderColor:'#ef4444',borderDash:[3,3],pointRadius:0,borderWidth:1,fill:false});}
            let exitLine = Array(labels.length).fill(null);exitLine[exitLine.length-1]=t.exit_price;
            datasets.push({label:'平仓 '+t.exit_price,data:exitLine,borderColor:c,pointRadius:6,pointBackgroundColor:c,borderWidth:0,fill:false});

            new Chart(document.getElementById('tradeChart'),{
                type:'line',
                data:{labels:labels,datasets:datasets},
                options:{responsive:true,plugins:{legend:{labels:{color:'#94a3b8',font:{size:10}}}},scales:{x:{ticks:{color:'#64748b',maxTicksLimit:10,font:{size:9}},grid:{display:false}},y:{ticks:{color:'#64748b',font:{size:10}},grid:{color:'rgba(100,116,139,0.1)'}}}}
            });
        }
    }).catch(e=>{modal.innerHTML='<div style="background:#0f172a;padding:24px;border-radius:12px;color:#ef4444;">加载失败: '+e+'</div>';});
}

function loadBacktest() {
    document.getElementById('backtestContent').innerHTML = '<div style="text-align:center;padding:20px;color:#f59e0b;">加载中...</div>';
    fetch('/api/backtest').then(r=>r.json()).then(data=>{
        if(data.error){document.getElementById('backtestContent').innerHTML='<div style="color:#ef4444;padding:20px;">'+data.error+'</div>';return;}
        let s=data.summary;
        let pnlColor = s.total_pnl>=0?'#10b981':'#ef4444';
        let html = `
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px;">
            <div style="background:#1e293b;padding:12px;border-radius:8px;text-align:center;">
                <div style="color:#94a3b8;font-size:11px;">初始本金</div>
                <div style="font-size:18px;font-weight:700;color:#e2e8f0;">${s.initial_capital.toLocaleString()}U</div>
            </div>
            <div style="background:#1e293b;padding:12px;border-radius:8px;text-align:center;">
                <div style="color:#94a3b8;font-size:11px;">最终资金</div>
                <div style="font-size:18px;font-weight:700;color:${pnlColor};">${s.final_capital.toLocaleString()}U</div>
            </div>
            <div style="background:#1e293b;padding:12px;border-radius:8px;text-align:center;">
                <div style="color:#94a3b8;font-size:11px;">总盈亏</div>
                <div style="font-size:18px;font-weight:700;color:${pnlColor};">${s.total_pnl>=0?'+':''}${s.total_pnl.toLocaleString()}U</div>
                <div style="color:${pnlColor};font-size:12px;">${s.total_pnl_pct>=0?'+':''}${s.total_pnl_pct}%</div>
            </div>
            <div style="background:#1e293b;padding:12px;border-radius:8px;text-align:center;">
                <div style="color:#94a3b8;font-size:11px;">胜率</div>
                <div style="font-size:18px;font-weight:700;color:#f59e0b;">${s.win_rate}%</div>
                <div style="color:#94a3b8;font-size:12px;">${s.wins}/${s.total_trades}</div>
            </div>
            <div style="background:#1e293b;padding:12px;border-radius:8px;text-align:center;">
                <div style="color:#94a3b8;font-size:11px;">最大回撤</div>
                <div style="font-size:18px;font-weight:700;color:#ef4444;">${s.max_drawdown}%</div>
            </div>
            <div style="background:#1e293b;padding:12px;border-radius:8px;text-align:center;">
                <div style="color:#94a3b8;font-size:11px;">总费用</div>
                <div style="font-size:14px;font-weight:700;color:#94a3b8;">${(s.total_fees||0).toFixed(0)}U</div>
                <div style="color:#64748b;font-size:11px;">总费用</div>
            </div>
        </div>`;

        // Equity curve
        html += '<canvas id="btChart" height="200"></canvas>';

        // Trades table
        html += '<div style="margin-top:16px;max-height:400px;overflow-y:auto;"><table style="width:100%;border-collapse:collapse;font-size:12px;">';
        html += '<tr style="background:#1e293b;position:sticky;top:0;"><th style="padding:6px;text-align:left;">日期</th><th>币种</th><th>方向</th><th>类型</th><th>杠杆</th><th>保证金</th><th>入场</th><th>出场</th><th>盈亏</th><th>ROI</th><th>费用</th><th>结果</th></tr>';
        data.trades.forEach((t,idx)=>{
            let c = t.real_pnl>=0?'#10b981':'#ef4444';
            let hitEmoji = {tp:'✅',sl:'🛑',hard_sl:'💀',timeout:'⏰'}[t.hit_type]||'❓';
            html += '<tr style="border-bottom:1px solid #1e293b;cursor:pointer;" onclick="showTradeDetail('+idx+')" title="点击查看详情">';
            html += '<td style="padding:4px 6px;color:#94a3b8;">'+t.date.substring(0,10)+'</td>';
            html += '<td style="font-weight:600;">'+t.symbol+'</td>';
            html += '<td style="color:'+(t.direction=="LONG"?"#10b981":"#ef4444")+';">'+t.direction+'</td>';
            html += '<td style="color:#94a3b8;">'+t.type+'</td>';
            html += '<td>'+t.leverage+'x</td>';
            html += '<td>'+t.margin+'U</td>';
            html += '<td>'+(t.actual_entry||t.entry)+'</td>';
            html += '<td>'+(t.exit_price||t.exit||'')+'</td>';
            html += '<td style="color:'+c+';font-weight:600;">'+(t.real_pnl>=0?'+':'')+t.real_pnl.toFixed(1)+'U</td>';
            html += '<td style="color:'+c+';">'+(t.pnl_pct>=0?'+':'')+t.pnl_pct.toFixed(1)+'%</td>';
            html += '<td style="color:#64748b;">'+(t.total_fees||0)+'U</td>';
            html += '<td>'+hitEmoji+'</td>';
            html += '</tr>';
        });
        html += '</table></div>';

        document.getElementById('backtestContent').innerHTML = html;

        // Draw equity curve
        let labels = data.trades.map(t=>t.date.substring(5,10));
        let equityData = data.trades.map(t=>t.capital_after);
        new Chart(document.getElementById('btChart'),{
            type:'line',
            data:{labels:labels,datasets:[{label:'资金曲线',data:equityData,borderColor:'#f59e0b',backgroundColor:'rgba(245,158,11,0.1)',fill:true,pointRadius:0,borderWidth:1.5}]},
            options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{ticks:{color:'#64748b',maxTicksLimit:20,font:{size:10}},grid:{display:false}},y:{ticks:{color:'#64748b',font:{size:10}},grid:{color:'rgba(100,116,139,0.1)'}}}}
        });
    }).catch(e=>{document.getElementById('backtestContent').innerHTML='<div style="color:#ef4444;padding:20px;">加载失败: '+e+'</div>';});
}
</script>
</body>
</html>
'''

# ===== Main =====

if __name__ == '__main__':
    print("=" * 50)
    print("  Signal Trader (Bitget) - vip點位策略")
    print("=" * 50)

    init_db()
    print("[DB] Initialized")

    # Init MEXC
    if init_exchange():
        print("[MEXC] Exchange ready")
    else:
        print("[Bitget] Exchange not connected - set EXCHANGE_API_KEY and EXCHANGE_API_SECRET env vars")

    # Start position monitor
    monitor_thread = threading.Thread(target=position_monitor, daemon=True)
    monitor_thread.start()

    # Start Telegram listener
    start_telegram_listener()

    # Start WebSocket price stream
    try:
        start_ws_price_stream()
        print("[WS] Price stream starting...", flush=True)
    except Exception as e:
        print(f"[WS] Failed to start: {e}", flush=True)

    app.run(host='0.0.0.0', port=5113, debug=False)
