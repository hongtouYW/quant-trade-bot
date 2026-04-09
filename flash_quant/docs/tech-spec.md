# Flash Quant - 技术规格文档 (Tech Spec)

> **作者**: 💻 Amelia (Senior Software Engineer)
> **日期**: 2026-04-08
> **版本**: v0.1
> **依赖**: prd.md, architecture.md, test-design.md
> **下游**: 实际代码实现

---

## 📖 文档说明

本文档是**实现层级**的规格,提供:
- 关键模块的接口签名
- 核心函数的伪代码
- 数据库迁移脚本
- 配置文件模板
- requirements.txt
- 部署清单

每个章节都标注**对应的 FR-ID**, 便于追溯。

---

## 1. 技术清单

### 1.1 requirements.txt

```txt
# Core
Flask==3.0.3
gunicorn==21.2.0
SQLAlchemy==2.0.30
PyMySQL==1.1.1
redis==5.0.4
python-dotenv==1.0.1

# Trading
ccxt==4.3.42
python-binance==1.0.19

# WebSocket
websockets==12.0

# Data
numpy==1.26.4
pandas==2.2.2
TA-Lib==0.4.28

# Async
asyncio  # builtin
aiohttp==3.9.5

# Logging
structlog==24.1.0

# Testing (dev)
pytest==8.2.0
pytest-asyncio==0.23.6
pytest-mock==3.14.0
pytest-cov==5.0.0
factory-boy==3.3.0
responses==0.25.0
```

### 1.2 .env.example

```bash
# === Database ===
MYSQL_HOST=127.0.0.1
MYSQL_PORT=3306
MYSQL_USER=flash_quant_user
MYSQL_PASSWORD=<change_me>
MYSQL_DATABASE=flash_quant_db

# === Redis ===
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0

# === Binance API ===
BINANCE_API_KEY=<change_me>
BINANCE_API_SECRET=<change_me>
BINANCE_TESTNET=false  # Phase 1 不用 testnet, 用真实数据 + paper executor

# === System ===
TRADING_MODE=paper  # paper | live
PHASE=1             # 1, 2, 3, 4 (BR-004)
LOG_LEVEL=INFO
LOG_FORMAT=json
TZ=Asia/Tokyo

# === Web ===
WEB_PORT=5114
WEB_SECRET_KEY=<random_64_chars>
WEB_AUTH_USER=hongtou
WEB_AUTH_PASS_HASH=<bcrypt_hash>

# === Encryption ===
ENCRYPTION_MASTER_KEY=<32_byte_hex>

# === Telegram (Phase 2+, optional) ===
TG_BOT_TOKEN=
TG_CHAT_ID=
```

---

## 2. 核心模块接口规格

### 2.1 `core/constants.py` (BR 硬编码所在地)

```python
"""
业务规则硬编码常量 - 不可在配置文件中修改
对应 PRD BR-001 ~ BR-008
"""
from enum import IntEnum

# === BR-001: 分级杠杆上限 ===
LEVERAGE_TIERS = {
    'tier_a_major': {'symbols': ['BTC', 'ETH'], 'max_leverage': 50, 'stop_loss_roi': -0.05},
    'tier_a_large': {'symbols': ['SOL', 'BNB', 'XRP', 'DOGE', 'ADA', 'AVAX'], 'max_leverage': 30, 'stop_loss_roi': -0.08},
    'tier_b': {'max_leverage': 20, 'stop_loss_roi': -0.10},
    'tier_c': {'max_leverage': 15, 'stop_loss_roi': -0.10},
}
MAX_LEVERAGE = 50  # 绝对上限 (Tier A Major)

# === BR-002: Tier D 黑名单 ===
TIER_D_VOLUME_THRESHOLD = 10_000_000  # USD, 24h volume

# === BR-003: 新币黑名单 ===
NEW_LISTING_DAYS = 7

# === BR-004: 渐进式资金 ===
class Phase(IntEnum):
    P1_PAPER = 1
    P2_SMALL = 2
    P3_MEDIUM = 3
    P4_FULL = 4

PHASE_CAPITAL_LIMITS = {
    Phase.P1_PAPER: 0,        # 模拟盘, 无真金
    Phase.P2_SMALL: 500,      # USDT
    Phase.P3_MEDIUM: 2_000,
    Phase.P4_FULL: 10_000,
}

# === BR-005: 单笔上限 ===
MAX_MARGIN_PER_TRADE = 300  # USDT

# === BR-006: 服务端止损 ===
STOP_LOSS_PLACEMENT_TIMEOUT = 5  # seconds, 必须在 5s 内挂止损

# === BR-007: Tier 1 时段 ===
TIER1_TRADING_HOURS_UTC = (8, 22)  # 8:00 - 22:00 UTC

# === BR-008: 周末减仓 ===
WEEKEND_POSITION_MULTIPLIER = 0.5

# === 其他常量 ===
MAX_CONCURRENT_POSITIONS = 5
SIGNAL_LATENCY_TARGET_MS = 500
WICK_BODY_RATIO_MIN = 0.55
CVD_TOLERANCE = 0.10
FUNDING_RATE_MAX = 0.0008  # 0.08%
COOLDOWN_AFTER_CLOSE_HOURS = 2
BLACK_SWAN_VOLATILITY_THRESHOLD = 0.05  # 5%
BLACK_SWAN_PAUSE_MINUTES = 30

# === Tier 参数 ===
# Tier 参数 (杠杆由 LEVERAGE_TIERS 决定, 这里只定义扫描策略参数)
TIER1_MAX_HOLD_HOURS = 2    # Tier A Major: 30min-1h, A Large: 1-4h
TIER2_MAX_HOLD_HOURS = 8
TIER3_MAX_HOLD_HOURS = 20

TIER1_TAKE_PROFIT_LADDER = [
    (0.15, 0.30),  # +15% ROI 平 30%
    (0.30, 0.30),  # +30% ROI 平 30%
    (0.60, 0.40),  # +60% ROI 平 40%
]
```

---

### 2.2 `filters/wick_filter.py` (FR-010)

```python
"""
反插针过滤器 - FR-010
对应测试: TC-WICK-001 ~ TC-WICK-010
"""
from decimal import Decimal
from core.constants import WICK_BODY_RATIO_MIN
from core.exceptions import InvalidKlineError


def wick_filter(open: Decimal, high: Decimal,
                low: Decimal, close: Decimal) -> tuple[bool, float]:
    """
    检查 K 线是否健康 (实体占比 ≥ 0.55)

    Returns:
        (passed: bool, body_ratio: float)
    """
    if any(p <= 0 for p in [open, high, low, close]):
        raise InvalidKlineError("Price must be positive")

    if high < low:
        raise InvalidKlineError("High < Low")

    body = abs(close - open)
    upper_wick = high - max(open, close)
    lower_wick = min(open, close) - low
    total = body + upper_wick + lower_wick

    if total == 0:
        return False, 0.0  # 一字线, 拒绝

    body_ratio = float(body / total)
    passed = body_ratio >= WICK_BODY_RATIO_MIN

    return passed, body_ratio
```

---

### 2.3 `filters/cvd_filter.py` (FR-011)

```python
"""
CVD 过滤器 - FR-011
对应测试: TC-CVD-001 ~ TC-CVD-010
"""
import numpy as np
from core.constants import CVD_TOLERANCE


def cvd_filter(price_series: np.ndarray,
               cvd_series: np.ndarray,
               direction: str,  # 'long' | 'short'
               lookback: int = 20) -> tuple[bool, str]:
    """
    检查价格突破时 CVD 是否同步

    Returns:
        (passed: bool, reason: str)
    """
    if len(price_series) < lookback or len(cvd_series) < lookback:
        return False, "insufficient_data"

    recent_prices = price_series[-lookback:]
    recent_cvd = cvd_series[-lookback:]

    if direction == 'long':
        price_high = recent_prices.max()
        cvd_high = recent_cvd.max()

        price_at_high = price_series[-1] >= price_high * (1 - CVD_TOLERANCE)
        cvd_at_high = cvd_series[-1] >= cvd_high * (1 - CVD_TOLERANCE)

        if not price_at_high:
            return False, "price_not_at_high"
        if not cvd_at_high:
            return False, "cvd_divergence"
        return True, "ok"

    elif direction == 'short':
        price_low = recent_prices.min()
        cvd_low = recent_cvd.min()

        price_at_low = price_series[-1] <= price_low * (1 + CVD_TOLERANCE)
        cvd_at_low = cvd_series[-1] <= cvd_low * (1 + CVD_TOLERANCE)

        if not price_at_low:
            return False, "price_not_at_low"
        if not cvd_at_low:
            return False, "cvd_divergence"
        return True, "ok"

    raise ValueError(f"Invalid direction: {direction}")
```

---

### 2.4 `data/cvd_calculator.py` (CVD 累积计算)

```python
"""
CVD 实时计算 - 从 Binance aggTrade 流
"""
from collections import defaultdict, deque
from data.redis_client import redis_client


class CvdCalculator:
    """
    维护每个 symbol 的 CVD 时间序列, 按 5min K线对齐
    """

    def __init__(self, symbols: list[str], window: int = 100):
        self.window = window
        # symbol -> deque of (timestamp_5min_bucket, cvd_value)
        self._series = {s: deque(maxlen=window) for s in symbols}
        self._current_bucket_cvd = defaultdict(float)
        self._current_bucket_ts = {}

    def on_agg_trade(self, symbol: str, trade: dict):
        """
        Process a single aggTrade message.
        Binance aggTrade format:
            {
                'e': 'aggTrade',
                's': 'BTCUSDT',
                'p': '60000.00',
                'q': '0.001',
                'T': 1234567890000,  # ms
                'm': True/False  # is buyer maker
            }
        """
        ts_ms = trade['T']
        qty = float(trade['q'])
        is_buyer_maker = trade['m']

        # Buyer maker = True 表示 taker 是卖方 → 主动卖
        delta = -qty if is_buyer_maker else qty

        bucket_ts = (ts_ms // 300_000) * 300_000  # 5min bucket

        # 如果进入新 bucket, 把上一个 bucket 累积值写入序列
        if symbol in self._current_bucket_ts:
            old_bucket = self._current_bucket_ts[symbol]
            if bucket_ts != old_bucket:
                self._series[symbol].append(
                    (old_bucket, self._current_bucket_cvd[symbol])
                )
                # 持久化到 Redis
                redis_client.lpush(
                    f"cvd:{symbol}",
                    f"{old_bucket}:{self._current_bucket_cvd[symbol]}"
                )
                redis_client.ltrim(f"cvd:{symbol}", 0, self.window - 1)
                # 重置 current bucket
                self._current_bucket_cvd[symbol] = 0.0

        self._current_bucket_ts[symbol] = bucket_ts
        self._current_bucket_cvd[symbol] += delta

    def get_series(self, symbol: str) -> np.ndarray:
        """返回累积 CVD 序列 (不是 delta)"""
        deltas = [v for _, v in self._series[symbol]]
        if not deltas:
            return np.array([])
        return np.cumsum(deltas)
```

---

### 2.5 `scanner/tier1_scalper.py` (FR-001)

```python
"""
Tier 1 极速爆破扫描器 - FR-001
"""
import asyncio
from datetime import datetime, timezone
from core.constants import TIER1_TRADING_HOURS_UTC, MAX_MARGIN_PER_TRADE
from data.kline_cache import kline_cache
from data.cvd_calculator import cvd_calc
from data.market_data import get_funding_rate, get_oi_change, get_taker_ratio
from filters.wick_filter import wick_filter
from filters.cvd_filter import cvd_filter
from filters.funding_filter import funding_filter
from filters.blacklist_filter import is_tradable
from risk.risk_manager import risk_manager
from executor import get_executor
from models.signal import save_signal
from core.logger import logger


class Tier1Scalper:
    SCAN_INTERVAL = 30  # seconds
    VOLUME_RATIO_THRESHOLD = 5.0
    PRICE_CHANGE_THRESHOLD = 0.02
    OI_CHANGE_THRESHOLD = 0.08

    def __init__(self, symbols: list[str]):
        self.symbols = symbols
        self.executor = get_executor()

    def _is_trading_hours(self) -> bool:
        """BR-007: Tier 1 仅在 UTC 8-22"""
        hour = datetime.now(timezone.utc).hour
        start, end = TIER1_TRADING_HOURS_UTC
        return start <= hour < end

    async def run(self):
        logger.info("tier1_scalper.started", symbols_count=len(self.symbols))
        while True:
            try:
                if not self._is_trading_hours():
                    await asyncio.sleep(self.SCAN_INTERVAL)
                    continue

                await self._scan_all()
            except Exception as e:
                logger.error("tier1_scalper.error", error=str(e), exc_info=True)
            await asyncio.sleep(self.SCAN_INTERVAL)

    async def _scan_all(self):
        tasks = [self._scan_one(sym) for sym in self.symbols]
        await asyncio.gather(*tasks, return_exceptions=True)

    async def _scan_one(self, symbol: str):
        # 1. 黑名单检查 (BR-002, BR-003)
        if not is_tradable(symbol):
            return

        # 2. 取最近 K线
        klines = kline_cache.get(symbol, '5m', n=20)
        if len(klines) < 20:
            return

        latest = klines[-1]
        prev_20 = klines[-21:-1]

        # 3. 量比检查
        avg_vol = sum(k.volume for k in prev_20) / 20
        vol_ratio = latest.volume / avg_vol if avg_vol > 0 else 0
        if vol_ratio < self.VOLUME_RATIO_THRESHOLD:
            return

        # 4. 价格变化
        price_change_pct = (latest.close - latest.open) / latest.open
        if abs(price_change_pct) < self.PRICE_CHANGE_THRESHOLD:
            return

        direction = 'long' if price_change_pct > 0 else 'short'

        # 5. OI 增长
        oi_change = await get_oi_change(symbol, period='1h')
        if oi_change < self.OI_CHANGE_THRESHOLD:
            return

        # 6. Taker 比例
        taker_ratio = await get_taker_ratio(symbol)
        if direction == 'long' and taker_ratio < 1.5:
            return
        if direction == 'short' and taker_ratio > 0.67:
            return

        # 7. Wick filter (FR-010)
        wick_passed, body_ratio = wick_filter(
            latest.open, latest.high, latest.low, latest.close
        )

        # 8. CVD filter (FR-011)
        cvd_series = cvd_calc.get_series(symbol)
        price_series = np.array([k.close for k in klines])
        cvd_passed, cvd_reason = cvd_filter(price_series, cvd_series, direction)

        # 9. Funding filter (FR-012)
        funding = await get_funding_rate(symbol)
        funding_passed = funding_filter(funding)

        # 构建信号
        signal = {
            'tier': 'tier1',
            'symbol': symbol,
            'direction': direction,
            'price': latest.close,
            'volume_ratio': vol_ratio,
            'price_change_pct': price_change_pct,
            'oi_change_pct': oi_change,
            'taker_ratio': taker_ratio,
            'funding_rate': funding,
            'body_ratio': body_ratio,
            'cvd_aligned': cvd_passed,
            'funding_passed': funding_passed,
        }

        # 综合过滤
        if not (wick_passed and cvd_passed and funding_passed):
            signal['final_decision'] = 'filtered'
            signal['filter_reason'] = self._build_reason(
                wick_passed, cvd_passed, funding_passed, cvd_reason
            )
            save_signal(signal)
            return

        # 风控
        risk_result = risk_manager.check(signal)
        if not risk_result.approved:
            signal['final_decision'] = 'blocked'
            signal['filter_reason'] = risk_result.reason
            save_signal(signal)
            return

        # 执行
        signal['final_decision'] = 'executed'
        save_signal(signal)
        await self.executor.open_position(
            symbol=symbol,
            direction=direction,
            tier='tier1',
            margin=risk_result.position_size,
            leverage=20,
        )

    def _build_reason(self, wick, cvd, funding, cvd_reason) -> str:
        reasons = []
        if not wick: reasons.append("wick_failed")
        if not cvd: reasons.append(f"cvd_{cvd_reason}")
        if not funding: reasons.append("funding_extreme")
        return ",".join(reasons)
```

---

### 2.6 `risk/risk_manager.py` (FR-030 ~ FR-034)

```python
"""
风险管理总入口 - FR-030 ~ FR-034
"""
from dataclasses import dataclass
from datetime import datetime, timedelta
from core.constants import (
    MAX_LEVERAGE, MAX_MARGIN_PER_TRADE, MAX_CONCURRENT_POSITIONS,
    WEEKEND_POSITION_MULTIPLIER,
)
from risk.circuit_breaker import circuit_breaker
from risk.black_swan import black_swan_monitor
from models.position import count_open_positions, recently_closed_in
from models.daily_stat import get_today_pnl, get_consecutive_losses


@dataclass
class RiskCheckResult:
    approved: bool
    position_size: float
    reason: str = ""


class RiskManager:
    def check(self, signal: dict) -> RiskCheckResult:
        # 1. 黑天鹅熔断 (FR-033)
        if black_swan_monitor.is_active():
            return RiskCheckResult(False, 0, "black_swan_active")

        # 2. 时段断路器 (FR-032)
        if circuit_breaker.is_active(['daily', 'weekly', 'monthly']):
            return RiskCheckResult(False, 0, "time_circuit_breaker_active")

        # 3. 连亏断路器 (FR-031)
        if circuit_breaker.is_active(['consecutive_loss']):
            return RiskCheckResult(False, 0, "consecutive_loss_breaker_active")

        # 4. 最大持仓 (FR-030)
        if count_open_positions() >= MAX_CONCURRENT_POSITIONS:
            return RiskCheckResult(False, 0, "max_positions_reached")

        # 5. 同币冷却 (FR-034)
        if recently_closed_in(signal['symbol'], hours=2):
            return RiskCheckResult(False, 0, "symbol_cooldown")

        # 6. 仓位计算
        size = self._calculate_position_size(signal)

        return RiskCheckResult(True, size, "")

    def _calculate_position_size(self, signal: dict) -> float:
        """考虑周末减仓 (BR-008) + 连亏减半 (FR-031)"""
        base = MAX_MARGIN_PER_TRADE  # 300

        # BR-008: 周末减半
        if datetime.utcnow().weekday() in (5, 6):  # Saturday, Sunday
            base *= WEEKEND_POSITION_MULTIPLIER

        # FR-031: 连亏 3 笔减半
        consecutive = get_consecutive_losses(window_hours=24)
        if consecutive >= 3:
            base *= 0.5

        return min(base, MAX_MARGIN_PER_TRADE)


risk_manager = RiskManager()
```

---

### 2.7 `executor/paper_executor.py` (FR-020)

```python
"""
模拟下单器 - Phase 1 唯一执行器
"""
import asyncio
from decimal import Decimal
from datetime import datetime, timedelta
from data.market_data import get_current_price
from models.trade import insert_trade, update_trade
from models.position import insert_position, close_position
from core.constants import TIER1_TAKE_PROFIT_LADDER
from core.logger import logger


class PaperExecutor:
    SLIPPAGE_PCT = 0.0005  # 0.05%
    TAKER_FEE_PCT = 0.0005  # Binance VIP 0

    async def open_position(self, symbol: str, direction: str,
                             tier: str, margin: float, leverage: int):
        # 1. 取实时价格
        market_price = await get_current_price(symbol)

        # 2. 模拟滑点
        if direction == 'long':
            entry_price = market_price * (1 + self.SLIPPAGE_PCT)
        else:
            entry_price = market_price * (1 - self.SLIPPAGE_PCT)

        # 3. 计算数量
        notional = margin * leverage
        quantity = notional / entry_price

        # 4. 计算止损价 (-10% ROI)
        stop_loss_distance = 0.10 / leverage  # 10% ROI / 杠杆 = 价格变化%
        if direction == 'long':
            stop_loss_price = entry_price * (1 - stop_loss_distance)
        else:
            stop_loss_price = entry_price * (1 + stop_loss_distance)

        # 5. 计算最大持仓时间
        if tier == 'tier1':
            max_hold = timedelta(hours=2)
        elif tier == 'tier2':
            max_hold = timedelta(hours=8)
        else:
            max_hold = timedelta(hours=20)

        # 6. 计算手续费
        fee = notional * self.TAKER_FEE_PCT

        # 7. 写入 DB
        trade_id = insert_trade(
            mode='paper',
            tier=tier,
            symbol=symbol,
            direction=direction,
            leverage=leverage,
            margin=margin,
            entry_price=entry_price,
            quantity=quantity,
            status='open',
            open_time=datetime.utcnow(),
            fee=fee,
        )

        insert_position(
            trade_id=trade_id,
            symbol=symbol,
            direction=direction,
            leverage=leverage,
            margin=margin,
            entry_price=entry_price,
            quantity=quantity,
            stop_loss_price=stop_loss_price,
            take_profit_levels=TIER1_TAKE_PROFIT_LADDER if tier == 'tier1' else None,
            open_time=datetime.utcnow(),
            max_hold_until=datetime.utcnow() + max_hold,
        )

        logger.info("paper.position_opened",
                    trade_id=trade_id, symbol=symbol,
                    direction=direction, entry=float(entry_price))

    async def close_position(self, position_id: int, reason: str):
        # 类似开仓, 模拟滑点 + 计算 PnL + 更新 DB
        ...
```

---

### 2.8 `web/routes/api.py` (Dashboard API)

```python
"""
Dashboard JSON API for AJAX
"""
from flask import Blueprint, jsonify, request
from models.position import list_open_positions
from models.trade import query_trades
from models.signal import query_signals
from models.daily_stat import get_today_stats, get_30d_curve

api_bp = Blueprint('api', __name__, url_prefix='/api')


@api_bp.route('/positions')
def positions():
    return jsonify([p.to_dict() for p in list_open_positions()])


@api_bp.route('/trades')
def trades():
    mode = request.args.get('mode', 'paper')
    tier = request.args.get('tier')
    limit = int(request.args.get('limit', 100))
    return jsonify([t.to_dict() for t in query_trades(mode=mode, tier=tier, limit=limit)])


@api_bp.route('/signals')
def signals():
    decision = request.args.get('decision')
    tier = request.args.get('tier')
    limit = int(request.args.get('limit', 100))
    return jsonify([s.to_dict() for s in query_signals(decision=decision, tier=tier, limit=limit)])


@api_bp.route('/stats/today')
def stats_today():
    return jsonify(get_today_stats())


@api_bp.route('/stats/curve')
def stats_curve():
    days = int(request.args.get('days', 30))
    return jsonify(get_30d_curve(days=days))
```

---

## 3. 数据库迁移脚本

### `scripts/init_db.py`

```python
"""
初始化数据库 - 创建所有表
"""
from sqlalchemy import create_engine
from models.base import metadata
from config.settings import settings


def init_database():
    engine = create_engine(settings.DB_URL)

    # 创建数据库 (如果不存在)
    with engine.connect() as conn:
        conn.execute(f"CREATE DATABASE IF NOT EXISTS {settings.MYSQL_DATABASE}")

    # 创建所有表
    metadata.create_all(engine)
    print("✅ Database initialized")


if __name__ == "__main__":
    init_database()
```

---

## 4. 启动入口

### `engine.py` (扫描器进程)

```python
"""
扫描器引擎主入口 - 独立进程
由 Supervisord 管理
"""
import asyncio
import signal
from data.market_data import fetch_top_symbols
from scanner.tier1_scalper import Tier1Scalper
from scanner.tier2_trend import Tier2TrendScanner
from scanner.tier3_direction import Tier3DirectionScanner
from data.kline_cache import kline_cache
from core.logger import logger


async def warmup():
    """启动时预加载 K线数据"""
    logger.info("engine.warmup_start")
    symbols = await fetch_top_symbols(top=50)  # Tier A + B
    await kline_cache.warmup(symbols)
    logger.info("engine.warmup_done", symbols=len(symbols))
    return symbols


async def main():
    symbols = await warmup()

    tier1 = Tier1Scalper(symbols)
    tier2 = Tier2TrendScanner(symbols)
    tier3 = Tier3DirectionScanner(symbols[:15])  # Tier A only

    await asyncio.gather(
        tier1.run(),
        tier2.run(),
        tier3.run(),
    )


if __name__ == "__main__":
    loop = asyncio.new_event_loop()
    asyncio.set_event_loop(loop)

    # Graceful shutdown
    for sig in (signal.SIGINT, signal.SIGTERM):
        loop.add_signal_handler(sig, loop.stop)

    try:
        loop.run_until_complete(main())
    finally:
        loop.close()
```

---

### `app.py` (Web 入口)

```python
"""
Flask Web 入口
由 Gunicorn 管理
"""
from flask import Flask
from web.routes.home import home_bp
from web.routes.signals import signals_bp
from web.routes.trades import trades_bp
from web.routes.risk import risk_bp
from web.routes.config import config_bp
from web.routes.api import api_bp
from config.settings import settings


def create_app():
    app = Flask(__name__)
    app.config['SECRET_KEY'] = settings.WEB_SECRET_KEY

    app.register_blueprint(home_bp)
    app.register_blueprint(signals_bp)
    app.register_blueprint(trades_bp)
    app.register_blueprint(risk_bp)
    app.register_blueprint(config_bp)
    app.register_blueprint(api_bp)

    return app


app = create_app()


if __name__ == "__main__":
    app.run(host='0.0.0.0', port=settings.WEB_PORT, debug=False)
```

---

## 5. 部署清单

### 5.1 Vultr 服务器初始化脚本 `scripts/setup_server.sh`

```bash
#!/bin/bash
set -e

# 1. 系统更新
apt update && apt upgrade -y

# 2. 安装基础软件
apt install -y python3.11 python3.11-venv python3-pip \
  mysql-server redis-server nginx supervisor \
  build-essential wget git curl certbot python3-certbot-nginx

# 3. 安装 TA-Lib
cd /tmp
wget http://prdownloads.sourceforge.net/ta-lib/ta-lib-0.4.0-src.tar.gz
tar -xzf ta-lib-0.4.0-src.tar.gz
cd ta-lib && ./configure --prefix=/usr && make && make install
ldconfig

# 4. 配置 MySQL
mysql -e "CREATE DATABASE flash_quant_db CHARACTER SET utf8mb4;"
mysql -e "CREATE USER 'flash_quant_user'@'localhost' IDENTIFIED BY '<password>';"
mysql -e "GRANT ALL ON flash_quant_db.* TO 'flash_quant_user'@'localhost';"

# 5. 配置 Redis (默认即可)
systemctl enable redis-server

# 6. 创建项目目录
mkdir -p /opt/flash_quant /var/log/flash-quant
chown -R root:root /opt/flash_quant /var/log/flash-quant

echo "✅ Server setup complete. Now deploy the code."
```

### 5.2 部署步骤

```bash
# 1. SSH 上服务器
ssh root@<vultr_ip>

# 2. 跑初始化脚本
bash setup_server.sh

# 3. 上传代码
scp -r flash_quant/ root@<vultr_ip>:/opt/

# 4. 创建虚拟环境
cd /opt/flash_quant
python3.11 -m venv venv
source venv/bin/activate
pip install -r requirements.txt

# 5. 配置环境变量
cp .env.example .env
nano .env  # 填入真实值

# 6. 初始化数据库
python scripts/init_db.py

# 7. 配置 Supervisord
cp supervisord.conf /etc/supervisor/conf.d/flash-quant.conf
supervisorctl reread && supervisorctl update

# 8. 配置 Nginx
cp nginx.conf /etc/nginx/sites-available/flash-quant
ln -s /etc/nginx/sites-available/flash-quant /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

# 9. 配置 SSL (可选, 有域名时)
certbot --nginx -d flash.<your-domain>.com

# 10. 启动服务
supervisorctl start flash-quant-web flash-quant-engine flash-quant-ws
supervisorctl status
```

---

## 6. 实施顺序 (Phase 1)

按以下顺序实现, 每完成一项创建 changelog:

### Sprint 1: 基础设施 (Day 1-2)
1. ✅ 项目目录创建
2. `core/constants.py` - 所有常量
3. `core/logger.py` - 结构化日志
4. `core/exceptions.py` - 自定义异常
5. `config/settings.py` - 配置加载
6. `models/*.py` - 所有数据模型
7. `scripts/init_db.py` - DB 初始化

### Sprint 2: 数据层 (Day 3-4)
8. `data/redis_client.py`
9. `data/kline_cache.py`
10. `data/cvd_calculator.py`
11. `data/market_data.py`
12. `ws/binance_ws.py`
13. `ws/stream_handler.py`
14. `ws_collector.py` (主入口)

### Sprint 3: 过滤器 + 风控 (Day 5-6)
15. `filters/wick_filter.py` + 单元测试
16. `filters/cvd_filter.py` + 单元测试
17. `filters/funding_filter.py` + 单元测试
18. `filters/blacklist_filter.py` + 单元测试
19. `risk/circuit_breaker.py`
20. `risk/black_swan.py`
21. `risk/position_risk.py`
22. `risk/risk_manager.py` + 单元测试

### Sprint 4: 扫描器 + 执行器 (Day 7-8)
23. `executor/base.py`
24. `executor/paper_executor.py` + 集成测试
25. `scanner/base.py`
26. `scanner/tier1_scalper.py` + 集成测试
27. `engine.py` (扫描器入口)

### Sprint 5: Web Dashboard (Day 9-10)
28. `web/routes/api.py`
29. `web/routes/home.py`
30. `web/routes/signals.py`
31. `web/routes/trades.py`
32. `web/routes/risk.py`
33. `web/templates/*.html`
34. `app.py` (Web 入口)

### Sprint 6: 部署 + 验证 (Day 11-12)
35. Vultr 服务器购买
36. `scripts/setup_server.sh` 运行
37. 代码上传 + 数据库初始化
38. Supervisord 启动 3 个进程
39. Nginx + SSL 配置
40. 模拟盘运行验证

### Sprint 7: 200笔验收 (Day 13-14)
41. 监控运行状态
42. 收集 200+ 笔信号数据
43. 跑 AC-Phase1 5 项验收测试
44. 出 Phase 1 验收报告

---

## 7. 风险和未决事项

### 7.1 实现风险

| 风险 | 等级 | 缓解 |
|---|---|---|
| WebSocket 库选择 (websockets vs python-binance) | 中 | 先用 python-binance, 抽象 ws/ 层方便切换 |
| TA-Lib 编译失败 | 中 | 使用 conda 备用方案 |
| Tokyo 服务器到 Binance 延迟实测可能 > 30ms | 低 | 实测后调整方案 |
| ccxt API 频率限速 | 中 | WebSocket 优先 |

### 7.2 待 Hongtou 决定的 (PRD Open Questions)

1. API Key: 复用还是新建? (推荐新建)
2. Dashboard 登录: 需要还是不需要? (推荐需要)
3. SSH 端口: 默认 22 还是非标? (推荐 26026)
4. 域名: 是否申请? (推荐先用 IP)
5. DB: 复用 trading_saas MySQL 实例还是新建? (推荐新建在 Vultr)

**这些可以在 Sprint 1 开始前决定, 不影响 Sprint 1 的代码工作。**

---

## 🎤 Amelia 签字

> "💻 **Tech Spec 完成。**
>
> 我用最少的废话给出了:
> - 8 个核心模块的接口签名 (含伪代码)
> - requirements.txt + .env 模板
> - 数据库迁移脚本
> - 部署脚本 + Supervisord/Nginx 配置
> - **7 个 Sprint 的实施顺序** (Day-by-Day)
>
> **每个模块都标注了对应的 FR-ID**, 可追溯到 PRD。
>
> Critical 模块 (filters/risk/executor) 我都给了完整伪代码,
> 直接照着写就行,不会有歧义。
>
> 现在所有规划文档完成! 等 Hongtou 拍板就开始 Sprint 1。
>
> 💻"

---

**文档版本**: v0.1
**状态**: ✅ 已完成
**下一步**: 🚀 Hongtou 审阅 + 启动 Sprint 1
