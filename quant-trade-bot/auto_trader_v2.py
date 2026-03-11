#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
量化交易机器人 v4.0 - 动量突破策略
基于30天回测验证:
- 动量突破信号（24周期新高/新低）
- BTC大盘趋势过滤（EMA20/EMA50）
- 成交量确认（>1.3x均量）
- ATR动态止损（2.5x ATR）
- 追踪止损（2.0x ATR触发，0.5x ATR距离）
- 回测结果: 103笔, 43.7%WR, +$452, 1.77 R:R, 10.9% MaxDD
"""

import sqlite3
import ccxt
import json
import time
from datetime import datetime, date, timedelta
import os

# 项目根目录
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DB_PATH = os.path.join(os.path.dirname(SCRIPT_DIR), 'data', 'db', 'paper_trading.db')
CONFIG_PATH = os.path.join(SCRIPT_DIR, 'config', 'config.json')

# ===== 技术指标计算 =====

def calc_ema(closes, period):
    """指数移动平均线"""
    if not closes:
        return []
    mult = 2.0 / (period + 1)
    result = [closes[0]]
    for i in range(1, len(closes)):
        result.append(closes[i] * mult + result[-1] * (1 - mult))
    return result

def calc_atr(highs, lows, closes, period=14):
    """平均真实波幅"""
    trs = [highs[0] - lows[0]]
    for i in range(1, len(closes)):
        tr = max(
            highs[i] - lows[i],
            abs(highs[i] - closes[i-1]),
            abs(lows[i] - closes[i-1])
        )
        trs.append(tr)
    atrs = [None] * (period - 1)
    if len(trs) >= period:
        atrs.append(sum(trs[:period]) / period)
        for i in range(period, len(trs)):
            atrs.append((atrs[-1] * (period - 1) + trs[i]) / period)
    return atrs

def calc_rsi(closes, period=14):
    """相对强弱指标"""
    if len(closes) < period + 1:
        return [50] * len(closes)
    rsis = [50] * period
    gains = []
    losses = []
    for i in range(1, len(closes)):
        delta = closes[i] - closes[i-1]
        gains.append(max(delta, 0))
        losses.append(max(-delta, 0))
    avg_gain = sum(gains[:period]) / period
    avg_loss = sum(losses[:period]) / period
    for i in range(period, len(closes)):
        if avg_loss == 0:
            rsis.append(100)
        else:
            rs = avg_gain / avg_loss
            rsis.append(100 - 100 / (1 + rs))
        if i < len(gains):
            avg_gain = (avg_gain * (period - 1) + gains[i]) / period
            avg_loss = (avg_loss * (period - 1) + losses[i]) / period
    return rsis


class AutoTraderV4:
    def __init__(self):
        # 加载Binance配置
        with open(CONFIG_PATH, 'r') as f:
            config = json.load(f)

        # 初始化交易所
        self.exchange = ccxt.binance({
            'apiKey': config['binance']['api_key'],
            'secret': config['binance']['api_secret'],
            'enableRateLimit': True,
            'options': {'defaultType': 'future'}
        })

        # 从数据库加载配置
        self.load_config_from_db()

        # 快照计数器
        self.snapshot_counter = 0

        # 持仓价格极值 {trade_id: {'highest': x, 'lowest': x}}
        self.price_extremes = {}

        # 平仓冷却 {symbol: datetime}
        self.symbol_cooldown = {}

        # OHLCV缓存 {symbol: {'data': [...], 'time': timestamp}}
        self.ohlcv_cache = {}
        self.cache_ttl = 300  # 5分钟缓存

        # BTC趋势缓存
        self.btc_trend_cache = {'direction': None, 'strength': 0, 'time': 0}

        # ===== 策略参数（回测验证） =====
        self.lookback = 24        # 突破回看周期
        self.vol_mult = 1.3       # 成交量过滤倍数
        self.sl_atr_mult = 2.5    # 止损=ATR*2.5
        self.tp_ratio = 3.0       # 止盈=止损*3
        self.trail_gate_mult = 2.0  # 追踪触发=ATR*2.0
        self.trail_dist_mult = 0.5  # 追踪距离=ATR*0.5
        self.cooldown_hours = 12  # 同币种冷却12小时
        self.circuit_break_count = 3  # 连亏3笔触发熔断
        self.circuit_break_pause = 15  # 熔断后暂停15个周期

        # 监控币种列表（回测表现好的币种优先）
        self.watchlist = [
            'SOL/USDT', 'DOGE/USDT', 'UNI/USDT', 'XRP/USDT', 'APT/USDT',
            'LINK/USDT', 'ARB/USDT', 'ADA/USDT', 'AVAX/USDT', 'DOT/USDT',
            'ETH/USDT', 'BNB/USDT', 'ATOM/USDT', 'NEAR/USDT',
            'OP/USDT', 'SUI/USDT', 'TIA/USDT', 'SEI/USDT', 'INJ/USDT',
        ]

        print("=" * 60)
        print("量化交易机器人 v4.0 - 动量突破策略")
        print("=" * 60)
        print("资金: $%.2f | 杠杆: %dx | 最大持仓: %d" % (
            self.initial_capital, self.default_leverage, self.max_positions))
        print("策略: 动量突破 + BTC趋势过滤 + ATR动态止损")
        print("回测: 103笔, 43.7%%WR, +$452, R:R=1.77")
        print("监控: %d个币种" % len(self.watchlist))
        print("=" * 60)

    def load_config_from_db(self):
        """从account_config表加载配置"""
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        cursor.execute("SELECT key, value FROM account_config")
        config = dict(cursor.fetchall())
        conn.close()

        self.initial_capital = float(config.get('initial_capital', 2000))
        self.target_profit = float(config.get('target_profit', 3400))
        self.max_position_size = float(config.get('max_position_size', 500))
        self.max_positions = int(config.get('max_positions', 3))
        self.default_leverage = int(config.get('default_leverage', 2))
        self.fee_rate = float(config.get('fee_rate', 0.001))
        self.trading_enabled = config.get('trading_enabled', '1') == '1'

    # ===== 数据获取 =====

    def fetch_ohlcv(self, symbol, timeframe='1h', limit=100):
        """获取K线数据（带缓存）"""
        cache_key = '%s_%s' % (symbol, timeframe)
        now = time.time()

        if cache_key in self.ohlcv_cache:
            cached = self.ohlcv_cache[cache_key]
            if now - cached['time'] < self.cache_ttl:
                return cached['data']

        try:
            data = self.exchange.fetch_ohlcv(symbol, timeframe, limit=limit)
            self.ohlcv_cache[cache_key] = {'data': data, 'time': now}
            return data
        except Exception as e:
            print("  获取%s K线失败: %s" % (symbol, e))
            return None

    def get_btc_trend(self):
        """获取BTC大盘趋势（EMA20/EMA50）"""
        now = time.time()
        if now - self.btc_trend_cache['time'] < 300:  # 5分钟缓存
            return self.btc_trend_cache['direction'], self.btc_trend_cache['strength']

        data = self.fetch_ohlcv('BTC/USDT', '1h', 100)
        if not data or len(data) < 55:
            return 'neutral', 0

        closes = [c[4] for c in data]
        ema20 = calc_ema(closes, 20)
        ema50 = calc_ema(closes, 50)

        price = closes[-1]
        e20 = ema20[-1]
        e50 = ema50[-1]

        # 强上升: 价格 > EMA20 > EMA50
        if price > e20 and e20 > e50:
            direction = 'up'
            strength = 2
        # 弱上升: 价格 > EMA50
        elif price > e50:
            direction = 'up'
            strength = 1
        # 强下降: 价格 < EMA20 < EMA50
        elif price < e20 and e20 < e50:
            direction = 'down'
            strength = 2
        # 弱下降: 价格 < EMA50
        elif price < e50:
            direction = 'down'
            strength = 1
        else:
            direction = 'neutral'
            strength = 0

        self.btc_trend_cache = {
            'direction': direction,
            'strength': strength,
            'time': now
        }

        return direction, strength

    # ===== 动量突破信号检测 =====

    def scan_momentum_signals(self):
        """扫描所有币种的动量突破信号"""
        btc_direction, btc_strength = self.get_btc_trend()
        print("\nBTC趋势: %s (强度%d)" % (btc_direction, btc_strength))

        if btc_direction == 'neutral':
            print("BTC无明确方向，跳过本轮扫描")
            return []

        signals = []
        for symbol in self.watchlist:
            try:
                signal = self.check_breakout(symbol, btc_direction)
                if signal:
                    signals.append(signal)
            except Exception as e:
                pass  # 静默跳过错误
            time.sleep(0.3)  # API限速

        return signals

    def check_breakout(self, symbol, btc_direction):
        """检测单个币种的动量突破"""
        data = self.fetch_ohlcv(symbol, '1h', 100)
        if not data or len(data) < self.lookback + 30:
            return None

        closes = [c[4] for c in data]
        highs = [c[2] for c in data]
        lows = [c[3] for c in data]
        volumes = [c[5] for c in data]

        i = len(data) - 1  # 最新K线
        price = closes[i]

        # 1. 计算指标
        atr = calc_atr(highs, lows, closes, 14)
        rsi = calc_rsi(closes)
        ema20 = calc_ema(closes, 20)
        ema50 = calc_ema(closes, 50)

        if not atr[i]:
            return None

        atr_val = atr[i]

        # 2. 币种自身趋势
        coin_trend_up = price > ema50[i] and ema20[i] > ema50[i]
        coin_trend_down = price < ema50[i] and ema20[i] < ema50[i]

        # 3. 成交量确认
        avg_vol = sum(volumes[i-self.lookback:i]) / self.lookback
        vol_ok = volumes[i] > avg_vol * self.vol_mult

        if not vol_ok:
            return None

        # 4. 突破水平
        high_n = max(highs[i-self.lookback:i])
        low_n = min(lows[i-self.lookback:i])

        # 5. 计算动态止损止盈
        sl_pct = min(max(atr_val / price * 100 * self.sl_atr_mult, 2.5), 6.0)
        tp_pct = sl_pct * self.tp_ratio

        # 6. 信号判断
        signal = None

        # 做多: BTC上涨 + 币种上涨 + 突破N周期高点 + RSI合理
        if btc_direction == 'up' and coin_trend_up:
            if price > high_n and 50 < rsi[i] < 72:
                stop_loss = price * (1 - sl_pct / 100)
                take_profit = price * (1 + tp_pct / 100)
                signal = {
                    'symbol': symbol,
                    'signal': 'buy',
                    'direction': 'long',
                    'price': price,
                    'stop_loss': stop_loss,
                    'take_profit': take_profit,
                    'sl_pct': sl_pct,
                    'tp_pct': tp_pct,
                    'atr': atr_val,
                    'rsi': rsi[i],
                    'volume_ratio': volumes[i] / avg_vol,
                    'score': int(70 + min(rsi[i] - 50, 20)),  # 70-90 score range
                    'reasons': [
                        '突破%d周期高点' % self.lookback,
                        'BTC趋势向上',
                        '成交量%.1fx' % (volumes[i] / avg_vol),
                        'RSI=%.0f' % rsi[i],
                    ]
                }

        # 做空: BTC下跌 + 币种下跌 + 跌破N周期低点 + RSI合理
        elif btc_direction == 'down' and coin_trend_down:
            if price < low_n and 28 < rsi[i] < 50:
                stop_loss = price * (1 + sl_pct / 100)
                take_profit = price * (1 - tp_pct / 100)
                signal = {
                    'symbol': symbol,
                    'signal': 'sell',
                    'direction': 'short',
                    'price': price,
                    'stop_loss': stop_loss,
                    'take_profit': take_profit,
                    'sl_pct': sl_pct,
                    'tp_pct': tp_pct,
                    'atr': atr_val,
                    'rsi': rsi[i],
                    'volume_ratio': volumes[i] / avg_vol,
                    'score': int(70 + min(50 - rsi[i], 20)),
                    'reasons': [
                        '跌破%d周期低点' % self.lookback,
                        'BTC趋势向下',
                        '成交量%.1fx' % (volumes[i] / avg_vol),
                        'RSI=%.0f' % rsi[i],
                    ]
                }

        return signal

    # ===== 资金管理 =====

    def get_current_capital(self):
        """计算当前资金"""
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        cursor.execute("SELECT SUM(pnl) FROM real_trades WHERE status = 'CLOSED'")
        total_pnl = cursor.fetchone()[0] or 0
        conn.close()
        return self.initial_capital + total_pnl

    def get_margin_used(self):
        """获取当前占用的保证金"""
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        cursor.execute("SELECT SUM(amount) FROM real_trades WHERE status = 'OPEN'")
        margin = cursor.fetchone()[0] or 0
        conn.close()
        return margin

    def get_open_positions(self):
        """获取当前所有持仓"""
        conn = sqlite3.connect(DB_PATH)
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        cursor.execute("SELECT * FROM real_trades WHERE status = 'OPEN'")
        positions = [dict(row) for row in cursor.fetchall()]
        conn.close()
        return positions

    # ===== 开仓 =====

    def should_trade(self, signal):
        """判断是否应该交易"""
        if not self.trading_enabled:
            return False

        symbol = signal['symbol']

        # 冷却检查
        if symbol in self.symbol_cooldown:
            if datetime.now() < self.symbol_cooldown[symbol]:
                return False

        # 持仓数检查
        positions = self.get_open_positions()
        if len(positions) >= self.max_positions:
            return False

        # 重复持仓检查
        for pos in positions:
            if pos['symbol'] == symbol:
                return False

        # 资金检查
        available = self.get_current_capital() - self.get_margin_used()
        if available < 50:
            return False

        return True

    def open_position(self, signal):
        """开仓"""
        symbol = signal['symbol']
        price = signal['price']
        direction = signal['direction']
        stop_loss = signal['stop_loss']
        take_profit = signal['take_profit']
        score = signal['score']
        rsi = signal['rsi']
        reasons = signal['reasons']

        current_capital = self.get_current_capital()
        margin_used = self.get_margin_used()
        available = current_capital - margin_used

        # 固定15%仓位
        margin = min(available * 0.15, self.max_position_size)
        if margin < 50:
            return False

        position_value = margin * self.default_leverage
        entry_fee = position_value * self.fee_rate

        print("\n" + "=" * 60)
        print("开仓: %s %s" % (symbol, direction.upper()))
        print("  价格: $%.4f" % price)
        print("  保证金: $%.2f | 杠杆: %dx" % (margin, self.default_leverage))
        print("  止损: $%.4f (-%.1f%%)" % (stop_loss, signal['sl_pct']))
        print("  止盈: $%.4f (+%.1f%%)" % (take_profit, signal['tp_pct']))
        print("  ATR: $%.4f | RSI: %.0f | Vol: %.1fx" % (signal['atr'], rsi, signal['volume_ratio']))
        print("  原因: %s" % ', '.join(reasons))
        print("=" * 60)

        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()

        entry_time = datetime.now().isoformat()
        reason_text = ', '.join(reasons)

        cursor.execute("""
            INSERT INTO real_trades (
                symbol, direction, entry_price, amount, leverage,
                stop_loss, take_profit, entry_time, status,
                fee, score, reason, entry_score, entry_rsi, entry_trend,
                original_stop_loss, original_take_profit,
                assistant, mode
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'OPEN', ?, ?, ?, ?, ?, ?, ?, ?, '量化交易', 'paper')
        """, (
            symbol, direction, price, margin, self.default_leverage,
            stop_loss, take_profit, entry_time,
            entry_fee, score, reason_text, score, rsi,
            'bullish' if direction == 'long' else 'bearish',
            stop_loss, take_profit
        ))

        trade_id = cursor.lastrowid
        self.price_extremes[trade_id] = {'highest': price, 'lowest': price}

        # 记录交易信号
        cursor.execute("""
            INSERT INTO trade_signals (
                symbol, signal_type, score, rsi, trend, reasons, executed, trade_id
            ) VALUES (?, ?, ?, ?, ?, ?, 1, ?)
        """, (symbol, signal['signal'], score, rsi,
              'bullish' if direction == 'long' else 'bearish',
              json.dumps(reasons), trade_id))

        conn.commit()
        conn.close()

        print("开仓成功!")
        return True

    # ===== 追踪止损（ATR动态版） =====

    def update_trailing_stop(self, trade, current_price):
        """追踪止损 - ATR动态版"""
        trade_id = trade['id']
        direction = trade['direction']
        entry_price = trade['entry_price']
        current_sl = trade['stop_loss']
        current_tp = trade['take_profit']
        symbol = trade['symbol']

        # 获取ATR用于计算追踪参数
        data = self.fetch_ohlcv(symbol, '1h', 30)
        if data and len(data) > 15:
            closes = [c[4] for c in data]
            highs = [c[2] for c in data]
            lows = [c[3] for c in data]
            atr_list = calc_atr(highs, lows, closes, 14)
            atr_val = atr_list[-1] if atr_list[-1] else current_price * 0.02
        else:
            atr_val = current_price * 0.02

        # 追踪触发门槛和距离
        trail_gate_pct = min(max(atr_val / current_price * 100 * self.trail_gate_mult, 2.0), 6.0)
        trail_dist_pct = min(max(atr_val / current_price * 100 * self.trail_dist_mult, 0.5), 2.0)

        # 计算盈利
        if direction == 'long':
            profit_pct = (current_price - entry_price) / entry_price * 100
        else:
            profit_pct = (entry_price - current_price) / entry_price * 100

        # 未达到追踪门槛
        if profit_pct < trail_gate_pct:
            return current_sl

        # 初始化价格极值
        if trade_id not in self.price_extremes:
            self.price_extremes[trade_id] = {'highest': entry_price, 'lowest': entry_price}
        extremes = self.price_extremes[trade_id]

        if direction == 'long':
            if current_price > extremes['highest']:
                extremes['highest'] = current_price
            new_sl = extremes['highest'] * (1 - trail_dist_pct / 100)
            if new_sl > current_sl:
                self._record_sl_change(trade_id, current_sl, new_sl, current_tp, current_tp,
                    "追踪止损上移 (盈利%.1f%% 最高$%.4f ATR$%.4f)" % (profit_pct, extremes['highest'], atr_val),
                    current_price, extremes['highest'], None)
                self._update_stop_loss(trade_id, new_sl)
                print("  %s 盈利%.1f%% 止损上移: $%.4f -> $%.4f" % (symbol, profit_pct, current_sl, new_sl))
                return new_sl
        else:
            if current_price < extremes['lowest']:
                extremes['lowest'] = current_price
            new_sl = extremes['lowest'] * (1 + trail_dist_pct / 100)
            if new_sl < current_sl:
                self._record_sl_change(trade_id, current_sl, new_sl, current_tp, current_tp,
                    "追踪止损下移 (盈利%.1f%% 最低$%.4f ATR$%.4f)" % (profit_pct, extremes['lowest'], atr_val),
                    current_price, None, extremes['lowest'])
                self._update_stop_loss(trade_id, new_sl)
                print("  %s 盈利%.1f%% 止损下移: $%.4f -> $%.4f" % (symbol, profit_pct, current_sl, new_sl))
                return new_sl

        return current_sl

    def _update_stop_loss(self, trade_id, new_sl):
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        cursor.execute("""
            UPDATE real_trades
            SET stop_loss = ?, sl_tp_adjustments = COALESCE(sl_tp_adjustments, 0) + 1
            WHERE id = ?
        """, (new_sl, trade_id))
        conn.commit()
        conn.close()

    def _record_sl_change(self, trade_id, old_sl, new_sl, old_tp, new_tp, reason, current_price, highest, lowest):
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        cursor.execute("""
            INSERT INTO sl_tp_history (trade_id, old_stop_loss, new_stop_loss, old_take_profit, new_take_profit,
                                       reason, current_price, highest_price, lowest_price)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, (trade_id, old_sl, new_sl, old_tp, new_tp, reason, current_price, highest, lowest))
        conn.commit()
        conn.close()

    # ===== 持仓监控 =====

    def check_and_close_positions(self):
        """检查并平仓"""
        positions = self.get_open_positions()
        closed_count = 0

        for pos in positions:
            symbol = pos['symbol']
            current_price = self.get_current_price(symbol)
            if not current_price:
                continue

            direction = pos['direction']
            entry_price = pos['entry_price']

            # 最低持仓保护（30分钟，除非亏损>5%）
            try:
                entry_t = datetime.strptime(
                    str(pos['entry_time']).replace('T', ' ').split('.')[0],
                    '%Y-%m-%d %H:%M:%S'
                )
                hold_minutes = (datetime.now() - entry_t).total_seconds() / 60

                if hold_minutes < 30:
                    if direction == 'long':
                        emergency_pct = (current_price - entry_price) / entry_price * 100
                    else:
                        emergency_pct = (entry_price - current_price) / entry_price * 100
                    if emergency_pct > -5:
                        continue  # 保护中，跳过
            except Exception:
                pass

            # 更新追踪止损
            stop_loss = self.update_trailing_stop(pos, current_price)
            take_profit = pos['take_profit']

            # 更新浮盈浮亏记录
            self._update_max_profit_loss(pos, current_price)

            should_close = False
            close_reason = ""

            # 止盈止损检查
            if direction == 'long':
                if current_price >= take_profit:
                    should_close = True
                    close_reason = "止盈"
                elif current_price <= stop_loss:
                    is_trailing = stop_loss != pos.get('original_stop_loss')
                    should_close = True
                    close_reason = "追踪止损" if is_trailing else "止损"
            else:
                if current_price <= take_profit:
                    should_close = True
                    close_reason = "止盈"
                elif current_price >= stop_loss:
                    is_trailing = stop_loss != pos.get('original_stop_loss')
                    should_close = True
                    close_reason = "追踪止损" if is_trailing else "止损"

            # 超时止损（24小时+亏损>1%）
            if not should_close:
                try:
                    if hold_minutes > 1440:  # 24小时
                        if direction == 'long':
                            pnl_pct = (current_price - entry_price) / entry_price * 100
                        else:
                            pnl_pct = (entry_price - current_price) / entry_price * 100
                        if pnl_pct < -1.0:
                            should_close = True
                            close_reason = "超时止损(%.0fh/%.1f%%)" % (hold_minutes/60, pnl_pct)
                except Exception:
                    pass

            if should_close:
                self.close_position(pos, current_price, close_reason)
                closed_count += 1
                if pos['id'] in self.price_extremes:
                    del self.price_extremes[pos['id']]
                time.sleep(1)

        return closed_count

    def _update_max_profit_loss(self, pos, current_price):
        """更新最大浮盈/浮亏"""
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        direction = pos['direction']
        entry_price = pos['entry_price']

        if direction == 'long':
            pnl_pct = (current_price - entry_price) / entry_price * 100
        else:
            pnl_pct = (entry_price - current_price) / entry_price * 100

        if pnl_pct > 0:
            cursor.execute("UPDATE real_trades SET max_profit = MAX(COALESCE(max_profit, 0), ?) WHERE id = ?",
                         (pnl_pct, pos['id']))
        else:
            cursor.execute("UPDATE real_trades SET max_loss = MIN(COALESCE(max_loss, 0), ?) WHERE id = ?",
                         (pnl_pct, pos['id']))
        conn.commit()
        conn.close()

    # ===== 平仓 =====

    def close_position(self, position, exit_price, reason):
        """平仓"""
        pos_id = position['id']
        symbol = position['symbol']
        direction = position['direction']
        entry_price = position['entry_price']
        margin = position['amount']
        leverage = position['leverage']
        entry_time_str = position['entry_time']
        current_sl = position['stop_loss']
        current_tp = position['take_profit']

        # 计算盈亏
        if direction == 'long':
            pnl_pct = (exit_price - entry_price) / entry_price
        else:
            pnl_pct = (entry_price - exit_price) / entry_price

        position_value = margin * leverage
        pnl_before_fee = position_value * pnl_pct

        entry_fee = position.get('fee', position_value * self.fee_rate)
        exit_fee = position_value * self.fee_rate
        total_fee = entry_fee + exit_fee

        entry_time_clean = entry_time_str.replace('T', ' ').split('.')[0]
        entry_time = datetime.strptime(entry_time_clean, '%Y-%m-%d %H:%M:%S')
        exit_time = datetime.now()
        holding_hours = (exit_time - entry_time).total_seconds() / 3600

        funding_rate = 0.0001
        funding_fee = position_value * funding_rate * (holding_hours / 8)
        duration_minutes = int(holding_hours * 60)

        pnl = pnl_before_fee - exit_fee - funding_fee
        roi = (pnl / margin) * 100

        original_sl = position.get('original_stop_loss') or current_sl
        original_tp = position.get('original_take_profit') or current_tp
        adjustments = position.get('sl_tp_adjustments') or 0

        mark = "+" if pnl > 0 else "-"
        print("\n%s 平仓: %s %s $%+.2f (%s)" % (mark, symbol, direction.upper(), pnl, reason))
        print("  入场$%.4f -> 出场$%.4f | 持仓%d分钟 | 调整%d次" % (
            entry_price, exit_price, duration_minutes, adjustments))

        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()

        cursor.execute("""
            UPDATE real_trades SET
                exit_price = ?, exit_time = ?, status = 'CLOSED',
                pnl = ?, roi = ?, fee = ?, funding_fee = ?,
                duration_minutes = ?, close_reason = ?,
                final_stop_loss = ?, final_take_profit = ?
            WHERE id = ?
        """, (exit_price, exit_time.isoformat(), pnl, roi, total_fee, funding_fee,
              duration_minutes, reason, current_sl, current_tp, pos_id))

        if funding_fee > 0:
            cursor.execute("""
                INSERT INTO funding_history (
                    trade_id, symbol, funding_rate, position_value, funding_fee, direction
                ) VALUES (?, ?, ?, ?, ?, ?)
            """, (pos_id, symbol, funding_rate, position_value, funding_fee, direction))

        conn.commit()
        conn.close()

        # 设置冷却期
        self.symbol_cooldown[symbol] = datetime.now() + timedelta(hours=self.cooldown_hours)

        return True

    def get_current_price(self, symbol):
        """获取当前价格"""
        try:
            ticker = self.exchange.fetch_ticker(symbol)
            return ticker['last']
        except Exception as e:
            print("  获取%s价格失败: %s" % (symbol, e))
            return None

    # ===== 熔断机制 =====

    def check_circuit_breaker(self):
        """熔断: 连续N笔亏损暂停"""
        try:
            conn = sqlite3.connect(DB_PATH)
            cursor = conn.cursor()
            cursor.execute("""
                SELECT pnl, exit_time FROM real_trades
                WHERE status = 'CLOSED' ORDER BY exit_time DESC LIMIT ?
            """, (self.circuit_break_count,))
            recent = cursor.fetchall()
            conn.close()

            if len(recent) < self.circuit_break_count:
                return False

            all_losses = all(row[0] < 0 for row in recent)
            if all_losses:
                last_exit = recent[0][1]
                try:
                    last_time = datetime.strptime(
                        str(last_exit).replace('T', ' ').split('.')[0],
                        '%Y-%m-%d %H:%M:%S'
                    )
                    hours_since = (datetime.now() - last_time).total_seconds() / 3600
                    if hours_since > self.cooldown_hours:
                        print("熔断解除: 已冷却%.0f小时" % hours_since)
                        return False
                except Exception:
                    pass

                total_loss = sum(row[0] for row in recent)
                print("熔断触发: 连续%d笔亏损 ($%.2f), 暂停开仓" % (self.circuit_break_count, total_loss))
                return True

            return False
        except Exception:
            return False

    # ===== 账户统计 =====

    def save_account_snapshot(self):
        """保存账户快照"""
        try:
            conn = sqlite3.connect(DB_PATH)
            cursor = conn.cursor()

            current_capital = self.get_current_capital()
            margin_used = self.get_margin_used()
            available = current_capital - margin_used
            positions = self.get_open_positions()

            unrealized_pnl = 0
            for pos in positions:
                current_price = self.get_current_price(pos['symbol'])
                if current_price:
                    if pos['direction'] == 'long':
                        pnl_pct = (current_price - pos['entry_price']) / pos['entry_price']
                    else:
                        pnl_pct = (pos['entry_price'] - current_price) / pos['entry_price']
                    unrealized_pnl += pos['amount'] * pos['leverage'] * pnl_pct

            cursor.execute("SELECT SUM(pnl) FROM real_trades WHERE status = 'CLOSED'")
            realized_pnl = cursor.fetchone()[0] or 0
            cursor.execute("SELECT SUM(fee), SUM(funding_fee) FROM real_trades WHERE status = 'CLOSED'")
            fees = cursor.fetchone()
            total_fees = fees[0] or 0
            total_funding = fees[1] or 0

            max_capital = max(self.initial_capital, current_capital)
            max_dd = ((max_capital - current_capital) / max_capital * 100) if max_capital > 0 else 0

            today = date.today().isoformat()
            cursor.execute("SELECT SUM(pnl) FROM real_trades WHERE status='CLOSED' AND DATE(exit_time)=?", (today,))
            daily_pnl = cursor.fetchone()[0] or 0

            cursor.execute("""
                INSERT INTO account_snapshots (
                    total_capital, available_capital, margin_used,
                    unrealized_pnl, realized_pnl, total_fees, total_funding_fees,
                    open_positions, daily_pnl, max_drawdown
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (current_capital + unrealized_pnl, available, margin_used,
                  unrealized_pnl, realized_pnl, total_fees, total_funding,
                  len(positions), daily_pnl, max_dd))
            conn.commit()
            conn.close()
        except Exception as e:
            print("保存快照失败: %s" % e)

    def update_daily_stats(self):
        """更新每日统计"""
        try:
            conn = sqlite3.connect(DB_PATH)
            cursor = conn.cursor()
            today = date.today().isoformat()
            current_capital = self.get_current_capital()

            cursor.execute("""
                SELECT
                    COUNT(CASE WHEN DATE(entry_time) = ? THEN 1 END),
                    COUNT(CASE WHEN DATE(exit_time) = ? THEN 1 END),
                    SUM(CASE WHEN DATE(exit_time) = ? AND pnl > 0 THEN 1 ELSE 0 END),
                    SUM(CASE WHEN DATE(exit_time) = ? AND pnl < 0 THEN 1 ELSE 0 END),
                    SUM(CASE WHEN DATE(exit_time) = ? THEN pnl ELSE 0 END),
                    SUM(CASE WHEN DATE(exit_time) = ? THEN fee ELSE 0 END),
                    SUM(CASE WHEN DATE(exit_time) = ? THEN funding_fee ELSE 0 END),
                    MAX(CASE WHEN DATE(exit_time) = ? THEN pnl END),
                    MIN(CASE WHEN DATE(exit_time) = ? THEN pnl END)
                FROM real_trades
            """, (today,) * 9)
            stats = cursor.fetchone()

            cursor.execute("""
                INSERT OR REPLACE INTO daily_stats (
                    date, starting_capital, ending_capital,
                    trades_opened, trades_closed, win_trades, loss_trades,
                    total_pnl, total_fees, total_funding_fees, best_trade, worst_trade
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (today, self.initial_capital, current_capital,
                  stats[0] or 0, stats[1] or 0, stats[2] or 0, stats[3] or 0,
                  stats[4] or 0, stats[5] or 0, stats[6] or 0, stats[7], stats[8]))
            conn.commit()
            conn.close()
        except Exception as e:
            print("更新统计失败: %s" % e)

    # ===== 主循环 =====

    def run_once(self):
        """执行一次交易循环"""
        print("\n" + "=" * 60)
        print("%s - 扫描开始" % datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
        print("=" * 60)

        # 1. 检查持仓
        print("\n[1] 检查持仓...")
        closed = self.check_and_close_positions()
        if closed > 0:
            print("  平仓 %d 个" % closed)

        # 2. 熔断检查
        circuit_break = self.check_circuit_breaker()

        # 3. 扫描动量信号
        print("\n[2] 扫描动量突破信号...")
        signals = self.scan_momentum_signals()

        # 状态显示
        current_capital = self.get_current_capital()
        margin_used = self.get_margin_used()
        available = current_capital - margin_used
        positions = self.get_open_positions()

        print("\n资金: $%.2f | 占用: $%.2f | 可用: $%.2f | 持仓: %d/%d" % (
            current_capital, margin_used, available, len(positions), self.max_positions))

        if signals:
            print("发现 %d 个信号" % len(signals))

        # 4. 开仓
        trades_made = 0
        if circuit_break:
            print("熔断中: 仅监控持仓")
        else:
            for sig in signals:
                if self.should_trade(sig):
                    if self.open_position(sig):
                        trades_made += 1
                        time.sleep(1)

        if trades_made > 0:
            print("本轮开仓 %d 个" % trades_made)

        # 5. 快照（每30次循环）
        self.snapshot_counter += 1
        if self.snapshot_counter % 30 == 0:
            self.save_account_snapshot()

        # 6. 每日统计
        self.update_daily_stats()

    def run(self, interval=300):
        """持续运行"""
        print("\n启动自动交易...")
        print("扫描间隔: %d秒" % interval)
        print("按 Ctrl+C 停止\n")

        while True:
            try:
                self.run_once()
                print("\n等待%d秒...\n" % interval)
                time.sleep(interval)
            except KeyboardInterrupt:
                print("\n停止信号收到")
                self.save_account_snapshot()
                print("交易已停止")
                break
            except Exception as e:
                print("\n错误: %s" % e)
                import traceback
                traceback.print_exc()
                print("5分钟后重试...")
                time.sleep(300)


if __name__ == '__main__':
    trader = AutoTraderV4()
    trader.run(interval=300)  # 5分钟扫描一次
