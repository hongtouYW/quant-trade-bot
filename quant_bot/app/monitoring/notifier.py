"""Telegram notification service"""
import logging
import requests
from app.config import get

log = logging.getLogger(__name__)

TELEGRAM_BOT_TOKEN = None  # Set via env or config
TELEGRAM_CHAT_ID = None


def init_telegram(bot_token, chat_id=None):
    global TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID
    TELEGRAM_BOT_TOKEN = bot_token
    TELEGRAM_CHAT_ID = chat_id or get('monitoring', 'telegram_chat_id', '')


def send_telegram(message, chat_id=None):
    """Send a message via Telegram bot"""
    token = TELEGRAM_BOT_TOKEN
    cid = chat_id or TELEGRAM_CHAT_ID
    if not token or not cid:
        log.debug(f"Telegram not configured, skipping: {message[:50]}")
        return False

    try:
        url = f"https://api.telegram.org/bot{token}/sendMessage"
        resp = requests.post(url, json={
            'chat_id': cid,
            'text': message,
            'parse_mode': 'HTML',
        }, timeout=10)
        if resp.status_code != 200:
            log.warning(f"Telegram send failed: {resp.text}")
            return False
        return True
    except Exception as e:
        log.error(f"Telegram error: {e}")
        return False


def notify_trade_open(order_plan):
    dir_str = "🟢 LONG" if order_plan['direction'] == 1 else "🔴 SHORT"
    msg = (
        f"<b>📈 开仓</b>\n"
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
    dir_str = "LONG" if trade_record.direction == 1 else "SHORT"
    msg = (
        f"<b>{emoji} 平仓</b>\n"
        f"{dir_str} {trade_record.symbol}\n"
        f"原因: {trade_record.close_reason}\n"
        f"入场: {trade_record.entry_price:.4f}\n"
        f"出场: {trade_record.exit_price:.4f}\n"
        f"PnL: {trade_record.pnl:+.2f}U ({trade_record.pnl_pct:+.2%})"
    )
    send_telegram(msg)


def notify_risk_event(event_type, details=""):
    msg = f"<b>⚠️ 风控</b>\n{event_type}\n{details}"
    send_telegram(msg)


def notify_heartbeat(status):
    msg = (
        f"<b>💓 心跳</b>\n"
        f"持仓: {status.get('positions', 0)}\n"
        f"日PnL: {status.get('daily_pnl', 0):+.2f}U ({status.get('daily_pnl_pct', 0):+.2f}%)\n"
        f"今日交易: {status.get('trades', 0)} 胜{status.get('wins', 0)} 负{status.get('losses', 0)}\n"
        f"活跃池: {status.get('pool_size', 0)}币"
    )
    send_telegram(msg)
