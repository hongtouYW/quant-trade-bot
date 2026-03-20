"""模拟盘执行器 (Spec §24)
验证:
  - 实时数据稳定性
  - 下单逻辑正确性
  - 持仓管理正确性
  - 监控与告警链路
  - 冷却机制是否生效
  - 回测和实时表现差距
"""
import os
import sys
import time
import signal
import logging

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))

from app.config import load_config
from app.main import create_bot, get_bot
from app.monitoring.logger import setup_logging

log = logging.getLogger(__name__)


class PaperRunner:
    """模拟盘运行管理器"""

    def __init__(self, config_path=None, sandbox=True):
        self.config_path = config_path
        self.sandbox = sandbox
        self.bot = None

    def start(self):
        log_dir = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(__file__))), 'logs')
        setup_logging(log_dir=log_dir)

        load_config(self.config_path)
        self.bot = create_bot(
            config_path=self.config_path,
            paper_mode=True,
            sandbox=self.sandbox,
        )

        # 优雅退出
        signal.signal(signal.SIGINT, self._handle_stop)
        signal.signal(signal.SIGTERM, self._handle_stop)

        log.info("模拟盘启动")
        self.bot.start()

    def run_forever(self):
        self.start()
        try:
            while self.bot and self.bot.running:
                time.sleep(60)
        except KeyboardInterrupt:
            pass
        finally:
            self.stop()

    def stop(self):
        if self.bot:
            self.bot.stop()
            log.info("模拟盘已停止")

    def _handle_stop(self, signum, frame):
        log.info(f"收到信号 {signum}, 停止模拟盘...")
        self.stop()
        sys.exit(0)


def main():
    import argparse
    parser = argparse.ArgumentParser(description='QuantBot 模拟盘')
    parser.add_argument('--config', default=None, help='配置文件路径')
    parser.add_argument('--no-sandbox', action='store_true', help='不使用沙盒模式')
    args = parser.parse_args()

    runner = PaperRunner(
        config_path=args.config,
        sandbox=not args.no_sandbox,
    )
    runner.run_forever()


if __name__ == '__main__':
    main()
