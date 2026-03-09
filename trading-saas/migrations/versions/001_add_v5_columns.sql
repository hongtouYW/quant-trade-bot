-- V5 Strategy Migration: Add columns for 10x leverage, partial TP, ATR stops
-- Run: mysql -u saas_user -pSaasTrade2026xK9m trading_saas < this_file.sql

-- agent_trading_config: v5 strategy parameters
ALTER TABLE agent_trading_config
    ADD COLUMN tp1_roi DECIMAL(5,2) DEFAULT 10.00 AFTER short_bias,
    ADD COLUMN tp1_close_ratio DECIMAL(3,2) DEFAULT 0.50 AFTER tp1_roi,
    ADD COLUMN tp2_roi DECIMAL(5,2) DEFAULT 20.00 AFTER tp1_close_ratio,
    ADD COLUMN use_atr_stop TINYINT(1) DEFAULT 0 AFTER tp2_roi,
    ADD COLUMN atr_stop_multiplier DECIMAL(3,1) DEFAULT 1.5 AFTER use_atr_stop,
    ADD COLUMN adx_min_threshold INT DEFAULT 25 AFTER atr_stop_multiplier;

-- trades: partial take-profit tracking
ALTER TABLE trades
    ADD COLUMN tp1_hit TINYINT(1) DEFAULT 0 AFTER strategy_version,
    ADD COLUMN tp1_price DECIMAL(20,8) NULL AFTER tp1_hit,
    ADD COLUMN tp1_time DATETIME NULL AFTER tp1_price,
    ADD COLUMN partial_pnl DECIMAL(15,4) DEFAULT 0 AFTER tp1_time,
    ADD COLUMN original_amount DECIMAL(15,2) NULL AFTER partial_pnl;
