#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
回测引擎 - Backtesting Engine
用历史K线数据模拟 paper_trader.py 的交易策略
纯函数，不依赖网络/数据库
"""

from datetime import datetime, timezone


def calculate_rsi(prices, period=14):
    """计算RSI"""
    if len(prices) < period:
        return 50

    deltas = [prices[i] - prices[i-1] for i in range(1, len(prices))]
    gains = [d if d > 0 else 0 for d in deltas]
    losses = [-d if d < 0 else 0 for d in deltas]

    avg_gain = sum(gains[-period:]) / period
    avg_loss = sum(losses[-period:]) / period

    if avg_loss == 0:
        return 100

    rs = avg_gain / avg_loss
    rsi = 100 - (100 / (1 + rs))
    return rsi


def calculate_atr_from_candles(candles, period=14):
    """从K线数据计算ATR"""
    if len(candles) < period + 1:
        return None, None

    true_ranges = []
    for i in range(1, len(candles)):
        high = candles[i]['high']
        low = candles[i]['low']
        prev_close = candles[i-1]['close']
        tr = max(high - low, abs(high - prev_close), abs(low - prev_close))
        true_ranges.append(tr)

    atr = sum(true_ranges[-period:]) / period
    current_price = candles[-1]['close']
    atr_pct = (atr / current_price) * 100 if current_price > 0 else 0
    return atr, atr_pct


def get_dynamic_stop_pct(atr_pct, multiplier=2.0, range_min=0.03, range_max=0.08):
    """根据ATR获取动态止损百分比"""
    if atr_pct is None:
        return 0.02, 'unknown'

    stop_pct = max(range_min, min(range_max, atr_pct * multiplier / 100))

    if atr_pct > 3:
        volatility = 'high'
    elif atr_pct > 1.5:
        volatility = 'medium'
    else:
        volatility = 'low'

    return stop_pct, volatility


def analyze_signal(candles):
    """分析交易信号（0-100分），从K线数据"""
    if len(candles) < 50:
        return 0, None

    closes = [c['close'] for c in candles]
    volumes = [c['volume'] for c in candles]
    highs = [c['high'] for c in candles]
    lows = [c['low'] for c in candles]

    current_price = closes[-1]

    votes = {'LONG': 0, 'SHORT': 0}

    # 1. RSI (30分)
    rsi = calculate_rsi(closes)
    if rsi < 30:
        rsi_score = 30
        votes['LONG'] += 1
    elif rsi > 70:
        rsi_score = 30
        votes['SHORT'] += 1
    elif rsi < 45:
        rsi_score = 15
        votes['LONG'] += 1
    elif rsi > 55:
        rsi_score = 15
        votes['SHORT'] += 1
    else:
        rsi_score = 5

    # 2. 趋势 (30分)
    ma7 = sum(closes[-7:]) / 7
    ma20 = sum(closes[-20:]) / 20
    ma50 = sum(closes[-50:]) / 50

    if current_price > ma7 > ma20 > ma50:
        trend_score = 30
        votes['LONG'] += 2
    elif current_price < ma7 < ma20 < ma50:
        trend_score = 30
        votes['SHORT'] += 2
    elif current_price > ma7 > ma20:
        trend_score = 15
        votes['LONG'] += 1
    elif current_price < ma7 < ma20:
        trend_score = 15
        votes['SHORT'] += 1
    else:
        trend_score = 5

    # 3. 成交量 (20分)
    avg_volume = sum(volumes[-20:]) / 20
    recent_volume = volumes[-1]
    volume_ratio = recent_volume / avg_volume if avg_volume > 0 else 1

    if volume_ratio > 1.5:
        volume_score = 20
    elif volume_ratio > 1.2:
        volume_score = 15
    elif volume_ratio > 1:
        volume_score = 10
    else:
        volume_score = 5

    # 4. 价格位置 (20分)
    high_50 = max(highs[-50:])
    low_50 = min(lows[-50:])
    price_position = (current_price - low_50) / (high_50 - low_50) if high_50 > low_50 else 0.5

    if price_position < 0.2:
        position_score = 20
        votes['LONG'] += 1
    elif price_position > 0.8:
        position_score = 20
        votes['SHORT'] += 1
    elif price_position < 0.35:
        position_score = 10
        votes['LONG'] += 1
    elif price_position > 0.65:
        position_score = 10
        votes['SHORT'] += 1
    else:
        position_score = 5

    # 方向投票
    if votes['LONG'] > votes['SHORT']:
        direction = 'LONG'
    elif votes['SHORT'] > votes['LONG']:
        direction = 'SHORT'
    else:
        direction = 'LONG' if rsi < 50 else 'SHORT'

    total_score = rsi_score + trend_score + volume_score + position_score

    # RSI/趋势冲突惩罚
    rsi_dir = 'LONG' if rsi < 50 else 'SHORT'
    trend_dir = 'LONG' if current_price > ma20 else 'SHORT'
    if rsi_dir != trend_dir:
        total_score = int(total_score * 0.85)

    analysis = {
        'price': current_price,
        'rsi': rsi,
        'ma7': ma7,
        'ma20': ma20,
        'ma50': ma50,
        'volume_ratio': volume_ratio,
        'price_position': price_position,
        'direction': direction,
        'score': total_score
    }

    return total_score, analysis


def calculate_position_size(score, available, max_leverage=5, high_score_leverage=None, dynamic_leverage=False):
    """根据信号强度计算仓位大小和杠杆
    high_score_leverage: 85+评分专用杠杆上限(None则用max_leverage)
    dynamic_leverage: v4.3动态杠杆模式
    """
    if dynamic_leverage:
        # v4.3 动态杠杆：根据评分调整
        if score >= 85:
            size = min(400, available * 0.25)
            leverage = min(10, max_leverage)  # 高分10x
        elif score >= 75:
            size = min(300, available * 0.2)
            leverage = min(8, max_leverage)   # 中高8x
        elif score >= 65:
            size = min(200, available * 0.15)
            leverage = min(5, max_leverage)   # 标准5x
        elif score >= 60:
            size = min(150, available * 0.1)
            leverage = min(3, max_leverage)   # 刚过门槛3x
        else:
            return 0, 3
    else:
        # 旧版逻辑
        hs_lev = high_score_leverage if high_score_leverage else max_leverage
        if score >= 85:
            size = min(400, available * 0.25)
            leverage = min(5, hs_lev)
        elif score >= 75:
            size = min(300, available * 0.2)
            leverage = min(3, max_leverage)
        elif score >= 70:
            size = min(200, available * 0.15)
            leverage = min(3, max_leverage)
        elif score >= 60:
            size = min(150, available * 0.1)
            leverage = min(3, max_leverage)
        elif score >= 55:
            size = min(100, available * 0.08)
            leverage = min(3, max_leverage)
        else:
            return 0, max_leverage

    return size, leverage


def calculate_dynamic_tpsl(score, trend_strength, direction_matches_trend, fixed_tp_mode=False):
    """动态止盈止损

    Args:
        score: 信号评分 (60-100)
        trend_strength: 趋势强度 (0-3, 0=无趋势, 3=强趋势)
        direction_matches_trend: 方向是否顺势 (True/False)
        fixed_tp_mode: True=固定止盈(v4.3.1), False=移动止盈(v4.3)

    Returns:
        (roi_stop_loss, roi_take_profit, roi_trailing_start, roi_trailing_distance)
    """
    if fixed_tp_mode:
        # v4.3.1 固定止盈目标
        if direction_matches_trend and trend_strength >= 2:
            return -8, 12, 0, 0  # 强趋势顺势 1.5:1
        elif direction_matches_trend and trend_strength >= 1:
            return -8, 10, 0, 0  # 普通顺势 1.25:1
        elif not direction_matches_trend:
            return -5, 6, 0, 0   # 逆势 1.2:1
        else:
            return -6, 8, 0, 0   # 震荡/弱势 1.33:1
    else:
        # v4.3 移动止盈
        if direction_matches_trend and trend_strength >= 2:
            return -8, 0, 8, 4   # 强趋势顺势：让利润跑
        elif direction_matches_trend and trend_strength >= 1:
            return -8, 0, 6, 3   # 普通顺势
        elif not direction_matches_trend:
            return -5, 0, 4, 2   # 逆势：保守快出
        else:
            return -6, 0, 5, 3   # 震荡/弱势


def calculate_dynamic_leverage_v431(score, max_lev=15):
    """动态杠杆 — v4.3.1 激进版"""
    if score >= 85:
        return min(15, max_lev)  # 高分15x
    elif score >= 75:
        return min(12, max_lev)  # 中高12x
    elif score >= 65:
        return min(8, max_lev)   # 标准8x
    else:
        return min(5, max_lev)   # 低分5x


def run_backtest(candles_1h, config):
    """
    主回测函数 - 逐K线模拟交易

    candles_1h: list of dicts {time, open, high, low, close, volume}
    config: {initial_capital, min_score, fee_rate, max_positions, max_same_direction}

    returns: {trades, equity_curve, summary}
    """
    initial_capital = config.get('initial_capital', 2000)
    min_score = config.get('min_score', 70)
    fee_rate = config.get('fee_rate', 0.0005)
    max_positions = config.get('max_positions', 3)
    max_same_dir = config.get('max_same_direction', 2)
    cooldown_bars = config.get('cooldown', 12)
    max_leverage = config.get('max_leverage', 5)
    enable_trend_filter = config.get('enable_trend_filter', True)
    ma_slope_threshold = config.get('ma_slope_threshold', 0.01)
    long_min_score = config.get('long_min_score', min_score)  # LONG方向最低评分(v4.1)
    long_ma_slope_threshold = config.get('long_ma_slope_threshold', ma_slope_threshold)  # LONG方向MA斜率阈值(v4.1)
    high_score_leverage = config.get('high_score_leverage', None)  # 85+评分专用杠杆(v4.2)
    dynamic_leverage = config.get('dynamic_leverage', False)  # v4.3 动态杠杆
    dynamic_leverage_v431 = config.get('dynamic_leverage_v431', False)  # v4.3.1 激进杠杆
    dynamic_tpsl = config.get('dynamic_tpsl', False)  # v4.3 动态止盈止损(移动止盈)
    fixed_tp_mode = config.get('fixed_tp_mode', False)  # v4.3.1 固定止盈模式
    # ROI模式参数（基于本金盈亏%）— 作为默认值，dynamic_tpsl开启时会被覆盖
    roi_stop_loss = config.get('roi_stop_loss', -8)        # 止损: ROI跌到-8%平仓
    roi_trailing_start = config.get('roi_trailing_start', 5)  # 启动移动止盈: ROI达+5%
    roi_trailing_distance = config.get('roi_trailing_distance', 3)  # 回撤距离: 从峰值回撤3%平仓

    capital = initial_capital
    positions = {}  # symbol -> position dict
    trades = []
    equity_curve = []
    cooldown_until = -1  # bar index until which we wait
    trade_id = 0
    bankrupt = False

    # 每100根K线记录一次资金曲线（避免数据点太多）
    curve_interval = max(1, len(candles_1h) // 500)

    for i in range(100, len(candles_1h)):
        candle = candles_1h[i]

        if capital <= 0:
            bankrupt = True
            break

        # === 检查所有持仓 ===
        symbols_to_close = []
        for sym, pos in positions.items():
            close_price, reason = _check_position_bar(pos, candle)
            if close_price is not None:
                symbols_to_close.append((sym, close_price, reason))

        for sym, close_price, reason in symbols_to_close:
            pos = positions[sym]
            pnl, fee, funding = _calc_pnl(pos, close_price, fee_rate, i - pos['bar_index'])
            capital += pnl
            trade_id += 1
            # 计算止盈触发价(移动止盈启动价)
            ep = pos['entry_price']
            lev = pos['leverage']
            ts = pos.get('roi_trailing_start', roi_trailing_start)
            tp_pct = ts / (lev * 100)
            if pos['direction'] == 'LONG':
                tp_trigger = ep * (1 + tp_pct)
            else:
                tp_trigger = ep * (1 - tp_pct)
            trades.append({
                'trade_id': trade_id,
                'direction': pos['direction'],
                'entry_price': ep,
                'exit_price': close_price,
                'amount': pos['amount'],
                'leverage': pos['leverage'],
                'pnl': round(pnl, 2),
                'roi': round((pnl / pos['amount']) * 100, 1) if pos['amount'] > 0 else 0,
                'fee': round(fee, 2),
                'funding_fee': round(funding, 2),
                'reason': reason,
                'entry_time': pos['entry_time'],
                'exit_time': _ts_to_str(candle['time']),
                'score': pos['score'],
                'stop_moves': pos.get('stop_move_count', 0),
                'stop_loss': round(pos['stop_loss'], 6),
                'tp_trigger': round(tp_trigger, 6)
            })
            del positions[sym]
            cooldown_until = i + cooldown_bars

        # === 记录资金曲线 ===
        if i % curve_interval == 0:
            # 计算浮动盈亏
            floating_pnl = 0
            for sym, pos in positions.items():
                fp, _, _ = _calc_pnl(pos, candle['close'], fee_rate, i - pos['bar_index'])
                floating_pnl += fp
            equity_curve.append({
                'time': candle['time'],
                'capital': round(capital + floating_pnl, 2)
            })

        # === 开仓逻辑 ===
        if i <= cooldown_until:
            continue
        if len(positions) >= max_positions:
            continue
        if capital <= 50:
            continue

        # 信号分析
        lookback = candles_1h[max(0, i-99):i+1]
        score, analysis = analyze_signal(lookback)

        if score < min_score or analysis is None:
            continue

        direction = analysis['direction']

        # v4.1: LONG方向使用更高的最低评分
        if direction == 'LONG' and score < long_min_score:
            continue

        # 趋势过滤：MA20斜率与方向冲突时跳过
        if enable_trend_filter and len(lookback) >= 25:
            ma20_now = sum(c['close'] for c in lookback[-20:]) / 20
            ma20_prev = sum(c['close'] for c in lookback[-25:-5]) / 20
            ma20_slope = (ma20_now - ma20_prev) / ma20_prev
            # v4.1: LONG方向使用更严格的斜率阈值
            long_threshold = long_ma_slope_threshold
            if direction == 'LONG' and ma20_slope < -long_threshold:
                continue
            if direction == 'SHORT' and ma20_slope > ma_slope_threshold:
                continue

        # 方向限制
        long_count = sum(1 for p in positions.values() if p['direction'] == 'LONG')
        short_count = sum(1 for p in positions.values() if p['direction'] == 'SHORT')
        if direction == 'LONG' and long_count >= max_same_dir:
            continue
        if direction == 'SHORT' and short_count >= max_same_dir:
            continue

        # 计算仓位
        used = sum(p['amount'] for p in positions.values())
        available = capital - used
        if available < 50:
            continue

        amount, leverage = calculate_position_size(score, available, max_leverage, high_score_leverage, dynamic_leverage)

        # v4.3.1 激进杠杆覆盖
        if dynamic_leverage_v431:
            leverage = calculate_dynamic_leverage_v431(score, max_leverage)

        if amount < 50:
            continue

        entry_price = analysis['price']

        # v4.3/v4.3.1 动态止盈止损
        if dynamic_tpsl or fixed_tp_mode:
            trend_strength = analysis.get('trend_strength', 1)
            ma_trend = analysis.get('ma_trend', 'neutral')
            direction_matches_trend = (
                (direction == 'LONG' and ma_trend == 'up') or
                (direction == 'SHORT' and ma_trend == 'down')
            )
            pos_roi_stop, pos_roi_tp, pos_roi_trail_start, pos_roi_trail_dist = calculate_dynamic_tpsl(
                score, trend_strength, direction_matches_trend, fixed_tp_mode
            )
        else:
            pos_roi_stop = roi_stop_loss
            pos_roi_tp = 0  # 0 表示使用移动止盈模式
            pos_roi_trail_start = roi_trailing_start
            pos_roi_trail_dist = roi_trailing_distance

        # ROI模式：按ROI反算止损价格（用于显示/记录）
        stop_price_pct = pos_roi_stop / (leverage * 100)
        if direction == 'LONG':
            stop_loss = entry_price * (1 + stop_price_pct)  # 负值所以是减
        else:
            stop_loss = entry_price * (1 - stop_price_pct)  # 负值所以是加

        # 用 bar index 作为 key（单币种回测）
        pos_key = f"pos_{trade_id + 1}"
        positions[pos_key] = {
            'direction': direction,
            'entry_price': entry_price,
            'amount': amount,
            'leverage': leverage,
            'stop_loss': stop_loss,
            'roi_stop_loss': pos_roi_stop,
            'roi_take_profit': pos_roi_tp,  # 固定止盈目标，0表示用移动止盈
            'roi_trailing_start': pos_roi_trail_start,
            'roi_trailing_distance': pos_roi_trail_dist,
            'peak_roi': 0,
            'entry_time': _ts_to_str(candle['time']),
            'score': score,
            'stop_move_count': 0,
            'bar_index': i
        }

    # === 强制平仓剩余持仓 ===
    if candles_1h and positions:
        last_candle = candles_1h[-1]
        for sym, pos in list(positions.items()):
            pnl, fee, funding = _calc_pnl(pos, last_candle['close'], fee_rate,
                                           len(candles_1h) - 1 - pos['bar_index'])
            capital += pnl
            trade_id += 1
            ep = pos['entry_price']
            lev = pos['leverage']
            ts = pos.get('roi_trailing_start', roi_trailing_start)
            tp_pct = ts / (lev * 100)
            if pos['direction'] == 'LONG':
                tp_trigger = ep * (1 + tp_pct)
            else:
                tp_trigger = ep * (1 - tp_pct)
            trades.append({
                'trade_id': trade_id,
                'direction': pos['direction'],
                'entry_price': ep,
                'exit_price': last_candle['close'],
                'amount': pos['amount'],
                'leverage': pos['leverage'],
                'pnl': round(pnl, 2),
                'roi': round((pnl / pos['amount']) * 100, 1) if pos['amount'] > 0 else 0,
                'fee': round(fee, 2),
                'funding_fee': round(funding, 2),
                'reason': '回测结束强制平仓',
                'entry_time': pos['entry_time'],
                'exit_time': _ts_to_str(last_candle['time']),
                'score': pos['score'],
                'stop_moves': pos.get('stop_move_count', 0),
                'stop_loss': round(pos['stop_loss'], 6),
                'tp_trigger': round(tp_trigger, 6)
            })

    # 最终资金曲线点
    if candles_1h:
        equity_curve.append({
            'time': candles_1h[-1]['time'],
            'capital': round(capital, 2)
        })

    # === 统计 ===
    summary = _compute_summary(trades, initial_capital, capital, equity_curve, bankrupt)

    return {
        'trades': trades,
        'equity_curve': equity_curve,
        'summary': summary
    }


def _check_position_bar(pos, candle):
    """用一根K线的高低价检查持仓 — 支持固定止盈和移动止盈两种模式

    固定目标模式 (roi_take_profit > 0):
    - 止损: ROI跌到 roi_stop_loss 平仓
    - 止盈: ROI达到 roi_take_profit 平仓

    移动止盈模式 (roi_take_profit = 0 或未设置):
    - 止损: ROI跌到 roi_stop_loss 平仓
    - 移动止盈: ROI达到 roi_trailing_start 后开始跟踪
    - 跟踪距离: 从最高ROI回撤 roi_trailing_distance 则平仓
    """
    direction = pos['direction']
    entry_price = pos['entry_price']
    leverage = pos.get('leverage', 1)

    roi_stop = pos.get('roi_stop_loss', -8)
    roi_tp = pos.get('roi_take_profit', 0)  # 固定止盈目标，0表示用移动止盈
    roi_trail_start = pos.get('roi_trailing_start', 5)
    roi_trail_dist = pos.get('roi_trailing_distance', 3)

    high = candle['high']
    low = candle['low']

    # 计算本bar的最佳/最差ROI
    if direction == 'LONG':
        best_roi = ((high - entry_price) / entry_price) * leverage * 100
        worst_roi = ((low - entry_price) / entry_price) * leverage * 100
    else:
        best_roi = ((entry_price - low) / entry_price) * leverage * 100
        worst_roi = ((entry_price - high) / entry_price) * leverage * 100

    # 更新峰值ROI
    peak_roi = pos.get('peak_roi', 0)
    if best_roi > peak_roi:
        pos['peak_roi'] = best_roi
        peak_roi = best_roi

    # === 1. 止损检查: ROI跌到止损线 ===
    if worst_roi <= roi_stop:
        exit_pct = roi_stop / (leverage * 100)
        if direction == 'LONG':
            exit_price = entry_price * (1 + exit_pct)
        else:
            exit_price = entry_price * (1 - exit_pct)
        pos['stop_move_count'] = pos.get('stop_move_count', 0)
        return exit_price, f"触发止损 (ROI {roi_stop}%)"

    # === 2. 固定止盈模式: ROI达到目标就平仓 ===
    if roi_tp > 0 and best_roi >= roi_tp:
        exit_pct = roi_tp / (leverage * 100)
        if direction == 'LONG':
            exit_price = entry_price * (1 + exit_pct)
        else:
            exit_price = entry_price * (1 - exit_pct)
        pos['stop_move_count'] = pos.get('stop_move_count', 0) + 1
        return exit_price, f"触发止盈 (ROI +{roi_tp}%)"

    # === 3. 移动止盈模式: 峰值ROI超过启动线后，回撤超过距离就平仓 ===
    if roi_tp == 0 and peak_roi >= roi_trail_start:
        trail_exit_roi = peak_roi - roi_trail_dist
        if worst_roi <= trail_exit_roi:
            exit_pct = trail_exit_roi / (leverage * 100)
            if direction == 'LONG':
                exit_price = entry_price * (1 + exit_pct)
            else:
                exit_price = entry_price * (1 - exit_pct)
            pos['stop_move_count'] = pos.get('stop_move_count', 0) + 1
            if trail_exit_roi > 0:
                return exit_price, f"移动止盈 (ROI +{trail_exit_roi:.1f}%)"
            else:
                return exit_price, f"触发止损 (ROI {trail_exit_roi:.1f}%)"

    return None, None


def _calc_pnl(pos, exit_price, fee_rate, bars_held):
    """计算盈亏"""
    entry_price = pos['entry_price']
    amount = pos['amount']
    leverage = pos['leverage']
    direction = pos['direction']

    if direction == 'SHORT':
        pct = (entry_price - exit_price) / entry_price
    else:
        pct = (exit_price - entry_price) / entry_price

    pnl_raw = amount * pct * leverage
    fee = amount * leverage * fee_rate * 2  # 开+平
    funding = amount * leverage * 0.0001 * (bars_held / 8)  # 每8h收一次
    pnl = pnl_raw - fee - funding

    return pnl, fee, funding


def _compute_summary(trades, initial_capital, final_capital, equity_curve, bankrupt):
    """计算统计摘要"""
    total_trades = len(trades)
    win_trades = sum(1 for t in trades if t['pnl'] > 0)
    loss_trades = sum(1 for t in trades if t['pnl'] <= 0)
    win_rate = (win_trades / total_trades * 100) if total_trades > 0 else 0

    total_pnl = final_capital - initial_capital

    # 最大回撤
    max_drawdown = 0
    peak = initial_capital
    for point in equity_curve:
        if point['capital'] > peak:
            peak = point['capital']
        dd = ((peak - point['capital']) / peak) * 100 if peak > 0 else 0
        if dd > max_drawdown:
            max_drawdown = dd

    # 盈亏比
    total_win = sum(t['pnl'] for t in trades if t['pnl'] > 0)
    total_loss = abs(sum(t['pnl'] for t in trades if t['pnl'] <= 0))
    profit_factor = total_win / total_loss if total_loss > 0 else float('inf')

    avg_win = total_win / win_trades if win_trades > 0 else 0
    avg_loss = -total_loss / loss_trades if loss_trades > 0 else 0

    best_trade = max((t['pnl'] for t in trades), default=0)
    worst_trade = min((t['pnl'] for t in trades), default=0)

    return {
        'initial_capital': initial_capital,
        'final_capital': round(final_capital, 2),
        'total_pnl': round(total_pnl, 2),
        'total_trades': total_trades,
        'win_trades': win_trades,
        'loss_trades': loss_trades,
        'win_rate': round(win_rate, 1),
        'max_drawdown': round(max_drawdown, 2),
        'profit_factor': round(profit_factor, 2) if profit_factor != float('inf') else 999,
        'avg_win': round(avg_win, 2),
        'avg_loss': round(avg_loss, 2),
        'best_trade': round(best_trade, 2),
        'worst_trade': round(worst_trade, 2),
        'bankrupt': bankrupt
    }


def _ts_to_str(ts_ms):
    """毫秒时间戳转字符串"""
    dt = datetime.fromtimestamp(ts_ms / 1000, tz=timezone.utc)
    return dt.strftime('%Y-%m-%d %H:%M')
