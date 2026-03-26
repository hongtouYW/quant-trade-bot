"""Telegram 推送模块"""

import requests


class TelegramBot:
    def __init__(self, bot_token: str, chat_id: str):
        self.bot_token = bot_token
        self.chat_id = chat_id
        self.api_base = f"https://api.telegram.org/bot{bot_token}"

    def send_message(self, text: str, parse_mode: str = "HTML") -> bool:
        """发送消息到群组，自动分段处理超长消息"""
        MAX_LEN = 4000  # TG限制4096，留余量

        if len(text) <= MAX_LEN:
            return self._send(text, parse_mode)

        # 按行分段
        lines = text.split("\n")
        chunk = ""
        success = True
        for line in lines:
            if len(chunk) + len(line) + 1 > MAX_LEN:
                if chunk:
                    success = self._send(chunk, parse_mode) and success
                chunk = line
            else:
                chunk = f"{chunk}\n{line}" if chunk else line
        if chunk:
            success = self._send(chunk, parse_mode) and success
        return success

    def _send(self, text: str, parse_mode: str) -> bool:
        """发送单条消息"""
        try:
            resp = requests.post(
                f"{self.api_base}/sendMessage",
                json={
                    "chat_id": self.chat_id,
                    "text": text,
                    "parse_mode": parse_mode,
                    "disable_web_page_preview": True,
                },
                timeout=30,
            )
            result = resp.json()
            if not result.get("ok"):
                print(f"[TG Error] {result.get('description', 'unknown')}")
                return False
            return True
        except Exception as e:
            print(f"[TG Error] {e}")
            return False
