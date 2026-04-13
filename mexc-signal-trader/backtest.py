#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
LUKE Signal Backtest Engine
- 回测 LUKE加密集中营策略群 信号
- 本金 10,000 USDT，从 2026-01-01 到 2026-04-11
- 大币 400U/50-100x, 小币 200U/10-30x
- 按信号 SL/TP 止损止盈，硬止损 -50% ROI
- OCR 图片提取杠杆和盈利信息
"""

import json
import os
import re
import sys
import requests
from datetime import datetime, timezone, timedelta

# Try OCR
try:
    import pytesseract
    from PIL import Image
    HAS_OCR = True
except:
    HAS_OCR = False
    print("[WARN] pytesseract/PIL not available, skipping OCR")

# ===== Config =====
INITIAL_CAPITAL = 10000
MAJOR_COINS = ['BTC', 'ETH', 'SOL', 'XRP', 'BNB', 'ADA', 'DOGE', 'AVAX', 'DOT', 'LINK',
               'XAG', 'MATIC', 'UNI', 'LTC', 'ATOM', 'FIL', 'APT', 'ARB', 'OP', 'SUI']
FEE_RATE = 0.0006  # 0.06% per side
FUNDING_RATE = 0.0001  # 0.01% per 8h
HARD_SL_PCT = -50  # -50% ROI

# ===== Signal Parser =====
def parse_luke_signal(text):
    """Parse LUKE signal format"""
    if not text or '標的' not in text:
        return None

    sym_match = re.search(r'標的[：:]\s*(\d*[A-Za-z]{2,10})', text)
    if not sym_match:
        return None
    symbol = sym_match.group(1).upper()

    direction = None
    if any(w in text for w in ['空单', '空單', '做空', '開空', '开空', '空']):
        direction = 'SHORT'
    elif any(w in text for w in ['多单', '多單', '做多', '開多', '开多', '多']):
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
        'symbol': symbol,
        'direction': direction,
        'entry_price': entry,
        'stop_loss': sl,
        'take_profit': tp,
    }

def ocr_extract_info(photo_path):
    """OCR screenshot to extract coin, leverage, PnL"""
    if not HAS_OCR or not photo_path or not os.path.exists(photo_path):
        return None
    try:
        img = Image.open(photo_path)
        text = pytesseract.image_to_string(img)

        result = {}
        # Symbol: XXXUSDT
        sym_match = re.findall(r'(\d*[A-Z]{2,10})USDT', text)
        if sym_match:
            result['symbol'] = sym_match[0]

        # Leverage: XXx
        lev_match = re.search(r'(\d+)x', text.lower())
        if lev_match:
            result['leverage'] = int(lev_match.group(1))

        # PnL percentage
        pnl_match = re.search(r'([\d,]+\.?\d*)\s*%', text)
        if pnl_match:
            result['pnl_pct'] = float(pnl_match.group(1).replace(',', ''))

        # Direction
        if '做多' in text:
            result['direction'] = 'LONG'
        elif '做空' in text:
            result['direction'] = 'SHORT'

        # Prices
        prices = re.findall(r'(\d+\.?\d+)', text)
        if len(prices) >= 2:
            result['prices'] = [float(p) for p in prices[:5]]

        return result if result else None
    except Exception as e:
        return None

def get_historical_price(symbol, timestamp_str):
    """Get price at a specific time using Binance klines API"""
    try:
        sym = symbol.upper().replace('/', '').replace('_', '')
        if not sym.endswith('USDT'):
            sym += 'USDT'

        dt = datetime.strptime(timestamp_str, '%Y-%m-%d %H:%M:%S')
        ts_ms = int(dt.replace(tzinfo=timezone.utc).timestamp() * 1000)

        # Get 1m candle at that time
        r = requests.get('https://api.binance.com/api/v3/klines', params={
            'symbol': sym,
            'interval': '1m',
            'startTime': ts_ms,
            'limit': 1
        }, timeout=10)
        data = r.json()
        if data and len(data) > 0:
            return float(data[0][4])  # close price
    except:
        pass
    return None

def check_sl_tp_hit(symbol, direction, entry, sl, tp, start_time_str, max_hours=72):
    """Check if SL or TP was hit within max_hours after entry using hourly candles"""
    try:
        sym = symbol.upper().replace('/', '').replace('_', '')
        if not sym.endswith('USDT'):
            sym += 'USDT'

        dt = datetime.strptime(start_time_str, '%Y-%m-%d %H:%M:%S')
        ts_ms = int(dt.replace(tzinfo=timezone.utc).timestamp() * 1000)

        # Get hourly candles
        r = requests.get('https://api.binance.com/api/v3/klines', params={
            'symbol': sym,
            'interval': '1h',
            'startTime': ts_ms,
            'limit': min(max_hours, 500)
        }, timeout=10)
        candles = r.json()

        for candle in candles:
            high = float(candle[2])
            low = float(candle[3])
            close_time = datetime.fromtimestamp(candle[6] / 1000, tz=timezone.utc)
            hours_held = (close_time - dt.replace(tzinfo=timezone.utc)).total_seconds() / 3600

            if direction == 'LONG':
                if sl and low <= sl:
                    return {'hit': 'sl', 'price': sl, 'hours': hours_held}
                if tp and high >= tp:
                    return {'hit': 'tp', 'price': tp, 'hours': hours_held}
            else:
                if sl and high >= sl:
                    return {'hit': 'sl', 'price': sl, 'hours': hours_held}
                if tp and low <= tp:
                    return {'hit': 'tp', 'price': tp, 'hours': hours_held}

        # Neither hit, use last candle close
        if candles:
            last_close = float(candles[-1][4])
            return {'hit': 'timeout', 'price': last_close, 'hours': max_hours}

    except Exception as e:
        pass
    return None

def run_backtest(messages_file, images_dir):
    """Run full backtest on LUKE signals"""
    with open(messages_file, 'r') as f:
        messages = json.load(f)

    # Sort by date ascending
    messages.sort(key=lambda m: m['date'])

    print("=" * 70)
    print("  LUKE 信号回测 (2026-01-01 → 2026-04-11)")
    print(f"  本金: {INITIAL_CAPITAL} USDT")
    print("=" * 70)

    # Phase 1: Parse all signals
    signals = []
    for msg in messages:
        sig = parse_luke_signal(msg['text'])
        if sig:
            sig['date'] = msg['date']
            sig['msg_id'] = msg['id']
            sig['raw'] = msg['text'][:100]
            signals.append(sig)

    print(f"\n总信号数: {len(signals)}")

    # Phase 2: OCR only profit/result screenshots (with keywords), limit to save time
    ocr_results = []
    seen_symbols_ocr = set()
    tp_keywords = ['tp', 'TP', '止盈', '翻倍', '倍拿下', '减仓', '減倉', '止損', '止损']
    photo_msgs = [m for m in messages if m['has_photo'] and m['photo_path']
                  and any(k in (m.get('text') or '') for k in tp_keywords)]
    print(f"截图总数: {len([m for m in messages if m['has_photo']])}, 有关键词的: {len(photo_msgs)}")

    for i, msg in enumerate(photo_msgs):
        info = ocr_extract_info(msg['photo_path'])
        if info and info.get('symbol'):
            sym = info['symbol']
            if sym not in seen_symbols_ocr:
                seen_symbols_ocr.add(sym)
                info['date'] = msg['date']
                info['msg_id'] = msg['id']
                info['text'] = msg.get('text', '')[:50]
                ocr_results.append(info)
        if (i+1) % 50 == 0:
            print(f"  OCR进度: {i+1}/{len(photo_msgs)}", flush=True)

    print(f"OCR 识别截图: {len(ocr_results)} (去重后)")

    # Phase 3: Simulate trades
    capital = INITIAL_CAPITAL
    trades = []
    open_positions = {}  # symbol -> trade
    total_fees = 0
    total_funding = 0

    print(f"\n{'='*70}")
    print("  交易模拟")
    print(f"{'='*70}\n")

    for sig in signals:
        symbol = sig['symbol']
        direction = sig['direction']
        entry = sig['entry_price']
        sl = sig['stop_loss']
        tp = sig['take_profit']
        date = sig['date']

        # Skip if already have position in this symbol
        if symbol in open_positions:
            continue

        # Get actual price at signal time
        if not entry:
            entry = get_historical_price(symbol, date)
        if not entry:
            continue

        # Classify coin
        is_major = symbol in MAJOR_COINS

        if is_major:
            position_size = 400
            lev_min, lev_max = 50, 100
        else:
            position_size = 200
            lev_min, lev_max = 10, 30

        # Skip if not enough capital
        if capital < position_size:
            continue

        # Dynamic leverage
        if entry and sl and entry > 0:
            sl_dist = abs(entry - sl) / entry * 100
            if sl_dist < 1:
                leverage = 100
            elif sl_dist < 2:
                leverage = 75
            elif sl_dist < 3:
                leverage = 50
            elif sl_dist < 5:
                leverage = 30
            else:
                leverage = 20
            leverage = max(min(leverage, lev_max), lev_min)
        else:
            leverage = lev_min
            # Set default SL/TP
            sl_pct = 0.03 if is_major else 0.05
            tp_pct = 0.06 if is_major else 0.10
            if direction == 'LONG':
                sl = round(entry * (1 - sl_pct), 6) if not sl else sl
                tp = round(entry * (1 + tp_pct), 6) if not tp else tp
            else:
                sl = round(entry * (1 + sl_pct), 6) if not sl else sl
                tp = round(entry * (1 - tp_pct), 6) if not tp else tp

        notional = position_size * leverage
        qty = notional / entry

        # Check SL/TP hit
        result = check_sl_tp_hit(symbol, direction, entry, sl, tp, date)
        if not result:
            continue

        exit_price = result['price']
        hours_held = result['hours']
        hit_type = result['hit']

        # Calculate PnL
        if direction == 'LONG':
            pnl_pct = ((exit_price - entry) / entry) * leverage * 100
        else:
            pnl_pct = ((entry - exit_price) / entry) * leverage * 100

        # Hard stop check
        if pnl_pct < HARD_SL_PCT:
            pnl_pct = HARD_SL_PCT
            hit_type = 'hard_sl'

        pnl = position_size * (pnl_pct / 100)

        # Fees
        trading_fees = notional * FEE_RATE * 2
        funding_fees = notional * FUNDING_RATE * (hours_held / 8)
        fees = trading_fees + funding_fees
        real_pnl = pnl - fees

        capital += real_pnl
        total_fees += trading_fees
        total_funding += funding_fees

        trade = {
            'date': date,
            'symbol': symbol,
            'direction': direction,
            'type': 'Major' if is_major else 'Small',
            'entry': entry,
            'exit': exit_price,
            'sl': sl,
            'tp': tp,
            'leverage': leverage,
            'margin': position_size,
            'notional': round(notional, 2),
            'pnl_pct': round(pnl_pct, 2),
            'pnl': round(pnl, 2),
            'trading_fees': round(trading_fees, 2),
            'funding_fees': round(funding_fees, 2),
            'real_pnl': round(real_pnl, 2),
            'hours_held': round(hours_held, 1),
            'hit_type': hit_type,
            'capital_after': round(capital, 2),
        }
        trades.append(trade)

        emoji = '✅' if real_pnl > 0 else '❌'
        print(f"{emoji} {date[:10]} {symbol:>10} {direction:>5} {leverage:>3}x | "
              f"entry={entry:<10} exit={exit_price:<10} | "
              f"PnL={real_pnl:>+8.2f}U ({pnl_pct:>+6.1f}%) | "
              f"Capital={capital:>10.2f}U [{hit_type}]")

        import time
        time.sleep(0.2)  # Rate limit for Binance API

    # ===== Summary =====
    print(f"\n{'='*70}")
    print("  回测总结")
    print(f"{'='*70}")

    wins = [t for t in trades if t['real_pnl'] > 0]
    losses = [t for t in trades if t['real_pnl'] <= 0]
    major_trades = [t for t in trades if t['type'] == 'Major']
    small_trades = [t for t in trades if t['type'] == 'Small']

    total_pnl = sum(t['real_pnl'] for t in trades)
    max_drawdown = 0
    peak = INITIAL_CAPITAL
    for t in trades:
        if t['capital_after'] > peak:
            peak = t['capital_after']
        dd = (peak - t['capital_after']) / peak * 100
        if dd > max_drawdown:
            max_drawdown = dd

    print(f"\n📊 基本统计:")
    print(f"  初始本金: {INITIAL_CAPITAL:,.2f} USDT")
    print(f"  最终资金: {capital:,.2f} USDT")
    print(f"  总盈亏: {total_pnl:+,.2f} USDT ({total_pnl/INITIAL_CAPITAL*100:+.1f}%)")
    print(f"  总交易: {len(trades)} 笔")
    print(f"  胜率: {len(wins)}/{len(trades)} ({len(wins)/len(trades)*100:.1f}%)" if trades else "  胜率: N/A")
    print(f"  最大回撤: {max_drawdown:.1f}%")

    print(f"\n💰 费用:")
    print(f"  交易手续费: {total_fees:,.2f} USDT")
    print(f"  资金费/隔夜费: {total_funding:,.2f} USDT")
    print(f"  总费用: {total_fees+total_funding:,.2f} USDT")

    if major_trades:
        major_pnl = sum(t['real_pnl'] for t in major_trades)
        major_wins = len([t for t in major_trades if t['real_pnl'] > 0])
        print(f"\n🔵 大币统计: {len(major_trades)}笔, 胜率{major_wins}/{len(major_trades)}, PnL={major_pnl:+,.2f}U")

    if small_trades:
        small_pnl = sum(t['real_pnl'] for t in small_trades)
        small_wins = len([t for t in small_trades if t['real_pnl'] > 0])
        print(f"🟡 小币统计: {len(small_trades)}笔, 胜率{small_wins}/{len(small_trades)}, PnL={small_pnl:+,.2f}U")

    print(f"\n📈 止盈/止损分布:")
    tp_count = len([t for t in trades if t['hit_type'] == 'tp'])
    sl_count = len([t for t in trades if t['hit_type'] == 'sl'])
    hard_sl_count = len([t for t in trades if t['hit_type'] == 'hard_sl'])
    timeout_count = len([t for t in trades if t['hit_type'] == 'timeout'])
    print(f"  止盈: {tp_count} | 止损: {sl_count} | 硬止损: {hard_sl_count} | 超时: {timeout_count}")

    # Top 5 best and worst
    if trades:
        sorted_trades = sorted(trades, key=lambda t: t['real_pnl'], reverse=True)
        print(f"\n🏆 Top 5 盈利:")
        for t in sorted_trades[:5]:
            print(f"  {t['date'][:10]} {t['symbol']:>10} {t['direction']:>5} {t['leverage']}x → {t['real_pnl']:+.2f}U ({t['pnl_pct']:+.1f}%)")
        print(f"\n💀 Top 5 亏损:")
        for t in sorted_trades[-5:]:
            print(f"  {t['date'][:10]} {t['symbol']:>10} {t['direction']:>5} {t['leverage']}x → {t['real_pnl']:+.2f}U ({t['pnl_pct']:+.1f}%)")

    # OCR results summary
    if ocr_results:
        print(f"\n📷 OCR 截图分析 ({len(ocr_results)} 张去重):")
        for r in ocr_results[:20]:
            lev = r.get('leverage', '?')
            pnl = r.get('pnl_pct', '?')
            d = r.get('direction', '?')
            print(f"  {r['date'][:10]} {r['symbol']:>10} {d:>5} {lev}x PnL={pnl}% | {r['text']}")

    # Save results
    results = {
        'summary': {
            'initial_capital': INITIAL_CAPITAL,
            'final_capital': round(capital, 2),
            'total_pnl': round(total_pnl, 2),
            'total_pnl_pct': round(total_pnl / INITIAL_CAPITAL * 100, 2),
            'total_trades': len(trades),
            'wins': len(wins),
            'losses': len(losses),
            'win_rate': round(len(wins) / len(trades) * 100, 2) if trades else 0,
            'max_drawdown': round(max_drawdown, 2),
            'total_trading_fees': round(total_fees, 2),
            'total_funding_fees': round(total_funding, 2),
        },
        'trades': trades,
        'ocr_results': ocr_results,
    }

    output_file = os.path.join(os.path.dirname(messages_file), 'backtest_results.json')
    with open(output_file, 'w') as f:
        json.dump(results, f, ensure_ascii=False, indent=2)
    print(f"\n结果已保存: {output_file}")

    return results

if __name__ == '__main__':
    msgs_file = sys.argv[1] if len(sys.argv) > 1 else '/opt/mexc-signal-trader/backtest_messages.json'
    imgs_dir = sys.argv[2] if len(sys.argv) > 2 else '/opt/mexc-signal-trader/backtest_images'
    run_backtest(msgs_file, imgs_dir)
