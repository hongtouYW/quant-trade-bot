"""Telegram 通知服务"""
import logging
import requests
from app.config import get

log = logging.getLogger(__name__)

TELEGRAM_BOT_TOKEN = None
TELEGRAM_CHAT_ID = None


def init_telegram(bot_token, chat_id=None):
    global TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID
    TELEGRAM_BOT_TOKEN = bot_token
    TELEGRAM_CHAT_ID = chat_id or get('monitoring', 'telegram_chat_id', '')


def send_telegram(message, chat_id=None):
    token = TELEGRAM_BOT_TOKEN
    cid = chat_id or TELEGRAM_CHAT_ID
    if not token or not cid:
        log.debug(f"Telegram未配置: {message[:50]}")
        return False
    try:
        url = f"https://api.telegram.org/bot{token}/sendMessage"
        resp = requests.post(url, json={
            'chat_id': cid,
            'text': message,
            'parse_mode': 'HTML',
        }, timeout=10)
        if resp.status_code != 200:
            log.warning(f"Telegram发送失败: {resp.text}")
            return False
        return True
    except Exception as e:
        log.error(f"Telegram错误: {e}")
        return False


def notify_trade_open(order_plan):
    dir_str = "🟢 做多" if order_plan['direction'] == 1 else "🔴 做空"
    msg = (
        f"<b>📈 开仓信号</b>\n"
        f"{dir_str} {order_plan['symbol']}\n"
        f"策略: {order_plan['setup_type']}\n"
        f"入场: {order_plan['entry']:.4f}\n"
        f"止损: {order_plan['stop']:.4f}\n"
        f"TP1: {order_plan['tp1']:.4f}\n"
        f"TP2: {order_plan['tp2']:.4f}\n"
        f"仓位: {order_plan['size']:.4f}\n"
        f"保证金: {order_plan['margin']:.2f}U\n"
        f"评分: {order_plan['score']:.1f}"
    )
    send_telegram(msg)


def notify_trade_close(trade_record):
    emoji = "✅" if trade_record.pnl >= 0 else "❌"
    dir_str = "做多" if trade_record.direction == 1 else "做空"
    # 平仓原因中文映射
    reason_map = {
        'stop_loss': '止损',
        'tp1': '止盈TP1(50%)',
        'tp2': '止盈TP2(100%)',
        'time_stop': '超时止损',
        '趋势评分下降': '趋势评分下降',
        '1H方向失效': '1H方向结构失效',
        '反向信号': '反向强信号',
        '突破失败': '突破回落',
    }
    reason = reason_map.get(trade_record.close_reason, trade_record.close_reason)
    msg = (
        f"<b>{emoji} 平仓</b>\n"
        f"{dir_str} {trade_record.symbol}\n"
        f"原因: {reason}\n"
        f"策略: {trade_record.setup_type}\n"
        f"入场: {trade_record.entry_price:.4f}\n"
        f"出场: {trade_record.exit_price:.4f}\n"
        f"毛利: {trade_record.pnl:+.2f}U ({trade_record.pnl_pct:+.2%})\n"
        f"手续费: {trade_record.fees:.4f}U\n"
        f"资金费: {trade_record.funding_fees:+.4f}U\n"
        f"净盈亏: {trade_record.net_pnl:+.2f}U"
    )
    send_telegram(msg)


def notify_risk_event(event_type, details=""):
    msg = f"<b>⚠️ 风控警报</b>\n类型: {event_type}\n{details}"
    send_telegram(msg)


def notify_heartbeat(status):
    msg = (
        f"<b>💓 系统心跳</b>\n"
        f"持仓: {status.get('positions', 0)}个\n"
        f"日PnL: {status.get('daily_pnl', 0):+.2f}U ({status.get('daily_pnl_pct', 0):+.2f}%)\n"
        f"今日交易: {status.get('trades', 0)}笔 (胜{status.get('wins', 0)} 负{status.get('losses', 0)})\n"
        f"活跃池: {status.get('pool_size', 0)}个币种"
    )
    send_telegram(msg)


def notify_data_error(details):
    msg = f"<b>🔴 数据异常</b>\n{details}"
    send_telegram(msg)


def notify_cooldown(symbol, minutes, reason):
    msg = f"<b>⏸️ 冷却触发</b>\n{symbol} 冷却 {minutes}分钟\n原因: {reason}"
    send_telegram(msg)


def notify_profit_guard(pnl_pct):
    pass  # 不发盈利保护通知


def notify_stop_failed(symbol, stop_price):
    msg = f"<b>🚨 止损挂单失败</b>\n{symbol} @ {stop_price:.4f}\n请手动检查!"
    send_telegram(msg)
