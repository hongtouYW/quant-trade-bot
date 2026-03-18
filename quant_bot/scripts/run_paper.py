#!/usr/bin/env python3
"""模拟盘运行"""
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
        logging.FileHandler(os.path.join(os.path.dirname(os.path.dirname(__file__)), 'logs', 'paper.log')),
    ]
)

if __name__ == '__main__':
    os.makedirs(os.path.join(os.path.dirname(os.path.dirname(__file__)), 'logs'), exist_ok=True)
    load_config()
    bot = create_bot(paper_mode=True, sandbox=True)
    bot.start()
    print("模拟盘已启动，按 Ctrl+C 停止")
    try:
        import time
        while True:
            time.sleep(60)
    except KeyboardInterrupt:
        bot.stop()
        print("模拟盘已停止")
