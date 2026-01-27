class RiskManager:
    """风控管理模块"""
    
    def __init__(self, max_position_pct=0.1, max_loss_pct=0.02, max_daily_trades=10):
        """
        初始化风控参数
        :param max_position_pct: 单次交易最大仓位占比（默认10%）
        :param max_loss_pct: 单次交易最大亏损占比（默认2%）
        :param max_daily_trades: 每日最大交易次数
        """
        self.max_position_pct = max_position_pct
        self.max_loss_pct = max_loss_pct
        self.max_daily_trades = max_daily_trades
        self.daily_trades = 0
        self.daily_pnl = 0
        self.positions = {}
    
    def calculate_position_size(self, balance, price, atr=None):
        """
        计算仓位大小
        :param balance: 账户余额
        :param price: 当前价格
        :param atr: ATR值（可选，用于动态仓位调整）
        :return: 建议交易数量
        """
        max_amount = balance * self.max_position_pct / price
        
        # 如果有ATR，根据波动率调整仓位
        if atr is not None and atr > 0:
            volatility_factor = min(1, 0.02 * price / atr)  # 波动越大，仓位越小
            max_amount *= volatility_factor
        
        return max_amount
    
    def calculate_stop_loss(self, entry_price, side, atr=None, risk_ratio=1.5):
        """
        计算止损价格
        :param entry_price: 入场价格
        :param side: 交易方向 ('buy' or 'sell')
        :param atr: ATR值
        :param risk_ratio: ATR倍数
        :return: 止损价格
        """
        if atr is None:
            # 默认使用价格的2%作为止损
            stop_distance = entry_price * self.max_loss_pct
        else:
            stop_distance = atr * risk_ratio
        
        if side == 'buy':
            return entry_price - stop_distance
        else:
            return entry_price + stop_distance
    
    def calculate_take_profit(self, entry_price, stop_loss, side, rr_ratio=2):
        """
        计算止盈价格（基于风险回报比）
        :param entry_price: 入场价格
        :param stop_loss: 止损价格
        :param side: 交易方向
        :param rr_ratio: 风险回报比
        :return: 止盈价格
        """
        risk = abs(entry_price - stop_loss)
        reward = risk * rr_ratio
        
        if side == 'buy':
            return entry_price + reward
        else:
            return entry_price - reward
    
    def can_trade(self):
        """检查是否可以继续交易"""
        if self.daily_trades >= self.max_daily_trades:
            return False, "已达到每日最大交易次数"
        return True, "可以交易"
    
    def check_drawdown(self, current_balance, initial_balance, max_drawdown=0.1):
        """
        检查最大回撤
        :param current_balance: 当前余额
        :param initial_balance: 初始余额
        :param max_drawdown: 最大允许回撤
        :return: (是否触发回撤保护, 当前回撤比例)
        """
        drawdown = (initial_balance - current_balance) / initial_balance
        if drawdown >= max_drawdown:
            return True, drawdown
        return False, drawdown
    
    def update_trade(self, pnl):
        """更新交易记录"""
        self.daily_trades += 1
        self.daily_pnl += pnl
    
    def reset_daily(self):
        """重置每日统计"""
        self.daily_trades = 0
        self.daily_pnl = 0
    
    def get_trailing_stop(self, entry_price, current_price, side, trail_pct=0.02):
        """
        计算移动止损
        :param entry_price: 入场价格
        :param current_price: 当前价格
        :param side: 交易方向
        :param trail_pct: 移动止损百分比
        :return: 移动止损价格
        """
        if side == 'buy':
            # 多单：止损跟随价格上涨
            trail_stop = current_price * (1 - trail_pct)
            return max(entry_price * (1 - self.max_loss_pct), trail_stop)
        else:
            # 空单：止损跟随价格下跌
            trail_stop = current_price * (1 + trail_pct)
            return min(entry_price * (1 + self.max_loss_pct), trail_stop)


# 测试代码
if __name__ == "__main__":
    rm = RiskManager(max_position_pct=0.1, max_loss_pct=0.02)
    
    # 示例：计算仓位和止损
    balance = 10000  # USDT
    price = 40000    # BTC价格
    atr = 500        # ATR值
    
    position_size = rm.calculate_position_size(balance, price, atr)
    stop_loss = rm.calculate_stop_loss(price, 'buy', atr)
    take_profit = rm.calculate_take_profit(price, stop_loss, 'buy', rr_ratio=2)
    
    print(f"建议仓位: {position_size:.6f} BTC")
    print(f"止损价格: {stop_loss:.2f}")
    print(f"止盈价格: {take_profit:.2f}")
