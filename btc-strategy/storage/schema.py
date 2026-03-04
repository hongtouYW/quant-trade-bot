KLINE_SCHEMA = {
    "columns": [
        ("timestamp", "datetime64[ms]"),
        ("open", "float64"),
        ("high", "float64"),
        ("low", "float64"),
        ("close", "float64"),
        ("volume", "float64"),
        ("quote_volume", "float64"),
        ("trade_count", "int64"),
        ("taker_buy_volume", "float64"),
        ("taker_buy_quote_volume", "float64"),
        ("close_time", "datetime64[ms]"),
    ],
    "primary_key": ["timestamp"],
    "sort_by": ["timestamp"],
}

FUNDING_RATE_SCHEMA = {
    "columns": [
        ("timestamp", "datetime64[ms]"),
        ("symbol", "str"),
        ("funding_rate", "float64"),
        ("mark_price", "float64"),
    ],
    "primary_key": ["timestamp"],
    "sort_by": ["timestamp"],
}

OPEN_INTEREST_SCHEMA = {
    "columns": [
        ("timestamp", "datetime64[ms]"),
        ("symbol", "str"),
        ("sum_open_interest", "float64"),
        ("sum_open_interest_value", "float64"),
    ],
    "primary_key": ["timestamp"],
    "sort_by": ["timestamp"],
}

AGG_TRADES_SCHEMA = {
    "columns": [
        ("agg_trade_id", "int64"),
        ("timestamp", "datetime64[ms]"),
        ("price", "float64"),
        ("quantity", "float64"),
        ("first_trade_id", "int64"),
        ("last_trade_id", "int64"),
        ("is_buyer_maker", "bool"),
        ("trade_value_usd", "float64"),
    ],
    "primary_key": ["agg_trade_id"],
    "sort_by": ["timestamp"],
}

TRADE_FLOW_SCHEMA = {
    "columns": [
        ("timestamp", "datetime64[ms]"),
        ("buy_volume", "float64"),
        ("sell_volume", "float64"),
        ("buy_value_usd", "float64"),
        ("sell_value_usd", "float64"),
        ("net_flow_usd", "float64"),
        ("large_buy_count", "int64"),
        ("large_sell_count", "int64"),
        ("large_buy_value_usd", "float64"),
        ("large_sell_value_usd", "float64"),
        ("trade_count", "int64"),
    ],
    "primary_key": ["timestamp"],
    "sort_by": ["timestamp"],
}

LIQUIDATION_SCHEMA = {
    "columns": [
        ("timestamp", "datetime64[ms]"),
        ("symbol", "str"),
        ("side", "str"),
        ("order_type", "str"),
        ("original_quantity", "float64"),
        ("price", "float64"),
        ("average_price", "float64"),
        ("order_status", "str"),
        ("filled_accumulated_qty", "float64"),
        ("trade_value_usd", "float64"),
    ],
    "primary_key": ["timestamp", "price"],
    "sort_by": ["timestamp"],
}

WHALE_TRACKING_SCHEMA = {
    "columns": [
        ("timestamp", "datetime64[ms]"),
        ("source", "str"),
        ("event_type", "str"),
        ("side", "str"),
        ("value_usd", "float64"),
        ("quantity", "float64"),
        ("price", "float64"),
        ("details", "str"),
    ],
    "primary_key": ["timestamp", "source", "value_usd"],
    "sort_by": ["timestamp"],
}

SCHEMAS = {
    "klines": KLINE_SCHEMA,
    "funding_rate": FUNDING_RATE_SCHEMA,
    "open_interest": OPEN_INTEREST_SCHEMA,
    "agg_trades": AGG_TRADES_SCHEMA,
    "trade_flow": TRADE_FLOW_SCHEMA,
    "liquidations": LIQUIDATION_SCHEMA,
    "whale_tracking": WHALE_TRACKING_SCHEMA,
}
