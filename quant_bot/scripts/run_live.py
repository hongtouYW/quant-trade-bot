#!/usr/bin/env python3
"""实盘运行 - 请确认已完成模拟盘验证"""
import os
import sys
import logging

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app.config import load_config
from app.main import create_bot

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(name)s: %(message)s',
    handlers=[
        logging.StreamHandler(),
        logging.FileHandler(os.path.join(os.path.dirname(os.path.dirname(__file__)), 'logs', 'live.log')),
    ]
)

if __name__ == '__main__':
    os.makedirs(os.path.join(os.path.dirname(os.path.dirname(__file__)), 'logs'), exist_ok=True)

    print("=" * 50)
    print("⚠️  实盘模式 - 使用真实资金")
    print("=" * 50)
    confirm = input("确认启动实盘? (输入 YES): ")
    if confirm != 'YES':
        print("已取消")
        sys.exit(0)

    load_config()
    bot = create_bot(paper_mode=False, sandbox=False)
    bot.start()
    print("实盘已启动，按 Ctrl+C 停止")
    try:
        import time
        while True:
            time.sleep(60)
    except KeyboardInterrupt:
        bot.stop()
        print("实盘已停止")
