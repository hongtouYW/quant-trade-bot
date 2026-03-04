#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
量化交易 v1 策略 - 基于交易助手 backtest_engine v1 原始策略
独立数据库: paper_trading.db (real_trades表, assistant='量化v1')
v1特点: 低门槛(55分)/高杠杆(最大10x)/无BTC过滤/ROI止盈止损/1h冷却
"""

import requests
import json
import time
import os
import sqlite3
from datetime import datetime

class AutoTraderV1:
    def __init__(self):
        # === v1 核心参数 (与 STRATEGY_PRESETS['v1'] 完全一致) ===
        self.initial_capital = 2000
        self.current_capital = 2000
        self.fee_rate = 0.0005  # 0.05% Binance合约
        self.min_score = 55          # v1: 低门槛
        self.max_positions = 10      # 最多10仓 (实盘多币种)
        self.max_leverage = 10       # v1: 最大10x杠杆
        self.cooldown_seconds = 3600 # v1: 1小时冷却
        self.min_hold_minutes = 60   # 1h最短持仓
        self.max_hold_minutes = 2880 # 48h最长持仓

        # === v1 ROI止盈止损参数 ===
        self.roi_stop_loss = -10     # ROI跌到-10%平仓
        self.roi_trailing_start = 5  # ROI达+5%启动移动止盈
        self.roi_trailing_distance = 3  # 从峰值回撤3%平仓

        # === 无BTC过滤, 无SHORT加成 (v1核心特征) ===
        # enable_trend_filter = False
        # short_bias = 1.0

        # === 监控币种 (~150个) ===
        self.watch_symbols = [
            'BTC', 'ETH', 'SOL', 'XRP', 'BNB', 'DOGE', 'ADA', 'AVAX', 'LINK', 'DOT',
            'NEAR', 'SUI', 'APT', 'ATOM', 'FTM', 'HBAR', 'XLM', 'ETC', 'LTC', 'BCH',
            'ALGO', 'ICP', 'FIL', 'XMR', 'TRX',
            'ARB', 'OP', 'MATIC', 'AAVE', 'UNI', 'CRV', 'DYDX', 'INJ', 'SEI',
            'STX', 'RUNE', 'SNX', 'COMP', 'MKR', 'LDO',
            'TAO', 'RENDER', 'FET', 'WLD', 'AGIX', 'OCEAN', 'ARKM', 'PENGU',
            'AIXBT', 'GRASS', 'CGPT',
            'TIA', 'JUP', 'PYTH', 'JTO', 'ENA', 'STRK', 'ZRO', 'WIF',
            'SHIB', 'FLOKI', 'TRUMP',
            'VET', 'AXS', 'ROSE', 'DUSK', 'CHZ', 'ENJ', 'SAND',
            'ONDO', 'PENDLE', 'EIGEN', 'ETHFI', 'TON',
            'MANA', 'GALA', 'IMX', 'ORDI', 'SXP', 'ZEC', 'DASH',
            'WAVES', 'GRT', 'THETA', 'IOTA', 'NEO', 'KAVA', 'ONE', 'CELO',
            'CAKE', 'SUSHI', 'GMX', 'ENS', 'BLUR', 'PEOPLE', 'MASK',
            '1INCH', 'ANKR', 'AR', 'FLOW', 'EGLD', 'KAS', 'JASMY', 'NOT',
            'NEIRO', 'PNUT', 'POPCAT', 'TURBO', 'MEME', 'BOME', 'DOGS',
            'FARTCOIN', 'USUAL', 'ME', 'MOODENG', 'SPX', 'ANIME', 'SONIC',
            'HYPE', 'LINA', 'LEVER', 'ALPHA', 'UNFI',
            'YGG', 'PIXEL', 'PORTAL', 'XAI', 'DYM', 'MANTA', 'ZK', 'W', 'SAGA', 'RSR',
        ]
        self.skip_coins = ['BERA', 'IP', 'LIT', 'TROY', 'VIRTUAL', 'BONK', 'PEPE']

        # === 状态 ===
        self.positions = {}
        self.last_close_time = None

        # === 数据库 (5001系统的DB) ===
        self.db_path = '/opt/trading-bot/data/db/paper_trading.db'
        os.makedirs(os.path.dirname(self.db_path), exist_ok=True)
        self.init_database()
        self.load_positions()
        self._restore_capital()

        print(f"[量化v1] 系统启动")
        print(f"  策略: v1原始 | min_score={self.min_score} | max_lev={self.max_leverage}x | 无BTC过滤")
        print(f"  ROI: SL={self.roi_stop_loss}% | 移动止盈+{self.roi_trailing_start}%启动/{self.roi_trailing_distance}%回撤")
        print(f"  资金: {self.current_capital:.2f}U (初始{self.initial_capital}U)")
        print(f"  币种: {len(self.watch_symbols)}个 | 最多{self.max_positions}仓 | 冷却{self.cooldown_seconds//60}分钟")

    # ==================== 数据库 ====================
    ASSISTANT_NAME = '量化v1'
    MODE = 'paper'

    def init_database(self):
        """确保real_trades表存在"""
        pass

    def _restore_capital(self):
        try:
            conn = sqlite3.connect(self.db_path)
            c = conn.cursor()
            c.execute('SELECT COALESCE(SUM(pnl), 0) FROM real_trades WHERE status="CLOSED" AND assistant=?',
                      (self.ASSISTANT_NAME,))
            total_pnl = c.fetchone()[0]
            conn.close()
            self.current_capital = self.initial_capital + total_pnl
            if total_pnl != 0:
                print(f"  恢复: {self.initial_capital}U + {total_pnl:+.2f}U = {self.current_capital:.2f}U")
        except Exception as e:
            print(f"  恢复失败: {e}")

    def load_positions(self):
        try:
            conn = sqlite3.connect(self.db_path)
            c = conn.cursor()
            c.execute('''SELECT symbol, direction, entry_price, amount, leverage, stop_loss, take_profit,
                         entry_time, score, final_stop_loss FROM real_trades
                         WHERE status="OPEN" AND assistant=?''', (self.ASSISTANT_NAME,))
            for row in c.fetchall():
                sym, dr, ep, amt, lev, sl, tp, et, sc, fsl = row
                self.positions[sym] = {
                    'direction': dr, 'entry_price': ep, 'amount': amt,
                    'leverage': lev, 'stop_loss': sl, 'take_profit': tp,
                    'entry_time': et, 'score': sc or 0,
                    'peak_roi': 0,
                }
            conn.close()
            if self.positions:
                print(f"  持仓: {list(self.positions.keys())}")
        except Exception as e:
            print(f"  加载持仓失败: {e}")

    # ==================== 行情API ====================

    SYMBOL_1000 = {
        'BONK': '1000BONKUSDT', 'PEPE': '1000PEPEUSDT',
        'SHIB': '1000SHIBUSDT', 'FLOKI': '1000FLOKIUSDT',
    }

    def _bsym(self, symbol):
        return self.SYMBOL_1000.get(symbol, f"{symbol}USDT")

    def get_price(self, symbol):
        try:
            url = f"https://fapi.binance.com/fapi/v1/ticker/price?symbol={self._bsym(symbol)}"
            r = requests.get(url, timeout=10)
            return float(r.json()['price'])
        except:
            return None

    def get_klines(self, symbol, interval='1h', limit=100):
        try:
            url = f"https://fapi.binance.com/fapi/v1/klines?symbol={self._bsym(symbol)}&interval={interval}&limit={limit}"
            r = requests.get(url, timeout=10)
            data = r.json()
            if isinstance(data, list):
                return data
        except:
            pass
        return None

    # ==================== 技术指标 ====================

    def calc_rsi(self, prices, period=14):
        if len(prices) < period + 1:
            return 50
        deltas = [prices[i] - prices[i-1] for i in range(1, len(prices))]
        gains = [d if d > 0 else 0 for d in deltas[-period:]]
        losses = [-d if d < 0 else 0 for d in deltas[-period:]]
        avg_g = sum(gains) / period
        avg_l = sum(losses) / period
        if avg_l == 0:
            return 100
        rs = avg_g / avg_l
        return 100 - (100 / (1 + rs))

    # ==================== 信号分析 (v1: 纯信号, 无BTC过滤) ====================

    def analyze_signal(self, symbol):
        """v1信号评分 (0-100) + 方向 — 无BTC过滤, 无SHORT加成"""
        try:
            klines = self.get_klines(symbol, '1h', 100)
            if not klines or len(klines) < 50:
                return 0, None

            closes = [float(k[4]) for k in klines]
            volumes = [float(k[5]) for k in klines]
            highs = [float(k[2]) for k in klines]
            lows = [float(k[3]) for k in klines]
            price = closes[-1]

            votes = {'LONG': 0, 'SHORT': 0}

            # 1. RSI (30分)
            rsi = self.calc_rsi(closes)
            if rsi < 30:
                rsi_sc = 30; votes['LONG'] += 1
            elif rsi > 70:
                rsi_sc = 30; votes['SHORT'] += 1
            elif rsi < 45:
                rsi_sc = 15; votes['LONG'] += 1
            elif rsi > 55:
                rsi_sc = 15; votes['SHORT'] += 1
            else:
                rsi_sc = 5

            # 2. 趋势 (30分, 双权重)
            ma7 = sum(closes[-7:]) / 7
            ma20 = sum(closes[-20:]) / 20
            ma50 = sum(closes[-50:]) / 50
            if price > ma7 > ma20 > ma50:
                trend_sc = 30; votes['LONG'] += 2
            elif price < ma7 < ma20 < ma50:
                trend_sc = 30; votes['SHORT'] += 2
            elif price > ma7 > ma20:
                trend_sc = 15; votes['LONG'] += 1
            elif price < ma7 < ma20:
                trend_sc = 15; votes['SHORT'] += 1
            else:
                trend_sc = 5

            # 3. 成交量 (20分)
            avg_vol = sum(volumes[-20:]) / 20
            vol_ratio = volumes[-1] / avg_vol if avg_vol > 0 else 1
            if vol_ratio > 1.5:
                vol_sc = 20
            elif vol_ratio > 1.2:
                vol_sc = 15
            elif vol_ratio > 1:
                vol_sc = 10
            else:
                vol_sc = 5

            # 4. 价格位置 (20分)
            h50 = max(highs[-50:])
            l50 = min(lows[-50:])
            pos = (price - l50) / (h50 - l50) if h50 > l50 else 0.5
            if pos < 0.2:
                pos_sc = 20; votes['LONG'] += 1
            elif pos > 0.8:
                pos_sc = 20; votes['SHORT'] += 1
            elif pos < 0.35:
                pos_sc = 10; votes['LONG'] += 1
            elif pos > 0.65:
                pos_sc = 10; votes['SHORT'] += 1
            else:
                pos_sc = 5

            # 方向决定
            if votes['LONG'] > votes['SHORT']:
                direction = 'LONG'
            elif votes['SHORT'] > votes['LONG']:
                direction = 'SHORT'
            else:
                direction = 'LONG' if rsi < 50 else 'SHORT'

            total = rsi_sc + trend_sc + vol_sc + pos_sc

            # v1: 无BTC过滤, 无RSI冲突惩罚, 无SHORT加成
            # 分数就是原始信号强度

            analysis = {
                'price': price, 'rsi': rsi, 'ma7': ma7, 'ma20': ma20, 'ma50': ma50,
                'volume_ratio': vol_ratio, 'direction': direction, 'score': total,
            }
            return total, analysis

        except Exception as e:
            return 0, None

    # ==================== 仓位管理 (v1: 评分动态杠杆) ====================

    def calc_position_size(self, score):
        """v1仓位: 评分决定杠杆和仓位大小 (与 backtest_engine 一致)"""
        available = self.current_capital - sum(p['amount'] for p in self.positions.values())

        if score >= 85:
            size = min(400, available * 0.25)
            leverage = min(5, self.max_leverage)
        elif score >= 75:
            size = min(300, available * 0.20)
            leverage = min(3, self.max_leverage)
        elif score >= 70:
            size = min(200, available * 0.15)
            leverage = min(3, self.max_leverage)
        elif score >= 60:
            size = min(150, available * 0.10)
            leverage = min(3, self.max_leverage)
        elif score >= 55:
            size = min(100, available * 0.08)
            leverage = min(3, self.max_leverage)
        else:
            return 0, 0

        return max(50, int(size)), leverage

    def open_position(self, symbol, analysis):
        """开仓 — v1 ROI止盈止损"""
        try:
            score = analysis['score']
            direction = analysis['direction']
            entry_price = analysis['price']

            amount, leverage = self.calc_position_size(score)
            if amount < 50:
                return

            # v1: ROI止损 → 反算止损价
            stop_pct = self.roi_stop_loss / (leverage * 100)  # 负值
            if direction == 'LONG':
                stop_loss = entry_price * (1 + stop_pct)  # stop_pct是负的
            else:
                stop_loss = entry_price * (1 - stop_pct)

            # 移动止盈: 不设固定TP, 由check_position中的trailing逻辑处理
            take_profit = 0  # 0表示使用移动止盈

            now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

            self.positions[symbol] = {
                'direction': direction, 'entry_price': entry_price,
                'amount': amount, 'leverage': leverage,
                'stop_loss': stop_loss, 'take_profit': take_profit,
                'entry_time': now, 'score': score,
                'peak_roi': 0,
            }

            # 写DB
            conn = sqlite3.connect(self.db_path)
            c = conn.cursor()
            reason_text = f"v1 sc={score} RSI={analysis['rsi']:.1f} {direction} {leverage}x"
            c.execute('''INSERT INTO real_trades (symbol, direction, entry_price, amount, leverage,
                         stop_loss, take_profit, entry_time, status, score, reason,
                         assistant, mode, entry_rsi, entry_trend,
                         original_stop_loss, original_take_profit)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'OPEN', ?, ?, ?, ?, ?, ?, ?, ?)''',
                      (symbol, direction, entry_price, amount, leverage,
                       stop_loss, take_profit, now, score, reason_text,
                       self.ASSISTANT_NAME, self.MODE,
                       analysis['rsi'], 'v1',
                       stop_loss, take_profit))
            conn.commit()
            conn.close()

            print(f"  >> OPEN {symbol} {direction} ${amount} @{entry_price:.6f} {leverage}x SL={stop_loss:.6f} sc={score}")

        except Exception as e:
            print(f"  开仓失败 {symbol}: {e}")
            import traceback; traceback.print_exc()

    def check_position(self, symbol, pos):
        """检查持仓 — v1 ROI移动止盈"""
        try:
            price = self.get_price(symbol)
            if not price:
                return

            direction = pos['direction']
            ep = pos['entry_price']
            lev = pos['leverage']

            # 计算ROI
            if direction == 'LONG':
                roi = ((price - ep) / ep) * lev * 100
            else:
                roi = ((ep - price) / ep) * lev * 100

            # 更新峰值ROI
            if roi > pos.get('peak_roi', 0):
                pos['peak_roi'] = roi

            peak_roi = pos['peak_roi']

            # 持仓时间
            hold_min = 0
            try:
                et = datetime.strptime(pos['entry_time'], '%Y-%m-%d %H:%M:%S')
                hold_min = (datetime.now() - et).total_seconds() / 60
            except:
                pass

            # 1h最短保护 (除非亏>15% ROI)
            min_hold_protect = hold_min < self.min_hold_minutes and roi > -15

            should_close = False
            reason = ""

            # 1. 48h超时强制平仓
            if hold_min > self.max_hold_minutes:
                should_close = True
                reason = f"timeout {hold_min/60:.0f}h ROI={roi:+.1f}%"

            # 2. ROI止损: ROI <= roi_stop_loss
            if not should_close and roi <= self.roi_stop_loss:
                if min_hold_protect:
                    pass  # 最短持仓保护
                else:
                    should_close = True
                    reason = f"SL hit ROI={roi:+.1f}% (limit={self.roi_stop_loss}%)"

            # 3. 移动止盈: 峰值ROI超过启动线后, 回撤超过距离就平仓
            if not should_close and peak_roi >= self.roi_trailing_start:
                trail_exit_roi = peak_roi - self.roi_trailing_distance
                if roi <= trail_exit_roi:
                    should_close = True
                    if trail_exit_roi > 0:
                        reason = f"trail TP @{price:.6f} peakROI={peak_roi:+.1f}% exitROI={trail_exit_roi:+.1f}%"
                    else:
                        reason = f"trail SL @{price:.6f} peakROI={peak_roi:+.1f}% exitROI={trail_exit_roi:+.1f}%"

            if should_close:
                self.close_position(symbol, price, reason)

        except Exception as e:
            print(f"  检查{symbol}失败: {e}")

    def close_position(self, symbol, exit_price, reason):
        """平仓"""
        try:
            pos = self.positions.get(symbol)
            if not pos:
                return

            direction = pos['direction']
            ep = pos['entry_price']
            amount = pos['amount']
            lev = pos['leverage']

            if direction == 'LONG':
                pct = (exit_price - ep) / ep
            else:
                pct = (ep - exit_price) / ep

            roi = pct * lev * 100
            pnl_raw = amount * pct * lev

            # 手续费
            pos_val = amount * lev
            total_fee = pos_val * self.fee_rate * 2  # 开+平

            # 资金费
            try:
                et = datetime.strptime(pos['entry_time'], '%Y-%m-%d %H:%M:%S')
                hours = (datetime.now() - et).total_seconds() / 3600
            except:
                hours = 0
            funding_fee = pos_val * 0.0001 * (hours / 8)

            pnl = pnl_raw - total_fee - funding_fee
            self.current_capital += pnl

            # 更新DB
            now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            conn = sqlite3.connect(self.db_path)
            c = conn.cursor()
            c.execute('''UPDATE real_trades SET exit_price=?, exit_time=?, status='CLOSED',
                         pnl=?, roi=?, fee=?, funding_fee=?,
                         close_reason=?, reason=reason||' | '||?,
                         final_stop_loss=?
                         WHERE symbol=? AND status='OPEN' AND assistant=?''',
                      (exit_price, now, pnl, roi, total_fee, funding_fee,
                       reason, reason,
                       pos.get('stop_loss', 0),
                       symbol, self.ASSISTANT_NAME))
            conn.commit()
            conn.close()

            del self.positions[symbol]
            self.last_close_time = datetime.now()

            mark = '+' if pnl > 0 else '-'
            print(f"  {mark} CLOSE {symbol} {direction} PnL={pnl:+.2f}U ROI={roi:+.1f}% | {reason}")

        except Exception as e:
            print(f"  平仓失败 {symbol}: {e}")
            import traceback; traceback.print_exc()

    # ==================== 主循环 ====================

    def scan_market(self):
        """扫描市场 — v1: 无方向限制, 无MA斜率过滤"""
        print(f"\n=== 扫描 {datetime.now().strftime('%H:%M:%S')} 持仓{len(self.positions)} 余额${self.current_capital:.2f} ===")

        opps = []
        for sym in self.watch_symbols:
            if sym in self.skip_coins or sym in self.positions:
                continue
            score, ana = self.analyze_signal(sym)
            if score >= self.min_score and ana:
                d = ana['direction']
                # v1: 无方向限制, 无LONG>=70, 无85+LONG跳过, 无MA斜率过滤
                opps.append((sym, score, ana))
                print(f"  * {sym} {score}分 {d}")

        opps.sort(key=lambda x: x[1], reverse=True)

        # v1: 1小时冷却
        if self.last_close_time:
            cd = (datetime.now() - self.last_close_time).total_seconds()
            if cd < self.cooldown_seconds:
                remaining = int((self.cooldown_seconds - cd) / 60)
                print(f"  冷却中 (还剩{remaining}m)")
                return

        available = self.current_capital - sum(p['amount'] for p in self.positions.values())
        if len(self.positions) < self.max_positions and available > 100:
            opened = 0
            for sym, score, ana in opps:
                if opened >= 3 or len(self.positions) + opened >= self.max_positions:
                    break
                self.open_position(sym, ana)
                opened += 1

    def save_snapshot(self):
        try:
            conn = sqlite3.connect(self.db_path)
            c = conn.cursor()
            c.execute('''INSERT INTO account_snapshots (timestamp, balance, equity, positions_count)
                         VALUES (?, ?, ?, ?)''',
                      (datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                       self.current_capital, self.current_capital, len(self.positions)))
            conn.commit()
            conn.close()
        except:
            pass

    def run(self, interval=60):
        """主循环"""
        print(f"\n>>> 开始运行 (每{interval}秒扫描)\n")
        scan_count = 0

        while True:
            try:
                # 检查持仓
                for sym in list(self.positions.keys()):
                    self.check_position(sym, self.positions[sym])

                # 扫描新机会
                self.scan_market()

                scan_count += 1

                # 每12次(~12分钟)存快照
                if scan_count % 12 == 0:
                    self.save_snapshot()
                    pnl = self.current_capital - self.initial_capital
                    print(f"\n  === 余额${self.current_capital:.2f} 盈亏${pnl:+.2f} 持仓{len(self.positions)} ===\n")

                time.sleep(interval)

            except KeyboardInterrupt:
                print(f"\n停止. 余额${self.current_capital:.2f} 盈亏${self.current_capital-self.initial_capital:+.2f}")
                break
            except Exception as e:
                print(f"错误: {e}")
                import traceback; traceback.print_exc()
                time.sleep(interval)


if __name__ == '__main__':
    trader = AutoTraderV1()
    trader.run(interval=60)
