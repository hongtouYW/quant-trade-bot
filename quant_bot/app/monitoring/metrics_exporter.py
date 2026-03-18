"""Prometheus metrics exporter (Spec §20: metrics_exporter.py)"""
import time
import logging
from flask import Response

log = logging.getLogger(__name__)


def _format_metric(name, value, help_text='', metric_type='gauge', labels=None):
    """格式化单个Prometheus指标"""
    lines = []
    if help_text:
        lines.append(f'# HELP {name} {help_text}')
    lines.append(f'# TYPE {name} {metric_type}')
    if labels:
        label_str = ','.join(f'{k}="{v}"' for k, v in labels.items())
        lines.append(f'{name}{{{label_str}}} {value}')
    else:
        lines.append(f'{name} {value}')
    return '\n'.join(lines)


def generate_metrics(bot):
    """从QuantBot实例生成Prometheus格式指标"""
    if bot is None:
        return '# QuantBot not initialized\n'

    lines = []
    try:
        status = bot.get_status()

        # === 系统指标 ===
        lines.append(_format_metric(
            'quantbot_running', 1 if status.get('running') else 0,
            'Whether the bot is running'))
        lines.append(_format_metric(
            'quantbot_cycle_count', status.get('cycle_count', 0),
            'Total main loop cycles', 'counter'))

        # === 账户指标 ===
        lines.append(_format_metric(
            'quantbot_equity_usdt', status.get('equity', 0),
            'Account equity in USDT'))
        lines.append(_format_metric(
            'quantbot_available_usdt', status.get('available', 0),
            'Available balance in USDT'))

        # === 持仓指标 ===
        positions = status.get('positions', [])
        lines.append(_format_metric(
            'quantbot_positions_count', len(positions),
            'Number of open positions'))

        total_margin = sum(p.get('margin', 0) for p in positions)
        total_notional = sum(p.get('notional', 0) for p in positions)
        total_unrealized = sum(p.get('pnl', 0) for p in positions)
        lines.append(_format_metric(
            'quantbot_total_margin_usdt', round(total_margin, 2),
            'Total margin used'))
        lines.append(_format_metric(
            'quantbot_total_notional_usdt', round(total_notional, 2),
            'Total notional value'))
        lines.append(_format_metric(
            'quantbot_unrealized_pnl_usdt', round(total_unrealized, 2),
            'Total unrealized PnL'))

        # 每个持仓的指标
        for p in positions:
            labels = {'symbol': p.get('symbol', ''), 'direction': 'long' if p.get('direction') == 1 else 'short'}
            lines.append(_format_metric(
                'quantbot_position_pnl_usdt', round(p.get('pnl', 0), 4),
                'Position unrealized PnL', labels=labels))
            lines.append(_format_metric(
                'quantbot_position_margin_usdt', round(p.get('margin', 0), 2),
                'Position margin', labels=labels))

        # === 风控指标 ===
        risk = status.get('risk', {})
        lines.append(_format_metric(
            'quantbot_daily_pnl_usdt', risk.get('daily_pnl', 0),
            'Daily realized PnL'))
        lines.append(_format_metric(
            'quantbot_daily_pnl_pct', risk.get('daily_pnl_pct', 0),
            'Daily PnL percentage'))
        lines.append(_format_metric(
            'quantbot_consecutive_losses', risk.get('consecutive_losses', 0),
            'Current consecutive loss streak'))
        lines.append(_format_metric(
            'quantbot_today_trades', risk.get('today_trades', 0),
            'Trades executed today', 'counter'))
        lines.append(_format_metric(
            'quantbot_today_wins', risk.get('today_wins', 0),
            'Winning trades today', 'counter'))
        lines.append(_format_metric(
            'quantbot_today_losses', risk.get('today_losses', 0),
            'Losing trades today', 'counter'))

        # === 候选池指标 ===
        lines.append(_format_metric(
            'quantbot_active_pool_size', status.get('active_pool_size', 0),
            'Number of symbols in active pool'))

        pool = status.get('active_pool', [])
        for item in pool[:12]:
            labels = {'symbol': item.get('symbol', ''), 'grade': item.get('grade', '?')}
            lines.append(_format_metric(
                'quantbot_pool_score', item.get('score', 0),
                'Symbol trend score in pool', labels=labels))

        # === WebSocket指标 ===
        ws = status.get('websocket', {})
        lines.append(_format_metric(
            'quantbot_websocket_connected', 1 if ws.get('connected') else 0,
            'WebSocket connection status'))
        lines.append(_format_metric(
            'quantbot_websocket_streams', ws.get('symbols', 0),
            'Number of WebSocket streams'))

        # === 冷却指标 ===
        cooldowns = status.get('cooldowns', {})
        active_cooldowns = sum(1 for v in cooldowns.values() if isinstance(v, dict) and v.get('active'))
        lines.append(_format_metric(
            'quantbot_active_cooldowns', active_cooldowns if isinstance(active_cooldowns, int) else len(cooldowns),
            'Number of active cooldowns'))

        # === 元信息 ===
        lines.append(_format_metric(
            'quantbot_scrape_timestamp', time.time(),
            'Timestamp of metrics scrape', 'counter'))

    except Exception as e:
        log.error(f"Metrics生成异常: {e}")
        lines.append(f'# Error generating metrics: {e}')

    return '\n'.join(lines) + '\n'


def register_metrics_endpoint(flask_app):
    """在Flask应用上注册 /metrics 端点"""

    @flask_app.route('/metrics')
    def metrics():
        from app.main import get_bot
        bot = get_bot()
        content = generate_metrics(bot)
        return Response(content, mimetype='text/plain; version=0.0.4; charset=utf-8')

    log.info("Prometheus /metrics 端点已注册")
