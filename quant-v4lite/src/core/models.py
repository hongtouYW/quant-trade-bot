from dataclasses import dataclass, field
from datetime import datetime
from typing import Optional, List, Dict, Tuple

from .enums import Direction, SignalStrategy, FillType, MarketRegime, SessionName


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
    features: Optional[Dict] = None
    timestamp: datetime = field(default_factory=datetime.utcnow)

    @property
    def r_value(self) -> float:
        return abs(self.entry_price - self.stop_loss)


# ============================================================
# 持仓
# ============================================================

@dataclass
class Position:
    id: str
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
    close_reason: str             # "tp1" / "tp2" / "trailing" / "stop_loss" / "time_stop"
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
