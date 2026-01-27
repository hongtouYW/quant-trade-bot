import requests
import json

class TelegramNotify:
    """Telegram消息推送模块"""
    
    def __init__(self, bot_token, chat_id):
        self.bot_token = bot_token
        self.chat_id = chat_id
        self.base_url = f"https://api.telegram.org/bot{bot_token}"
    
    def send_message(self, message):
        """发送文本消息"""
        url = f"{self.base_url}/sendMessage"
        payload = {
            "chat_id": self.chat_id,
            "text": message,
            "parse_mode": "HTML"
        }
        try:
            response = requests.post(url, json=payload, timeout=10)
            return response.json()
        except Exception as e:
            print(f"Telegram发送失败: {e}")
            return None
    
    def send_buy_alert(self, symbol, price, reason=""):
        """发送买入报警"""
        message = f"""
🟢 <b>买入信号</b>
━━━━━━━━━━━━━━
📈 交易对: {symbol}
💰 当前价格: {price}
📝 触发原因: {reason}
⏰ 时间: {self._get_time()}
"""
        return self.send_message(message)
    
    def send_sell_alert(self, symbol, price, reason=""):
        """发送卖出报警"""
        message = f"""
🔴 <b>卖出信号</b>
━━━━━━━━━━━━━━
📉 交易对: {symbol}
💰 当前价格: {price}
📝 触发原因: {reason}
⏰ 时间: {self._get_time()}
"""
        return self.send_message(message)
    
    def send_indicator_alert(self, symbol, indicator_name, value, threshold, direction):
        """发送指标报警"""
        emoji = "⬆️" if direction == "up" else "⬇️"
        message = f"""
📊 <b>指标报警</b>
━━━━━━━━━━━━━━
📈 交易对: {symbol}
🔢 指标: {indicator_name}
{emoji} 当前值: {value:.4f}
🎯 阈值: {threshold}
⏰ 时间: {self._get_time()}
"""
        return self.send_message(message)
    
    def send_order_result(self, symbol, side, amount, price, status):
        """发送下单结果通知"""
        emoji = "🟢" if side == "buy" else "🔴"
        status_emoji = "✅" if status == "success" else "❌"
        message = f"""
{emoji} <b>订单执行结果</b>
━━━━━━━━━━━━━━
📈 交易对: {symbol}
📋 方向: {side.upper()}
📦 数量: {amount}
💰 价格: {price}
{status_emoji} 状态: {status}
⏰ 时间: {self._get_time()}
"""
        return self.send_message(message)
    
    def send_daily_report(self, total_trades, win_rate, pnl, balance):
        """发送每日报告"""
        pnl_emoji = "📈" if pnl >= 0 else "📉"
        message = f"""
📊 <b>每日交易报告</b>
━━━━━━━━━━━━━━
🔢 总交易次数: {total_trades}
🎯 胜率: {win_rate:.2%}
{pnl_emoji} 盈亏: {pnl:.4f} USDT
💰 当前余额: {balance:.4f} USDT
⏰ 时间: {self._get_time()}
"""
        return self.send_message(message)
    
    def _get_time(self):
        from datetime import datetime
        return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


# 测试代码
if __name__ == "__main__":
    with open('../config.json', 'r') as f:
        config = json.load(f)
    
    tg = TelegramNotify(config['telegram']['bot_token'], config['telegram']['chat_id'])
    tg.send_message("✅ Telegram推送测试成功！")
