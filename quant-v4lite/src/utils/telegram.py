import logging
import aiohttp

logger = logging.getLogger(__name__)


class TelegramNotifier:
    """Telegram 通知"""

    def __init__(self, config: dict):
        alert_cfg = config.get('alerts', {}).get('telegram', {})
        self._enabled = alert_cfg.get('enabled', False)
        self._token = alert_cfg.get('bot_token', '')
        self._chat_id = alert_cfg.get('chat_id', '')
        self._notify_on = alert_cfg.get('notify_on', [])

    async def notify(self, event_type: str, message: str):
        """发送通知"""
        if not self._enabled or not self._token or not self._chat_id:
            return

        if event_type not in self._notify_on and 'all' not in self._notify_on:
            return

        url = f"https://api.telegram.org/bot{self._token}/sendMessage"
        payload = {
            'chat_id': self._chat_id,
            'text': message,
            'parse_mode': 'HTML',
        }

        try:
            import ssl
            ssl_ctx = ssl.create_default_context()
            ssl_ctx.check_hostname = False
            ssl_ctx.verify_mode = ssl.CERT_NONE
            connector = aiohttp.TCPConnector(ssl=ssl_ctx)
            async with aiohttp.ClientSession(connector=connector) as session:
                async with session.post(url, json=payload, timeout=aiohttp.ClientTimeout(total=10)) as resp:
                    if resp.status != 200:
                        logger.warning(f"Telegram send failed: {resp.status}")
        except Exception as e:
            logger.warning(f"Telegram error: {e}")

    async def trade_open(self, data: dict):
        direction = "开多" if data['direction'] == 'LONG' else "开空"
        msg = (f"✅ {direction} {data['symbol']} | {data['strategy']} | "
               f"置信度 {data['confidence']*100:.0f}%\n"
               f"入场: {data['entry']:.4f} | 止损: {data['stop']:.4f} | "
               f"盈亏比: {data.get('rr', 0):.1f}\n"
               f"保证金: {data['margin']:.2f}U | 风险: {data['risk']:.2f}U")
        await self.notify('trade_open', msg)

    async def trade_close(self, data: dict):
        emoji = "🟢" if data['pnl'] > 0 else "🔴"
        msg = (f"{emoji} 平仓 {data['symbol']} | {data['reason']} | "
               f"{data['pnl']:+.2f}U ({data['pnl_pct']:+.2f}%)\n"
               f"持仓 {data['hold_min']:.0f} 分钟 | {data['fill_type']}")
        await self.notify('trade_close', msg)

    async def risk_alert(self, message: str):
        await self.notify('risk_alert', f"⚠️ {message}")

    async def error(self, message: str):
        await self.notify('error', f"🚨 {message}")

    async def daily_report(self, data: dict):
        msg = (f"📊 日报 {data['date']}\n"
               f"交易: {data['trades']} 笔 | 胜率: {data['win_rate']*100:.0f}%\n"
               f"净盈亏: {data['pnl']:+.2f}U ({data['pnl_pct']:+.2f}%)\n"
               f"手续费: {data['fee']:.2f}U | Maker 率: {data.get('maker_rate', 0)*100:.0f}%")
        await self.notify('daily_report', msg)
