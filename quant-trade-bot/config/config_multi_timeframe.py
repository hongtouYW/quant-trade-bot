# -*- coding: utf-8 -*-
"""
多时间框架交易配置
支持日线趋势 + 15分钟入场的组合策略
"""

# 时间框架配置
TIMEFRAMES = {
    "trend_analysis": "1d",      # 趋势分析：日线
    "entry_signals": "15m",      # 入场信号：15分钟
    "risk_management": "5m"      # 风控监控：5分钟
}

# 策略参数
STRATEGY_CONFIG = {
    "multi_timeframe": {
        "enabled": True,
        "trend_strength_threshold": 0.5,      # 方案B：降低趋势强度阈值，增加机会
        "entry_confidence_threshold": 0.4,    # 方案B：降低入场信心阈值
        "max_positions": 8,                   # 方案B增强：最大持仓数提高到8（15币种监控）
        "position_sizing": "confidence"       # 按信心度分配仓位
    },
    
    # 日线趋势参数
    "daily_trend": {
        "ma_short": 20,
        "ma_long": 50,
        "rsi_period": 14,
        "rsi_oversold": 30,
        "rsi_overbought": 70,
        "macd_fast": 12,
        "macd_slow": 26,
        "macd_signal": 9
    },
    
    # 15分钟入场参数
    "entry_15m": {
        "ema_short": 12,
        "ema_long": 26,
        "bb_period": 20,
        "bb_std": 2,
        "rsi_period": 14,
        "support_resistance_period": 10
    }
}

# 交易品种配置
TRADING_PAIRS = {
    "major": ["BTC/USDT", "ETH/USDT"],
    "altcoins": ["SOL/USDT", "ADA/USDT", "DOT/USDT"],
    "defi": ["UNI/USDT", "LINK/USDT", "AAVE/USDT"],
    # 方案B增强版：15个币种全方位监控
    "active_pairs": [
        # 主流币 (4个)
        "BTC/USDT", "ETH/USDT", "BNB/USDT", "SOL/USDT",
        # Layer1 公链 (3个)
        "AVAX/USDT", "DOT/USDT", "NEAR/USDT",
        # DeFi (3个)
        "LINK/USDT", "UNI/USDT", "AAVE/USDT",
        # 热门Altcoins (5个)
        "ADA/USDT", "DOGE/USDT", "MATIC/USDT", "ARB/USDT", "OP/USDT"
    ]
}

# 风险管理
RISK_MANAGEMENT = {
    "max_risk_per_trade": 0.02,    # 单笔最大风险 2%
    "max_portfolio_risk": 0.06,    # 组合最大风险 6%  
    "stop_loss": {
        "method": "percentage",     # 方案B：固定百分比止损
        "percentage": 0.02,         # 止损 -2%
        "multiplier": 2.0,          # ATR倍数（备用）
        "max_sl": 0.02             # 最大止损 2%
    },
    "take_profit": {
        "method": "percentage",     # 方案B：固定百分比止盈
        "percentage": 0.04,         # 止盈 +4%
        "ratio": [2.0],            # 风险收益比 1:2
        "trail_percent": 0.01       # 跟踪止盈1%
    },
    "max_holding_hours": 24         # 方案B：最长持仓24小时
}

# 杠杆配置
LEVERAGE_CONFIG = {
    "max_leverage": 3,
    "confidence_based": {
        "high_confidence": 3,       # 高信心度 3倍杠杆
        "medium_confidence": 2,     # 中等信心度 2倍杠杆
        "low_confidence": 1         # 低信心度 1倍杠杆
    },
    "pair_specific": {
        "BTC/USDT": 3,
        "ETH/USDT": 3,
        "others": 2
    }
}

# 时间控制
TIME_CONTROLS = {
    "analysis_interval": 300,      # 方案B：5分钟分析一次
    "trend_check_interval": 1800,  # 30分钟检查趋势
    "risk_check_interval": 60,     # 1分钟检查风险（止损监控）
    "max_holding_time": 86400,     # 24小时（秒）
    "trading_hours": {
        "enabled": False,           # 是否限制交易时间
        "start": "09:00",          # 开始时间
        "end": "21:00"             # 结束时间
    }
}

# Telegram通知配置
NOTIFICATION_CONFIG = {
    "trend_change": True,          # 趋势变化通知
    "entry_signals": True,         # 入场信号通知
    "position_updates": True,      # 持仓更新通知
    "risk_alerts": True,          # 风险警告通知
    "daily_summary": True         # 日度总结通知
}