# 量化交易系统开发规格书 V4-Lite
## 直接开发版 | V3 核心 + 4 个免费增强模块

> **本文档只有一个用途：拿去写代码。**  
> 策略原理、对比分析、收益预测等内容不在本文档范围内，请参考 V1-V4 策略文档。

---

# 目录

```
1.  系统参数总表
2.  配置文件 (config.yaml)
3.  项目结构
4.  数据模型定义
5.  模块 01：交易所接口
6.  模块 02：数据采集与缓存
7.  模块 03：技术指标计算
8.  模块 04：市场状态识别
9.  模块 05：币种筛选管道
10. 模块 06：信号检测引擎（5 套策略）
11. 模块 07：订单簿深度分析 [V4 增强]
12. 模块 08：多交易所价格共识 [V4 增强]
13. 模块 09：信号聚合与增强检查
14. 模块 10：仓位计算
15. 模块 11：智能下单引擎 [V4 增强]
16. 模块 12：止盈止损管理
17. 模块 13：持仓监控
18. 模块 14：风控中心（四层）
19. 模块 15：分时段参数管理 [V4 增强]
20. 模块 16：自适应参数引擎
21. 模块 17：主控循环
22. 模块 18：回测引擎
23. 模块 19：监控与告警
24. 开发任务拆解与排期
25. 测试清单
```

---

# 1. 系统参数总表

| 参数 | 值 | 说明 |
|------|------|------|
| 本金 | 2,000 USDT | 合约账户 |
| 杠杆 | 10x 逐仓 | 永远不用全仓 |
| 交易对类型 | USDT 永续合约 | 不做现货/交割 |
| 全市场扫描 | 150 个币 | 每 5 分钟 |
| 候选池 | Top 15 | 评分 ≥ 60 |
| 趋势判断周期 | 1H | EMA 20/50/200 + ADX |
| 入场执行周期 | 15m | 回踩/突破确认 |
| 市场状态判断 | 1H + 4H (BTC) | 6 种状态 |
| 单笔风险 | 0.4% 本金 = 8U | 固定风险法 |
| 止损 | 1.2 × ATR(14) | 15m 级别 |
| 止盈 | 1.5R / 2.5R 分批 | 各平 50% |
| 最大持仓 | 3-4 笔 | 按时段调整 |
| 日止损 | -3% = -60U | 硬阈值停机 |
| 日止盈 | +5% 降频 / +8% 停机 | 保护利润 |
| 连亏暂停 | 5 笔 | 冷却 30 分钟 |
| 最大总回撤 | -15% | 停机待审 |

---

# 2. 配置文件

文件路径: `config/config.yaml`

```yaml
# ============================================================
# 量化交易系统配置 V4-Lite
# ============================================================

account:
  balance: 2000
  leverage: 10
  margin_mode: isolated
  exchange: binance
  api_key: "${EXCHANGE_API_KEY}"
  api_secret: "${EXCHANGE_API_SECRET}"

symbols:
  universe: 150
  scan_interval_sec: 300
  candidate_pool_size: 15
  min_listing_days: 7
  blacklist: []  # 手动排除的币种

timeframes:
  trend_tf: 1h
  entry_tf: 15m
  regime_tf_short: 1h
  regime_tf_long: 4h
  regime_reference: "BTC/USDT:USDT"

trend_filter:
  ema_fast: 20
  ema_mid: 50
  ema_slow: 200
  adx_period: 14
  adx_strong: 25
  adx_weak: 20

volatility_filter:
  atr_period: 14
  atrp_min_pct: 0.8
  atrp_max_pct: 4.5

liquidity_filter:
  min_24h_quote_volume: 5000000
  max_spread_pct: 0.15
  min_volume_ratio: 1.2

ranking:
  top_n: 15
  min_score: 60
  weights:
    ema_alignment: 30
    adx_strength: 25
    momentum: 20
    volume_expansion: 15
    liquidity: 10

risk:
  per_trade_risk_pct: 0.4
  daily_loss_limit_pct: 3.0
  daily_profit_target_pct: 5.0
  daily_profit_hard_stop_pct: 8.0
  max_consecutive_losses: 5
  cooldown_after_streak_min: 30
  max_open_positions: 4
  max_same_direction: 3
  max_single_margin_pct: 5.0
  max_total_margin_pct: 40.0
  same_symbol_daily_loss_pct: 1.0
  total_drawdown_stop_pct: 15.0
  weekly_loss_limit_pct: 8.0
  max_correlation: 0.75

execution:
  stop_atr_multiple: 1.2
  tp1_r: 1.5
  tp1_close_pct: 0.50
  tp2_r: 2.5
  tp2_close_pct: 0.50
  min_risk_reward: 1.8
  breakeven_after_r: 1.0
  trailing_enabled: true
  trailing_activate_r: 1.5
  trailing_lock_pct: 0.60
  time_stop_minutes: 60
  time_stop_min_r: 0.3

strategies:
  trend_follow:
    enabled: true
    regimes: [strong_trend_up, weak_trend_up, strong_trend_down, weak_trend_down]
  pullback_breakout:
    enabled: true
    regimes: [strong_trend_up, weak_trend_up, strong_trend_down, weak_trend_down]
  mean_reversion:
    enabled: false  # Phase 2 开启
    regimes: [ranging]
  volatility_breakout:
    enabled: false  # Phase 2 开启
    regimes: [ranging, weak_trend_up, weak_trend_down]
  funding_arbitrage:
    enabled: false  # Phase 3 开启
    regimes: [extreme_volatile, ranging]
    min_funding_rate: 0.001

regime:
  enabled: false  # Phase 1 关闭，Phase 2 开启
  update_interval_sec: 300

adaptive:
  enabled: false  # Phase 3 开启
  lookback_days: 7

# ============================================================
# V4 增强模块
# ============================================================

orderbook:
  enabled: true
  wall_threshold: 3.0
  imbalance_min_ratio: 0.65
  analysis_zone_pct: 0.5
  depth_levels: 20

smart_order:
  enabled: true
  maker_wait_sec: 3
  chase_wait_sec: 3
  max_slippage_pct: 0.05
  post_only: true
  urgent_types: [stop_loss, liquidation_risk]

multi_exchange:
  enabled: true
  exchanges:
    - name: binance
      type: primary
    - name: bybit
      type: secondary
    - name: okx
      type: secondary
  update_interval_sec: 30
  consensus_reject_below: 0.3
  consensus_weak_below: 0.6

session:
  enabled: true
  zones:
    asia:
      utc_hours: [0,1,2,3,4,5,6,7]
      risk_per_trade_pct: 0.3
      max_positions: 2
      min_adx: 28
      min_score: 70
      min_volume_ratio: 1.0
    europe:
      utc_hours: [8,9,10,11,12,13,14,15]
      risk_per_trade_pct: 0.4
      max_positions: 3
      min_adx: 25
      min_score: 65
      min_volume_ratio: 1.2
    america:
      utc_hours: [16,17,18,19,20,21,22,23]
      risk_per_trade_pct: 0.4
      max_positions: 4
      min_adx: 25
      min_score: 60
      min_volume_ratio: 1.3

# ============================================================
# 基础设施
# ============================================================

database:
  postgres:
    host: localhost
    port: 5432
    db: quant_trading
    user: "${DB_USER}"
    password: "${DB_PASSWORD}"
  redis:
    host: localhost
    port: 6379
    db: 0

logging:
  level: INFO
  file: logs/trading.log
  max_size_mb: 100
  backup_count: 10

alerts:
  telegram:
    enabled: true
    bot_token: "${TELEGRAM_BOT_TOKEN}"
    chat_id: "${TELEGRAM_CHAT_ID}"
    notify_on: [trade_open, trade_close, daily_report, risk_alert, error]
```

---

# 3. 项目结构

```
quant-trading/
├── config/
│   ├── config.yaml              # 主配置
│   ├── config.dev.yaml          # 开发环境
│   ├── config.backtest.yaml     # 回测配置
│   └── economic_calendar.json   # 经济日历（手动更新）
│
├── src/
│   ├── __init__.py
│   ├── main.py                  # 入口
│   │
│   ├── core/
│   │   ├── __init__.py
│   │   ├── config.py            # 配置加载
│   │   ├── models.py            # 数据模型
│   │   ├── enums.py             # 枚举定义
│   │   └── exceptions.py        # 自定义异常
│   │
│   ├── exchange/
│   │   ├── __init__.py
│   │   ├── base.py              # 交易所抽象基类
│   │   ├── binance_client.py    # Binance 实现
│   │   ├── bybit_client.py      # Bybit 实现（多所共识用）
│   │   ├── okx_client.py        # OKX 实现（多所共识用）
│   │   └── smart_router.py      # 智能下单引擎 [V4]
│   │
│   ├── data/
│   │   ├── __init__.py
│   │   ├── market_data.py       # K 线/行情管理
│   │   ├── orderbook.py         # 订单簿采集 [V4]
│   │   ├── cache.py             # Redis 缓存
│   │   └── db.py                # PostgreSQL 操作
│   │
│   ├── indicators/
│   │   ├── __init__.py
│   │   ├── trend.py             # EMA, ADX, MACD
│   │   ├── momentum.py          # RSI, ROC
│   │   ├── volatility.py        # ATR, ATRP, Bollinger
│   │   └── volume.py            # 量比, OI
│   │
│   ├── analysis/
│   │   ├── __init__.py
│   │   ├── regime.py            # 市场状态识别
│   │   ├── screener.py          # 币种筛选管道
│   │   ├── correlation.py       # 相关性计算
│   │   ├── orderbook_filter.py  # 订单簿分析 [V4]
│   │   └── multi_exchange.py    # 多所共识 [V4]
│   │
│   ├── strategy/
│   │   ├── __init__.py
│   │   ├── base.py              # 策略抽象基类
│   │   ├── trend_follow.py      # 趋势跟踪
│   │   ├── pullback_breakout.py # 箱体突破
│   │   ├── mean_reversion.py    # 均值回归
│   │   ├── vol_breakout.py      # 波动率突破
│   │   ├── funding_arb.py       # 资金费率套利
│   │   └── aggregator.py        # 信号聚合器
│   │
│   ├── risk/
│   │   ├── __init__.py
│   │   ├── position_sizer.py    # 仓位计算
│   │   ├── stop_manager.py      # 止盈止损管理
│   │   ├── risk_control.py      # 四层风控
│   │   ├── portfolio.py         # 持仓管理
│   │   └── session.py           # 分时段参数 [V4]
│   │
│   ├── execution/
│   │   ├── __init__.py
│   │   ├── order_manager.py     # 订单生命周期
│   │   └── position_monitor.py  # 持仓实时监控
│   │
│   ├── adaptive/
│   │   ├── __init__.py
│   │   └── daily_review.py      # 自适应参数
│   │
│   ├── bot/
│   │   ├── __init__.py
│   │   └── engine.py            # 主控循环
│   │
│   ├── backtest/
│   │   ├── __init__.py
│   │   ├── engine.py            # 回测引擎
│   │   ├── simulator.py         # 模拟执行
│   │   └── reporter.py          # 报表生成
│   │
│   └── utils/
│       ├── __init__.py
│       ├── logger.py            # 日志
│       ├── telegram.py          # Telegram 通知
│       └── helpers.py           # 通用工具
│
├── tests/
│   ├── test_indicators.py
│   ├── test_screener.py
│   ├── test_strategies.py
│   ├── test_risk.py
│   ├── test_orderbook.py
│   ├── test_smart_order.py
│   └── test_backtest.py
│
├── scripts/
│   ├── fetch_history.py         # 拉取历史数据
│   ├── run_backtest.py          # 运行回测
│   └── deploy.sh                # 部署脚本
│
├── requirements.txt
├── Dockerfile
├── docker-compose.yaml
└── README.md
```

---

# 4. 数据模型定义

文件: `src/core/models.py`

```python
from dataclasses import dataclass, field
from datetime import datetime
from typing import Optional, List, Dict, Tuple
from enum import Enum


# ============================================================
# 枚举
# ============================================================

class Direction(Enum):
    LONG = 1
    SHORT = -1

class MarketRegime(Enum):
    STRONG_TREND_UP = "strong_trend_up"
    WEAK_TREND_UP = "weak_trend_up"
    RANGING = "ranging"
    WEAK_TREND_DOWN = "weak_trend_down"
    STRONG_TREND_DOWN = "strong_trend_down"
    EXTREME_VOLATILE = "extreme_volatile"

class OrderType(Enum):
    MARKET = "market"
    LIMIT = "limit"
    STOP_MARKET = "stop_market"
    TAKE_PROFIT_MARKET = "take_profit_market"

class FillType(Enum):
    MAKER = "maker"
    CHASE = "chase"
    MARKET = "market"

class SignalStrategy(Enum):
    TREND_FOLLOW = "trend_follow"
    PULLBACK_BREAKOUT = "pullback_breakout"
    MEAN_REVERSION = "mean_reversion"
    VOLATILITY_BREAKOUT = "volatility_breakout"
    FUNDING_ARBITRAGE = "funding_arbitrage"

class SessionName(Enum):
    ASIA = "asia"
    EUROPE = "europe"
    AMERICA = "america"


# ============================================================
# K 线
# ============================================================

@dataclass
class Kline:
    timestamp: int          # 毫秒时间戳
    open: float
    high: float
    low: float
    close: float
    volume: float           # 基础币成交量
    quote_volume: float     # USDT 成交额
    taker_buy_volume: float = 0.0
    trades_count: int = 0

    @property
    def is_bullish(self) -> bool:
        return self.close > self.open

    @property
    def body(self) -> float:
        return abs(self.close - self.open)

    @property
    def upper_shadow(self) -> float:
        return self.high - max(self.open, self.close)

    @property
    def lower_shadow(self) -> float:
        return min(self.open, self.close) - self.low

    @property
    def total_range(self) -> float:
        return self.high - self.low


# ============================================================
# 订单簿
# ============================================================

@dataclass
class OrderBookLevel:
    price: float
    quantity: float

    @property
    def value(self) -> float:
        return self.price * self.quantity

@dataclass
class OrderBook:
    symbol: str
    timestamp: int
    bids: List[OrderBookLevel]  # 买盘，价格从高到低
    asks: List[OrderBookLevel]  # 卖盘，价格从低到高

    @property
    def best_bid(self) -> float:
        return self.bids[0].price if self.bids else 0

    @property
    def best_ask(self) -> float:
        return self.asks[0].price if self.asks else 0

    @property
    def mid_price(self) -> float:
        return (self.best_bid + self.best_ask) / 2

    @property
    def spread_pct(self) -> float:
        if self.best_bid == 0:
            return 999
        return (self.best_ask - self.best_bid) / self.best_bid


# ============================================================
# 交易对信息
# ============================================================

@dataclass
class SymbolInfo:
    symbol: str                  # "BTC/USDT:USDT"
    base: str                    # "BTC"
    quote: str                   # "USDT"
    price_precision: int         # 小数位数
    qty_precision: int           # 数量精度
    min_qty: float               # 最小下单量
    min_notional: float          # 最小名义价值
    tick_size: float             # 最小价格变动
    listing_date: Optional[datetime] = None

    @property
    def listing_days(self) -> int:
        if not self.listing_date:
            return 999
        return (datetime.utcnow() - self.listing_date).days


# ============================================================
# 趋势评分
# ============================================================

@dataclass
class TrendScore:
    symbol: str
    total_score: float           # 0-100
    direction: Direction
    detail: Dict[str, float]     # 各维度分数
    timestamp: datetime = field(default_factory=datetime.utcnow)


# ============================================================
# 交易信号
# ============================================================

@dataclass
class Signal:
    symbol: str
    direction: Direction
    strategy: SignalStrategy
    entry_price: float
    stop_loss: float
    take_profits: List[Tuple[float, float]]  # [(价格, 平仓比例)]
    risk_reward: Optional[float]
    confidence: float = 0.5       # 0.0 ~ 1.0
    trend_score: float = 0.0
    multi_confirm: bool = False
    hold_until_funding: bool = False
    features: Optional[Dict] = None  # ML 特征快照
    timestamp: datetime = field(default_factory=datetime.utcnow)

    @property
    def r_value(self) -> float:
        """每 R 对应的价格距离"""
        return abs(self.entry_price - self.stop_loss)


# ============================================================
# 持仓
# ============================================================

@dataclass
class Position:
    id: str                       # 唯一 ID
    symbol: str
    direction: Direction
    strategy: SignalStrategy
    entry_price: float
    margin: float                 # 保证金
    notional: float               # 名义持仓
    quantity: float               # 持仓数量
    stop_loss: float              # 当前止损价
    initial_stop: float           # 初始止损价
    take_profits: List[Tuple[float, float]]
    best_price: float             # 持仓以来最优价
    open_time: datetime
    remaining_pct: float = 1.0    # 剩余仓位比例
    tp1_hit: bool = False
    fill_type: FillType = FillType.MARKET

    @property
    def r_value(self) -> float:
        return abs(self.entry_price - self.initial_stop)

    @property
    def holding_minutes(self) -> float:
        return (datetime.utcnow() - self.open_time).total_seconds() / 60

    def unrealized_pnl(self, current_price: float) -> float:
        if self.direction == Direction.LONG:
            return (current_price - self.entry_price) * self.quantity * self.remaining_pct
        else:
            return (self.entry_price - current_price) * self.quantity * self.remaining_pct

    def current_r(self, current_price: float) -> float:
        if self.r_value == 0:
            return 0
        if self.direction == Direction.LONG:
            return (current_price - self.entry_price) / self.r_value
        else:
            return (self.entry_price - current_price) / self.r_value


# ============================================================
# 交易记录
# ============================================================

@dataclass
class TradeRecord:
    id: str
    symbol: str
    direction: Direction
    strategy: SignalStrategy
    entry_price: float
    exit_price: float
    quantity: float
    margin: float
    pnl: float                    # 扣费后净盈亏
    fee: float
    fill_type: FillType
    open_time: datetime
    close_time: datetime
    close_reason: str             # "tp1" / "tp2" / "trailing" / "stop_loss" / "time_stop" / ...
    trend_score: float = 0.0
    confidence: float = 0.0
    session: str = ""
    regime: str = ""
    features: Optional[Dict] = None


# ============================================================
# 订单簿分析结果
# ============================================================

@dataclass
class OrderBookAnalysis:
    can_enter: bool
    score_adjustment: int         # -20 ~ +20
    reason: str
    bid_volume_near: float = 0
    ask_volume_near: float = 0
    imbalance_ratio: float = 0.5


# ============================================================
# 多所共识结果
# ============================================================

@dataclass
class ConsensusResult:
    consensus: float              # 0.0 ~ 1.0
    recommendation: str           # "strong" / "normal" / "weak" / "reject"
    price_divergence: Dict[str, float] = field(default_factory=dict)


# ============================================================
# 仓位计算结果
# ============================================================

@dataclass
class PositionSize:
    margin: float                 # 保证金
    notional: float               # 名义持仓
    quantity: float               # 下单数量
    risk_amount: float            # 风险金额
    stop_distance_pct: float      # 止损距离百分比
```

---

# 5. 模块 01：交易所接口

文件: `src/exchange/base.py`

### 接口定义

```python
from abc import ABC, abstractmethod
from typing import List, Dict, Optional
from src.core.models import *


class ExchangeClient(ABC):
    """交易所客户端抽象基类"""

    # ── 市场数据 ──

    @abstractmethod
    async def load_markets(self) -> Dict[str, SymbolInfo]:
        """获取所有 USDT 永续合约信息"""
        pass

    @abstractmethod
    async def fetch_klines(self, symbol: str, timeframe: str,
                           limit: int = 100) -> List[Kline]:
        """获取 K 线"""
        pass

    @abstractmethod
    async def fetch_ticker(self, symbol: str) -> dict:
        """获取实时行情 {last, bid, ask, volume_24h, ...}"""
        pass

    @abstractmethod
    async def fetch_tickers(self, symbols: List[str]) -> Dict[str, dict]:
        """批量获取行情"""
        pass

    @abstractmethod
    async def fetch_orderbook(self, symbol: str, depth: int = 20) -> OrderBook:
        """获取订单簿"""
        pass

    @abstractmethod
    async def fetch_funding_rate(self, symbol: str) -> float:
        """获取当前资金费率"""
        pass

    @abstractmethod
    async def fetch_open_interest(self, symbol: str) -> float:
        """获取持仓量"""
        pass

    # ── 交易操作 ──

    @abstractmethod
    async def set_leverage(self, symbol: str, leverage: int):
        """设置杠杆（逐仓）"""
        pass

    @abstractmethod
    async def place_market_order(self, symbol: str, side: str,
                                  quantity: float) -> dict:
        """市价下单"""
        pass

    @abstractmethod
    async def place_limit_order(self, symbol: str, side: str,
                                 quantity: float, price: float,
                                 post_only: bool = False) -> dict:
        """限价下单"""
        pass

    @abstractmethod
    async def place_stop_order(self, symbol: str, side: str,
                                quantity: float, stop_price: float) -> dict:
        """止损单"""
        pass

    @abstractmethod
    async def place_tp_order(self, symbol: str, side: str,
                              quantity: float, tp_price: float) -> dict:
        """止盈单"""
        pass

    @abstractmethod
    async def cancel_order(self, symbol: str, order_id: str) -> bool:
        """撤单"""
        pass

    @abstractmethod
    async def get_order_status(self, symbol: str, order_id: str) -> dict:
        """查询订单状态"""
        pass

    @abstractmethod
    async def get_position(self, symbol: str) -> Optional[dict]:
        """查询持仓"""
        pass

    @abstractmethod
    async def get_balance(self) -> float:
        """查询可用余额"""
        pass

    # ── WebSocket ──

    @abstractmethod
    async def subscribe_klines(self, symbols: List[str],
                                timeframes: List[str], callback):
        """订阅 K 线推送"""
        pass

    @abstractmethod
    async def subscribe_orderbook(self, symbols: List[str], callback):
        """订阅订单簿推送"""
        pass
```

### Binance 实现要点

文件: `src/exchange/binance_client.py`

```python
import ccxt.async_support as ccxt

class BinanceClient(ExchangeClient):
    def __init__(self, api_key: str, api_secret: str):
        self.exchange = ccxt.binance({
            'apiKey': api_key,
            'secret': api_secret,
            'options': {'defaultType': 'future'},
            'enableRateLimit': True,
        })

    async def load_markets(self) -> Dict[str, SymbolInfo]:
        markets = await self.exchange.load_markets()
        result = {}
        for symbol, info in markets.items():
            if (info['type'] == 'swap' and
                info['quote'] == 'USDT' and
                info['active']):
                result[symbol] = SymbolInfo(
                    symbol=symbol,
                    base=info['base'],
                    quote=info['quote'],
                    price_precision=info['precision']['price'],
                    qty_precision=info['precision']['amount'],
                    min_qty=info['limits']['amount']['min'] or 0,
                    min_notional=info['limits']['cost']['min'] or 0,
                    tick_size=info['precision']['price'],
                )
        return result

    async def fetch_klines(self, symbol, timeframe, limit=100):
        ohlcv = await self.exchange.fetch_ohlcv(symbol, timeframe, limit=limit)
        return [
            Kline(
                timestamp=k[0],
                open=k[1], high=k[2], low=k[3], close=k[4],
                volume=k[5],
                quote_volume=k[5] * k[4],  # 近似
            )
            for k in ohlcv
        ]

    async def fetch_orderbook(self, symbol, depth=20):
        book = await self.exchange.fetch_order_book(symbol, limit=depth)
        return OrderBook(
            symbol=symbol,
            timestamp=book['timestamp'] or 0,
            bids=[OrderBookLevel(b[0], b[1]) for b in book['bids']],
            asks=[OrderBookLevel(a[0], a[1]) for a in book['asks']],
        )

    async def set_leverage(self, symbol, leverage):
        await self.exchange.set_leverage(leverage, symbol,
                                          params={'marginMode': 'isolated'})

    async def place_market_order(self, symbol, side, quantity):
        return await self.exchange.create_market_order(symbol, side, quantity)

    async def place_limit_order(self, symbol, side, quantity, price,
                                 post_only=False):
        params = {}
        if post_only:
            params['timeInForce'] = 'GTX'  # Binance post-only
        return await self.exchange.create_limit_order(
            symbol, side, quantity, price, params=params)

    async def place_stop_order(self, symbol, side, quantity, stop_price):
        return await self.exchange.create_order(
            symbol, 'stop_market', side, quantity,
            params={'stopPrice': stop_price, 'reduceOnly': True})

    async def place_tp_order(self, symbol, side, quantity, tp_price):
        return await self.exchange.create_order(
            symbol, 'take_profit_market', side, quantity,
            params={'stopPrice': tp_price, 'reduceOnly': True})

    # WebSocket 方法需要用 binance 原生 websocket 库实现
    # ccxt 不直接支持，建议用 python-binance 或 websockets 库
```

---

# 6. 模块 02：数据采集与缓存

文件: `src/data/market_data.py`

### 数据管理器规格

```python
class MarketDataManager:
    """
    职责：
    1. 管理所有币种的 K 线数据
    2. 提供缓存层（Redis）避免重复请求
    3. 支持 REST 拉取 + WebSocket 实时更新
    
    缓存策略：
    - 1H K线: 缓存 5 分钟
    - 15m K线: 缓存 1 分钟
    - Ticker: 缓存 10 秒
    - 订单簿: 不缓存（实时推送）
    
    初始化流程：
    1. load_markets() → 获取所有交易对
    2. fetch_initial_klines() → 拉取历史 K 线（每个币 1H×220根 + 15m×100根）
    3. start_websocket() → 启动实时推送
    
    API 限频管理：
    - Binance REST: 1200 权重/分钟
    - fetch_klines 消耗 ~5 权重/次
    - 150 币 × 2 周期 = 300 次 = 1500 权重
    - 解决: 分批拉取，每批 50 币，间隔 30 秒
    
    接口:
    """

    async def get_klines(self, symbol: str, timeframe: str,
                         limit: int = 100) -> List[Kline]:
        """优先从缓存取，缓存过期则拉取"""
        pass

    async def get_ticker(self, symbol: str) -> dict:
        """获取实时行情"""
        pass

    async def get_orderbook(self, symbol: str) -> OrderBook:
        """获取订单簿"""
        pass

    async def get_all_symbols(self) -> List[SymbolInfo]:
        """获取所有交易对信息"""
        pass

    async def start(self):
        """启动数据采集（REST + WebSocket）"""
        pass

    async def stop(self):
        """停止数据采集"""
        pass
```

---

# 7. 模块 03：技术指标计算

文件: `src/indicators/trend.py`, `momentum.py`, `volatility.py`, `volume.py`

### 所有指标的输入输出规格

```python
"""
所有指标函数签名统一：
输入: List[Kline], period: int
输出: float 或 List[float]

使用 numpy 加速计算
"""

import numpy as np
from typing import List
from src.core.models import Kline


# ============================================================
# trend.py - 趋势指标
# ============================================================

def ema(klines: List[Kline], period: int) -> np.ndarray:
    """
    指数移动平均
    输入: K线列表, 周期
    输出: 与 klines 等长的 numpy 数组
    """
    closes = np.array([k.close for k in klines])
    multiplier = 2 / (period + 1)
    result = np.empty_like(closes)
    result[0] = closes[0]
    for i in range(1, len(closes)):
        result[i] = closes[i] * multiplier + result[i-1] * (1 - multiplier)
    return result


def adx(klines: List[Kline], period: int = 14) -> float:
    """
    平均趋向指数
    输入: K线列表（至少 2*period 根）, 周期
    输出: 最新 ADX 值（0-100）
    """
    pass  # 标准 ADX 算法实现


def plus_di(klines: List[Kline], period: int = 14) -> float:
    """正趋向指标"""
    pass


def minus_di(klines: List[Kline], period: int = 14) -> float:
    """负趋向指标"""
    pass


def ema_alignment(klines: List[Kline],
                   fast: int = 20, mid: int = 50, slow: int = 200) -> str:
    """
    EMA 排列状态
    输出: 'bullish' / 'bearish' / 'mixed'
    """
    ema_f = ema(klines, fast)[-1]
    ema_m = ema(klines, mid)[-1]
    ema_s = ema(klines, slow)[-1]
    close = klines[-1].close

    if close > ema_f > ema_m > ema_s:
        return 'bullish'
    elif close < ema_f < ema_m < ema_s:
        return 'bearish'
    return 'mixed'


# ============================================================
# momentum.py - 动量指标
# ============================================================

def rsi(klines: List[Kline], period: int = 14) -> float:
    """
    相对强弱指标
    输出: 0-100
    """
    closes = np.array([k.close for k in klines])
    deltas = np.diff(closes)
    gains = np.where(deltas > 0, deltas, 0)
    losses = np.where(deltas < 0, -deltas, 0)

    avg_gain = np.mean(gains[-period:])
    avg_loss = np.mean(losses[-period:])

    if avg_loss == 0:
        return 100.0
    rs = avg_gain / avg_loss
    return 100 - (100 / (1 + rs))


def roc(klines: List[Kline], period: int = 20) -> float:
    """
    变化率
    输出: 百分比小数（如 0.05 = 5%）
    """
    if len(klines) <= period:
        return 0.0
    return (klines[-1].close - klines[-1-period].close) / klines[-1-period].close


# ============================================================
# volatility.py - 波动率指标
# ============================================================

def atr(klines: List[Kline], period: int = 14) -> float:
    """
    真实波幅均值
    输出: 价格单位
    """
    trs = []
    for i in range(1, len(klines)):
        tr = max(
            klines[i].high - klines[i].low,
            abs(klines[i].high - klines[i-1].close),
            abs(klines[i].low - klines[i-1].close)
        )
        trs.append(tr)
    if len(trs) < period:
        return np.mean(trs) if trs else 0
    return float(np.mean(trs[-period:]))


def atrp(klines: List[Kline], period: int = 14) -> float:
    """
    ATR 百分比
    输出: 百分比小数（如 0.015 = 1.5%）
    """
    atr_val = atr(klines, period)
    if klines[-1].close == 0:
        return 0
    return atr_val / klines[-1].close


def bollinger(klines: List[Kline], period: int = 20,
              std_dev: float = 2.0) -> tuple:
    """
    布林带
    输出: (upper, mid, lower) 最新值
    """
    closes = np.array([k.close for k in klines[-period:]])
    mid = np.mean(closes)
    std = np.std(closes)
    return (mid + std_dev * std, mid, mid - std_dev * std)


# ============================================================
# volume.py - 量能指标
# ============================================================

def volume_ratio(klines: List[Kline], recent: int = 5,
                 baseline: int = 20) -> float:
    """
    量比
    输出: 倍数（如 1.5 = 近期量是均量的 1.5 倍）
    """
    if len(klines) < recent + baseline:
        return 1.0
    recent_avg = np.mean([k.volume for k in klines[-recent:]])
    baseline_avg = np.mean([k.volume for k in klines[-recent-baseline:-recent]])
    if baseline_avg == 0:
        return 1.0
    return float(recent_avg / baseline_avg)


def taker_buy_ratio(klines: List[Kline], period: int = 10) -> float:
    """
    主买占比
    输出: 0-1（0.5 = 买卖均衡）
    """
    total_vol = sum(k.volume for k in klines[-period:])
    if total_vol == 0:
        return 0.5
    buy_vol = sum(k.taker_buy_volume for k in klines[-period:])
    return buy_vol / total_vol
```

---

# 8. 模块 04：市场状态识别

文件: `src/analysis/regime.py`

### 规格

```python
class RegimeDetector:
    """
    输入: BTC 的 1H 和 4H K线
    输出: MarketRegime 枚举
    更新频率: 每 5 分钟
    
    算法:
    1. ADX 方向性（1H）→ 投票
    2. EMA 20/50/200 排列（1H + 4H 一致性）→ 投票
    3. 布林带状态（1H）→ 投票
    4. 波动率百分位 → 极端标记
    5. 综合投票 → 6 种状态之一
    
    投票规则:
    - up_votes >= 4 → STRONG_TREND_UP
    - up_votes >= 2 且 > down_votes → WEAK_TREND_UP
    - down_votes >= 4 → STRONG_TREND_DOWN
    - down_votes >= 2 且 > up_votes → WEAK_TREND_DOWN
    - extreme 且无明确方向 → EXTREME_VOLATILE
    - 其他 → RANGING
    """

    def detect(self, btc_1h: List[Kline], btc_4h: List[Kline]) -> MarketRegime:
        pass
```

### 策略路由表

```python
STRATEGY_ROUTING = {
    MarketRegime.STRONG_TREND_UP: {
        "strategies": ["trend_follow", "pullback_breakout"],
        "direction_bias": "long",
        "position_scale": 1.0,
        "max_positions": 4,
    },
    MarketRegime.WEAK_TREND_UP: {
        "strategies": ["trend_follow", "pullback_breakout", "volatility_breakout"],
        "direction_bias": "long",
        "position_scale": 0.7,
        "max_positions": 3,
    },
    MarketRegime.RANGING: {
        "strategies": ["mean_reversion", "volatility_breakout", "funding_arbitrage"],
        "direction_bias": "both",
        "position_scale": 0.5,
        "max_positions": 3,
    },
    MarketRegime.WEAK_TREND_DOWN: {
        "strategies": ["trend_follow", "pullback_breakout", "volatility_breakout"],
        "direction_bias": "short",
        "position_scale": 0.7,
        "max_positions": 3,
    },
    MarketRegime.STRONG_TREND_DOWN: {
        "strategies": ["trend_follow", "pullback_breakout"],
        "direction_bias": "short",
        "position_scale": 1.0,
        "max_positions": 4,
    },
    MarketRegime.EXTREME_VOLATILE: {
        "strategies": ["funding_arbitrage"],
        "direction_bias": "both",
        "position_scale": 0.2,
        "max_positions": 1,
    },
}

# Phase 1 不启用 regime，默认用固定路由:
DEFAULT_ROUTING = {
    "strategies": ["trend_follow", "pullback_breakout"],
    "direction_bias": "both",
    "position_scale": 1.0,
    "max_positions": 3,
}
```

---

# 9. 模块 05：币种筛选管道

文件: `src/analysis/screener.py`

### 三级筛选流程

```python
class SymbolScreener:
    """
    Level 1: 基础过滤 (150 → ~120)
      - 24h 成交额 >= 5,000,000 USDT
      - spread <= 0.15%
      - ATRP 在 0.8%-4.5% 范围
      - 上线 >= 7 天
      - 不在黑名单

    Level 2: 趋势评分 (120 → Top 15)
      用 1H K线计算:
      - EMA 排列 (30分): close > EMA20 > EMA50 > EMA200 → 满分
      - ADX 强度 (25分): min(ADX/40, 1.0) × 25
      - 动量 ROC20 (20分): min(|ROC|/0.05, 1.0) × 20
      - 量比 (15分): min(vol_ratio/3.0, 1.0) × 15
      - 流动性 (10分): 成交额+价差综合分

    Level 3: 相关性去重 (15 → 10-15 独立标的)
      - 同方向持仓中，相关性 > 0.75 的只保留评分最高的
      - 用 1H 收盘价的收益率相关系数
      - 每小时更新一次相关性矩阵

    输出: List[TrendScore] 按分数降序
    """

    async def scan(self) -> List[TrendScore]:
        pass

    def _level1_filter(self, all_symbols) -> List[str]:
        pass

    def _level2_score(self, symbol, klines_1h) -> TrendScore:
        pass

    def _level3_deduplicate(self, scored: List[TrendScore]) -> List[TrendScore]:
        pass
```

---

# 10. 模块 06：信号检测引擎

文件: `src/strategy/base.py` + 各策略文件

### 策略基类

```python
class BaseStrategy(ABC):
    """所有策略的统一接口"""

    name: str

    @abstractmethod
    def check_signal(self, symbol: str,
                     klines_1h: List[Kline],
                     klines_15m: List[Kline],
                     direction: Direction,
                     config: dict) -> Optional[Signal]:
        """
        检测入场信号
        返回 Signal 或 None
        """
        pass
```

### 策略一：趋势跟踪

文件: `src/strategy/trend_follow.py`

```
名称: trend_follow
适用状态: strong_trend_up, weak_trend_up, strong_trend_down, weak_trend_down

1H 过滤条件:
  做多: close > EMA20 > EMA50 > EMA200，ADX > 25
  做空: close < EMA20 < EMA50 < EMA200，ADX > 25

15m 入场条件（做多）:
  1. 前一根 K 线低点接近 EMA20_15m（距离 < 0.3%）
  2. 当前 K 线为阳线，收盘 > EMA20_15m
  3. 当前成交量 > 20 根均量 × min_volume_ratio
  4. 上述条件镜像适用于做空

止损: entry - 1.2 × ATR(15m, 14)
止盈: TP1 = entry + 1.5R (平50%), TP2 = entry + 2.5R (平50%)
盈亏比检查: 若 < 1.8 则放弃
置信度: 0.80
```

### 策略二：箱体突破

文件: `src/strategy/pullback_breakout.py`

```
名称: pullback_breakout
适用状态: 同趋势跟踪

1H 过滤: 同趋势跟踪

15m 入场条件（做多）:
  1. 最近 20 根 K 线形成窄幅整理（高低差 < 3%）
  2. 当前 K 线收盘突破 20 根最高点
  3. 成交量 > 均量 × 1.3

止损/止盈: 同趋势跟踪
置信度: 0.75
```

### 策略三：均值回归（Phase 2 启用）

文件: `src/strategy/mean_reversion.py`

```
名称: mean_reversion
适用状态: ranging

前提: ADX_1h < 25

15m 入场条件（做多）:
  1. RSI(14) < 30
  2. 前一根收盘 < 布林带下轨
  3. 当前收盘 > 布林带下轨（回到带内）
  4. 当前为阳线

止损: entry - 0.8 × ATR
止盈: 布林带中轨（全部平仓）
盈亏比检查: 同上
置信度: 0.70
```

### 策略四：波动率突破（Phase 2 启用）

文件: `src/strategy/vol_breakout.py`

```
名称: volatility_breakout
适用状态: ranging, weak_trend_up, weak_trend_down

15m 入场条件:
  1. 布林带宽度处于近 50 根 K 线的最低 15%
  2. 当前收盘突破上轨（做多）或下轨（做空）
  3. 量比 > 2.0

止损/止盈: 同趋势跟踪
置信度: 0.80
```

### 策略五：资金费率套利（Phase 3 启用）

文件: `src/strategy/funding_arb.py`

```
名称: funding_arbitrage
适用状态: extreme_volatile, ranging

入场条件:
  1. |资金费率| > 0.1%
  2. 距下次结算 < 2 小时
  3. 费率 > 0 且 RSI > 55 → 做空收费
  4. 费率 < 0 且 RSI < 45 → 做多收费

止损: 0.5 × ATR（更紧）
止盈: 无价格止盈，结算后平仓
置信度: 0.60
```

---

# 11. 模块 07：订单簿深度分析 [V4]

文件: `src/analysis/orderbook_filter.py`

### 规格

```python
class OrderBookFilter:
    """
    在信号触发后、下单前执行
    
    输入: symbol, direction, entry_price, OrderBook
    输出: OrderBookAnalysis
    
    三维分析:
    1. 买卖墙检测（入场价上下 0.5%）
       做多: 上方卖墙 > 下方买墙 × 3 → 拒绝
       做空: 下方买墙 > 上方卖墙 × 3 → 拒绝
    
    2. 前 10 档买卖失衡
       做多: 买盘占比 < 35% → 拒绝
       做多: 买盘占比 > 60% → 加 10 分
    
    3. 挂单密度分析
       上方 1% 内有密集阻力 → 减 5 分
       无密集阻力 → 加 5 分
    
    score_adjustment 范围: -20 ~ +20
    can_enter = False 时直接放弃该信号
    """

    def analyze(self, symbol: str, direction: Direction,
                entry_price: float, book: OrderBook) -> OrderBookAnalysis:
        pass
```

---

# 12. 模块 08：多交易所价格共识 [V4]

文件: `src/analysis/multi_exchange.py`

### 规格

```python
class MultiExchangeConsensus:
    """
    对比 3 家交易所价格方向一致性
    
    数据获取:
    - 主交易所 (Binance): 已有数据，无额外请求
    - Bybit: REST API 每 30 秒拉一次 Top 15 币 ticker
    - OKX: REST API 每 30 秒拉一次 Top 15 币 ticker
    
    一致性计算:
    - 各所 5 分钟变化率 > +0.03% 算同意做多
    - 各所 5 分钟变化率 < -0.03% 算同意做空
    - consensus = agree_count / total_exchanges
    
    建议:
    - consensus >= 0.9 → "strong" → confidence +0.05
    - consensus >= 0.6 → "normal" → 不调整
    - consensus >= 0.3 → "weak" → confidence -0.10
    - consensus < 0.3 → "reject" → 放弃信号
    
    异常检测:
    - 单所价格偏离均值 > 0.3% → 标记该所异常
    """

    async def update(self, symbols: List[str]):
        """每 30 秒调用"""
        pass

    def check(self, symbol: str, direction: Direction) -> ConsensusResult:
        pass
```

---

# 13. 模块 09：信号聚合与增强检查

文件: `src/strategy/aggregator.py`

### 信号检测 → 增强检查 → 输出的完整管道

```python
class SignalAggregator:
    """
    流程:
    1. 遍历当前 routing 允许的策略
    2. 每个策略对候选币检查信号
    3. 收集所有信号，按置信度排序
    4. 同币种多策略同方向 → 置信度 +0.10
    5. 方向偏好过滤（如趋势市只做多）
    
    增强检查管道（V4）:
    6. 订单簿过滤 → can_enter / score_adj
    7. 多所共识 → confidence 调整
    8. 置信度裁剪到 [0.10, 0.95]
    
    输出: 单个最佳 Signal 或 None
    """

    def scan(self, symbol, klines_1h, klines_15m,
             active_strategies, direction_bias, config) -> Optional[Signal]:
        pass

    def enhanced_check(self, signal, orderbook_filter,
                       multi_exchange) -> Tuple[Optional[Signal], str]:
        """
        返回: (signal_or_none, reason)
        """
        pass
```

---

# 14. 模块 10：仓位计算

文件: `src/risk/position_sizer.py`

### 固定风险法

```python
class PositionSizer:
    """
    核心公式:
      risk_amount = balance × per_trade_risk_pct (默认 0.4% = 8U)
      risk_amount × position_scale (市场状态缩放)
      risk_amount × risk_scale (日内表现缩放)
      risk_amount × streak_mult (连亏缩放)
      risk_amount × 1.15 (多策略确认加成，若有)
      
      notional = risk_amount / stop_distance_pct
      margin = notional / leverage
      quantity = notional / entry_price
    
    上限:
      margin <= balance × 5% (单笔)
      margin >= balance × 1% (否则不做)
    
    连亏缩放:
      连亏 2 笔 → ×0.85
      连亏 3 笔 → ×0.70
      连亏 4+ 笔 → ×0.50
    """

    def calculate(self, balance, signal, routing_config,
                  risk_scale, config) -> Optional[PositionSize]:
        pass
```

---

# 15. 模块 11：智能下单引擎 [V4]

文件: `src/exchange/smart_router.py`

### 三层下单逻辑

```python
class SmartOrderRouter:
    """
    Layer 1 - Maker (等 3 秒):
      做多: 挂在 best_bid + 1 tick，post_only=True
      做空: 挂在 best_ask - 1 tick，post_only=True
      成交 → 省手续费 (0.02% vs 0.04%)
    
    Layer 2 - 追价 (等 3 秒):
      做多: 挂在 best_ask
      做空: 挂在 best_bid
      检查滑点 < 0.05%，超过则放弃
    
    Layer 3 - 市价:
      直接吃单
    
    紧急订单（止损）直接走 Layer 3
    止盈可以走 Layer 1
    
    统计: 记录 maker/chase/market 成交比例和节省金额
    """

    async def execute_entry(self, symbol, side, quantity,
                            urgency='normal') -> Tuple[Optional[dict], FillType]:
        pass

    async def execute_exit(self, symbol, side, quantity,
                           reason='tp') -> Tuple[dict, FillType]:
        pass
```

---

# 16. 模块 12：止盈止损管理

文件: `src/risk/stop_manager.py`

### 三阶段止损 + 分批止盈

```python
class StopManager:
    """
    开仓时:
      止损单: entry ± 1.2 × ATR
      止盈单 TP1: entry ± 1.5R (平 50%)
      止盈单 TP2: entry ± 2.5R (平 50%)
    
    持仓中每轮扫描更新:
      阶段1 (盈利 < 1R): 止损不动
      阶段2 (盈利 >= 1R): 止损移到保本 (entry + 0.1% 手续费缓冲)
      阶段3 (盈利 >= 1.5R): 移动止盈
        trail_stop = entry + (best_price - entry) × 0.60
        做多: new_stop = max(current_stop, trail_stop)
        做空: new_stop = min(current_stop, trail_stop)
    
    TP1 命中后:
      平 50% 仓位
      剩余 50% 继续跟踪
    
    时间止损:
      持仓 > 60 分钟 且 盈利 < 0.3R → 平仓
    """

    def initial_orders(self, signal: Signal) -> dict:
        """返回需要挂的止损和止盈订单参数"""
        pass

    def update(self, position: Position, current_price: float) -> float:
        """返回新的止损价"""
        pass
```

---

# 17. 模块 13：持仓监控

文件: `src/execution/position_monitor.py`

### 职责

```python
class PositionMonitor:
    """
    每 15 秒扫描一次所有持仓:
    
    1. 获取当前价格
    2. 更新 best_price（最优价）
    3. 调用 StopManager.update() 计算新止损
    4. 若止损价变了 → 修改交易所止损单
    5. 检查 TP1 是否命中 → 部分平仓
    6. 检查时间止损 → 是否超时未盈利
    7. 检查趋势评分是否骤降（< 35）→ 主动平仓
    8. 资金费率策略 → 结算后平仓
    
    异常处理:
    - 交易所 API 超时 → 重试 3 次
    - 重试失败 → 发 Telegram 告警，但不自动平仓
    - 价格获取失败 → 跳过该轮，不修改订单
    """

    async def monitor_loop(self):
        pass
```

---

# 18. 模块 14：风控中心

文件: `src/risk/risk_control.py`

### 四层风控

```python
class RiskControl:
    """
    Layer 1 - 单笔风控 (在 PositionSizer 中执行):
      每笔最多亏 0.4% 本金
      盈亏比 >= 1.8
    
    Layer 2 - 日级别:
      日亏损 >= -3% → 停机
      日盈利 >= +8% → 停机
      连亏 >= 5 笔 → 冷却 30 分钟
      单币日亏损 >= -1% → 当天不再交易该币
    
    Layer 3 - 系统级:
      异常检测: 15m 涨跌 > 5% / 流动性枯竭 / 极端资金费率 / BTC 1h 波动 > 3%
      风险等级 0-3, >= 2 不交易
    
    Layer 4 - 账户级:
      总回撤 >= -15% → 停机待审
      周亏损 >= -8% → 仓位减半
    
    接口:
    """

    def pre_trade_check(self, signal: Optional[Signal] = None) -> Tuple[bool, str]:
        """返回 (可否交易, 原因)"""
        pass

    def get_risk_scale(self) -> float:
        """
        返回仓位缩放系数:
        日亏 >= 2% → 0.3
        日亏 >= 1% → 0.5
        日盈 >= 5% → 0.5
        其他 → 1.0
        """
        pass

    def on_trade_close(self, pnl: float, symbol: str):
        """交易结束后更新状态"""
        pass

    def daily_reset(self):
        """UTC 00:00 重置日计数"""
        pass

    def weekly_reset(self):
        """UTC 每周一 00:00 重置周计数"""
        pass
```

---

# 19. 模块 15：分时段参数管理 [V4]

文件: `src/risk/session.py`

### 规格

```python
class SessionManager:
    """
    根据 UTC 时间切换参数:
    
    亚洲盘 (00:00-08:00):
      risk_per_trade: 0.3%, max_positions: 2, min_adx: 28, min_score: 70
    欧洲盘 (08:00-16:00):
      risk_per_trade: 0.4%, max_positions: 3, min_adx: 25, min_score: 65
    美洲盘 (16:00-24:00):
      risk_per_trade: 0.4%, max_positions: 4, min_adx: 25, min_score: 60
    
    接口:
    """

    def get_current_session(self) -> Tuple[SessionName, dict]:
        """返回当前时段名和参数"""
        pass

    def apply_overrides(self, base_config: dict) -> dict:
        """将时段参数覆盖到基础配置，返回新配置"""
        pass
```

---

# 20. 模块 16：自适应参数引擎

文件: `src/adaptive/daily_review.py`

### 规格（Phase 3 启用）

```python
class AdaptiveEngine:
    """
    每日 UTC 00:00 执行
    
    分析近 7 天交易记录，产出调整建议:
    1. 胜率 < 30% → 提高 min_score 到 70, min_volume_ratio 到 1.5
    2. 胜率 > 45% → 放宽 min_score 到 55
    3. 盈亏比 < 1.5 → 加大 tp2_r 到 3.0, 收紧 stop_atr_multiple 到 1.0
    4. 各策略 PnL < 0 且样本 >= 5 → 降低该策略 confidence 0.10
    5. 按小时统计 PnL → 找出最佳 8 小时和最差 4 小时
    
    输出: dict of adjustments
    自动 apply 到 config（仅覆盖允许自动调整的参数）
    """

    def review(self, trade_history: List[TradeRecord],
               config: dict) -> dict:
        pass
```

---

# 21. 模块 17：主控循环

文件: `src/bot/engine.py`

### 完整流程伪代码

```python
class TradingEngine:
    """
    主循环伪代码:
    
    启动:
      1. 加载 config
      2. 初始化所有模块
      3. 启动后台任务:
         - regime_loop (每 5 分钟)
         - pool_loop (每 5 分钟)
         - multi_exchange_loop (每 30 秒) [V4]
         - position_monitor_loop (每 15 秒)
         - daily_review (UTC 00:00)
      4. 进入主循环
    
    主循环 (每 scan_interval_sec):
      1. session_manager.apply_overrides(config) → active_config [V4]
      2. risk_control.pre_trade_check()
         → 不通过: 等 60 秒重试
      3. position_monitor.manage_positions()
      4. routing = STRATEGY_ROUTING[current_regime]
         → Phase 1: 用 DEFAULT_ROUTING
      5. 检查持仓数是否已满
      6. for symbol in active_pool:
           a. 跳过已有持仓的币
           b. anomaly_detector.scan() → risk >= 2 跳过
           c. signal_aggregator.scan() → 无信号跳过
           d. enhanced_check (orderbook + multi_exchange) [V4]
              → 被拦截则跳过
           e. risk_control.pre_trade_check(signal)
           f. position_sizer.calculate()
           g. 总保证金检查 (< 40%)
           h. smart_router.execute_entry() [V4]
           i. 创建 Position, 挂 SL/TP 订单
           j. 发 Telegram 通知
      7. sleep(scan_interval_sec)
    
    异常处理:
      - 任何异常: log.error + sleep 10 秒 + 继续
      - 连续异常 > 10 次: 发 Telegram 告警
      - 交易所连接断开: 自动重连，重连期间不开新仓
    """

    async def run(self):
        pass
```

---

# 22. 模块 18：回测引擎

文件: `src/backtest/engine.py`

### 规格

```python
class BacktestEngine:
    """
    输入:
      - 历史 K 线数据 (1H + 15m, 30-90 天)
      - config.yaml
    
    必须模拟的成本:
      - taker 手续费: 0.04%/侧
      - maker 手续费: 0.02%/侧
      - 滑点: 0.015%
      - 资金费率: 每 8 小时（从交易所获取真实历史费率）
    
    输出报表:
      - 总收益率
      - 最大回撤 (日/周/总)
      - 胜率
      - 盈亏比
      - Profit Factor
      - 夏普比率
      - 日均交易数
      - 最大连亏笔数
      - 手续费总额 / 占利润比
      - 按策略拆分的各项指标
      - 按币种拆分的各项指标
      - 按时段拆分的各项指标
      - 按月份拆分的各项指标
      - 权益曲线 (CSV + 图表)
    
    验收标准:
      - 最大日回撤 < 3%
      - 最大总回撤 < 15%
      - Profit Factor >= 1.3
      - 成本后仍为正收益
    """

    def run(self, data: dict, start: str, end: str) -> dict:
        pass
```

---

# 23. 模块 19：监控与告警

### Telegram 通知规格

```python
class TelegramNotifier:
    """
    通知事件:
    
    [交易] 开仓:
      ✅ 开多 BTC/USDT | 趋势跟踪 | 置信度 85%
      入场: 65,420.5 | 止损: 64,890.2 | 盈亏比: 2.1
      保证金: 95.2U | 风险: 8.0U
    
    [交易] 平仓:
      🟢 平仓 BTC/USDT | TP1 命中 | +12.5U (+0.63%)
      持仓 23 分钟 | Maker 成交
    
    [风控] 告警:
      ⚠️ 日亏损已达 -2.1%，仓位缩至 50%
      ⛔ 日亏损达 -3%，今日停止交易
      🔴 连亏 5 笔，冷却 30 分钟
    
    [日报] 每日 UTC 00:00:
      📊 日报 2026-03-18
      交易: 18 笔 | 胜率: 44%
      净盈亏: +42.5U (+2.1%)
      手续费: -15.8U | Maker 率: 55%
      最大持仓: 3 笔 | 市场状态: 弱势上涨
    
    [系统] 异常:
      🚨 API 连接失败，重试中...
      🚨 连续异常 10 次，请检查系统
    """

    async def notify(self, event_type: str, data: dict):
        pass
```

### Grafana 监控面板

```
推荐监控指标:
  - 实时权益曲线
  - 今日 PnL
  - 当前持仓列表
  - 候选池更新状态
  - API 调用频率和延迟
  - 错误计数
  - Maker/Taker 成交比例
  - 各策略今日 PnL
```

---

# 24. 开发任务拆解与排期

```yaml
# ============================================================
# Phase 1: 基础可运行版（第 1-4 周）
# 目标: 趋势跟踪策略 + 基础风控 + 回测通过
# ============================================================

Week 1 - 基础设施:
  Day 1-2:
    - 项目初始化（结构、依赖、Docker）
    - config.py 配置加载
    - models.py 数据模型
    - logger.py + helpers.py
  Day 3-4:
    - binance_client.py（REST 部分）
    - 所有 REST 接口实现 + 单测
  Day 5:
    - market_data.py（K 线管理 + Redis 缓存）
    - fetch_history.py 脚本（拉取 90 天数据）

Week 2 - 指标 + 筛选:
  Day 1-2:
    - indicators/ 全部指标实现 + 单测
    - 每个指标写对比测试（用 TradingView 数据验证）
  Day 3-4:
    - screener.py 三级筛选
    - correlation.py 相关性计算
    - 输出 Top 15 候选池 + 验证
  Day 5:
    - regime.py 市场状态识别（先写好，Phase 1 不启用）
    - 用历史数据验证状态标记是否合理

Week 3 - 策略 + 风控:
  Day 1-2:
    - trend_follow.py 趋势跟踪策略
    - pullback_breakout.py 箱体突破策略
    - aggregator.py 信号聚合器
    - 单测：用已知行情验证信号触发
  Day 3:
    - position_sizer.py 仓位计算
    - stop_manager.py 止盈止损
  Day 4-5:
    - risk_control.py 四层风控
    - portfolio.py 持仓管理
    - 全链路单测：信号→仓位→风控→通过/拒绝

Week 4 - 回测:
  Day 1-3:
    - backtest/engine.py 回测引擎
    - backtest/simulator.py 模拟执行
    - backtest/reporter.py 报表生成
  Day 4-5:
    - 运行 30-90 天回测
    - 分析报表，调参（但不要过拟合！）
    - 验收标准检查

# ============================================================
# Phase 2: V4 增强 + 实盘准备（第 5-6 周）
# 目标: 加入 4 个免费增强模块 + 模拟盘
# ============================================================

Week 5 - V4 模块:
  Day 1:
    - session.py 分时段参数 [最简单，先做]
  Day 2:
    - orderbook.py 订单簿数据采集
    - orderbook_filter.py 订单簿分析
  Day 3:
    - smart_router.py 智能下单引擎
  Day 4:
    - bybit_client.py + okx_client.py（仅 ticker）
    - multi_exchange.py 多所共识
  Day 5:
    - 回测对比：V4-Lite vs V3
    - 确认增强模块有效

Week 6 - 执行层 + 上线:
  Day 1-2:
    - binance_client.py（WebSocket 部分）
    - order_manager.py 订单生命周期
    - position_monitor.py 持仓监控
  Day 3:
    - engine.py 主控循环
    - telegram.py 通知
  Day 4-5:
    - 模拟盘上线（不动真钱）
    - 跑 3 天验证全链路

# ============================================================
# Phase 3: 实盘迭代（第 7 周+）
# ============================================================

Week 7:
  - 200U 小资金实盘
  - 每天检查日志和报表

Week 8:
  - 开启 regime 市场状态识别
  - 开启 mean_reversion 均值回归策略

Week 9:
  - 开启 volatility_breakout
  - 开启 adaptive 自适应参数

Week 10+:
  - 逐步加仓 500U → 1000U → 2000U
  - Grafana 监控面板
  - 持续迭代
```

---

# 25. 测试清单

```yaml
单元测试:
  indicators:
    - EMA 值与 TradingView 对齐（误差 < 0.01%）
    - ADX 在已知趋势行情中 > 25
    - RSI 在已知超买行情中 > 70
    - ATR 值与手动计算一致

  screener:
    - Level 1 正确过滤低流动性币
    - Level 2 评分在 0-100 范围
    - Level 3 去重后无高相关性同方向币

  strategies:
    - 趋势跟踪在 1H 多头排列 + 15m 回踩时触发
    - 趋势跟踪在 1H 空头排列时不触发做多
    - 止损距离 ≈ 1.2 ATR
    - 盈亏比 < 1.8 时返回 None

  risk:
    - 日亏损 -3% 后 pre_trade_check 返回 False
    - 连亏 5 笔后返回 False
    - 单币日亏损 -1% 后该币被拒绝
    - 总保证金 > 40% 时拒绝新仓

  orderbook [V4]:
    - 卖墙 > 3x 买墙时 can_enter = False
    - 买卖均衡时 score_adjustment ≈ 0

  smart_order [V4]:
    - post_only 订单不会变成 taker
    - 超时后正确升级到下一层
    - 止损单直接走市价

集成测试:
  - 完整扫描周期：数据获取 → 筛选 → 信号 → 下单 → 持仓 → 平仓
  - 风控触发后所有新信号被拒绝
  - 分时段切换时参数正确变化
  - WebSocket 断线后自动重连

回测验证:
  - 手续费正确扣除
  - 滑点正确模拟
  - 资金费率正确计算
  - 止损单在触价时正确触发
  - 分批止盈数量正确
  - 日止损线正确停机
  - 权益曲线与交易记录一致

压力测试:
  - 150 币同时扫描不超时（< 5 秒）
  - API 限频不触发 429
  - Redis 缓存命中率 > 80%
  - 内存使用 < 2GB
```

---

# 依赖清单

文件: `requirements.txt`

```
# 交易所
ccxt>=4.0.0
websockets>=12.0
python-binance>=1.0.0

# 数据处理
numpy>=1.24.0
pandas>=2.0.0

# 数据库
asyncpg>=0.28.0
redis>=5.0.0
sqlalchemy>=2.0.0

# 配置
pyyaml>=6.0
python-dotenv>=1.0.0

# 通知
python-telegram-bot>=20.0

# 回测
matplotlib>=3.7.0
tabulate>=0.9.0

# 工具
aiohttp>=3.9.0
loguru>=0.7.0
uvloop>=0.19.0  # Linux only, 提升异步性能

# 测试
pytest>=7.0.0
pytest-asyncio>=0.21.0
```

---

*开发规格书 V4-Lite | 可直接开发 | 2026-03-17*
