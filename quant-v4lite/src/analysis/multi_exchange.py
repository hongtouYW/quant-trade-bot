import asyncio
import logging
import time
from typing import List, Dict

import ccxt.async_support as ccxt

from src.core.models import ConsensusResult
from src.core.enums import Direction

logger = logging.getLogger(__name__)


class MultiExchangeConsensus:
    """
    多交易所价格共识 [V4]
    对比 Binance/Bybit/OKX 价格方向一致性
    """

    def __init__(self, config: dict):
        me_cfg = config.get('multi_exchange', {})
        self._enabled = me_cfg.get('enabled', False)
        self._reject_below = me_cfg.get('consensus_reject_below', 0.3)
        self._weak_below = me_cfg.get('consensus_weak_below', 0.6)

        self._exchanges: Dict[str, ccxt.Exchange] = {}
        self._prices: Dict[str, Dict[str, dict]] = {}  # {symbol: {exchange: ticker}}
        self._prev_prices: Dict[str, Dict[str, float]] = {}

        if self._enabled:
            for ex_cfg in me_cfg.get('exchanges', []):
                name = ex_cfg['name']
                if name == 'bybit':
                    self._exchanges[name] = ccxt.bybit({
                        'options': {'defaultType': 'future'},
                        'enableRateLimit': True,
                    })
                elif name == 'okx':
                    self._exchanges[name] = ccxt.okx({
                        'options': {'defaultType': 'swap'},
                        'enableRateLimit': True,
                    })

    async def close(self):
        for ex in self._exchanges.values():
            await ex.close()

    async def update(self, symbols: List[str]):
        """每 30 秒调用，更新各交易所价格"""
        if not self._enabled:
            return

        # 保存旧价格
        self._prev_prices = {}
        for sym in symbols:
            self._prev_prices[sym] = {}
            for ex_name, data in self._prices.get(sym, {}).items():
                self._prev_prices[sym][ex_name] = data.get('last', 0)

        for ex_name, exchange in self._exchanges.items():
            try:
                # 转换符号格式
                tickers = await exchange.fetch_tickers()
                for sym in symbols:
                    # Binance: BTC/USDT:USDT, Bybit/OKX 类似
                    for ticker_sym, ticker in tickers.items():
                        base = sym.split('/')[0]
                        if base in ticker_sym and 'USDT' in ticker_sym:
                            if sym not in self._prices:
                                self._prices[sym] = {}
                            self._prices[sym][ex_name] = ticker
                            break
            except Exception as e:
                logger.warning(f"MultiExchange update {ex_name}: {e}")

    def check(self, symbol: str, direction: Direction) -> ConsensusResult:
        """检查多所共识"""
        if not self._enabled:
            return ConsensusResult(consensus=1.0, recommendation="strong")

        exchange_prices = self._prices.get(symbol, {})
        prev = self._prev_prices.get(symbol, {})

        if len(exchange_prices) < 2:
            return ConsensusResult(consensus=0.5, recommendation="normal")

        agree_count = 0
        total_count = len(exchange_prices)
        divergence = {}

        prices = []
        for ex_name, ticker in exchange_prices.items():
            current = ticker.get('last', 0)
            previous = prev.get(ex_name, current)
            prices.append(current)

            if previous > 0:
                change = (current - previous) / previous
                if direction == Direction.LONG and change > 0.0003:
                    agree_count += 1
                elif direction == Direction.SHORT and change < -0.0003:
                    agree_count += 1
                divergence[ex_name] = change

        consensus = agree_count / total_count if total_count > 0 else 0

        # 异常检测: 单所偏离均值 > 0.3%
        if prices:
            avg = sum(prices) / len(prices)
            for ex_name, ticker in exchange_prices.items():
                p = ticker.get('last', 0)
                if avg > 0 and abs(p - avg) / avg > 0.003:
                    logger.warning(f"Price divergence {ex_name} {symbol}: "
                                   f"{p} vs avg {avg}")

        if consensus >= 0.9:
            rec = "strong"
        elif consensus >= 0.6:
            rec = "normal"
        elif consensus >= self._reject_below:
            rec = "weak"
        else:
            rec = "reject"

        return ConsensusResult(
            consensus=consensus,
            recommendation=rec,
            price_divergence=divergence,
        )
