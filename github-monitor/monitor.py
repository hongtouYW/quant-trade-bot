#!/usr/bin/env python3
"""GitHub 监控主程序 - 每周汇总推送到 Telegram"""

import os
import sys
import yaml
import argparse
import schedule
import time
import pytz
from datetime import datetime

from github_client import GitHubClient
from telegram_bot import TelegramBot
from report_generator import generate_weekly_report
from auto_review import run_review


def load_config(config_path: str = "config.yaml") -> dict:
    with open(config_path, "r", encoding="utf-8") as f:
        return yaml.safe_load(f)


def run_weekly_report(config: dict):
    """执行一次每周报告"""
    print(f"[{datetime.now()}] 开始生成每周报告...")

    gh = GitHubClient(token=config["github"].get("token", ""))
    tg = TelegramBot(
        bot_token=config["telegram"]["bot_token"],
        chat_id=str(config["telegram"]["chat_id"]),
    )

    repos = config["github"]["repos"]
    watch_users = config["github"].get("watch_users", [])
    timezone_str = config["schedule"].get("timezone", "Asia/Shanghai")

    report = generate_weekly_report(gh, repos, watch_users, timezone_str)

    if tg.send_message(report):
        print(f"[{datetime.now()}] 报告发送成功!")
    else:
        print(f"[{datetime.now()}] 报告发送失败!")


def run_all_reviews(config: dict):
    """对所有仓库执行代码评估"""
    print(f"[{datetime.now()}] 开始执行代码评估...")
    for repo_cfg in config["github"]["repos"]:
        chat_id = repo_cfg.get("review_chat_id")
        try:
            run_review(config, repo_cfg, chat_id)
        except Exception as e:
            print(f"[{datetime.now()}] 评估 {repo_cfg['owner']}/{repo_cfg['repo']} 失败: {e}")


def main():
    parser = argparse.ArgumentParser(description="GitHub 监控 - Telegram 推送")
    parser.add_argument("--config", default="config.yaml", help="配置文件路径")
    parser.add_argument("--now", action="store_true", help="立即执行一次报告")
    parser.add_argument("--daemon", action="store_true", help="守护进程模式，按计划执行")
    args = parser.parse_args()

    # 切换到脚本所在目录
    os.chdir(os.path.dirname(os.path.abspath(__file__)))

    config = load_config(args.config)

    if args.now:
        run_weekly_report(config)
        return

    if args.daemon:
        sched_cfg = config["schedule"]
        day_map = {0: "monday", 1: "tuesday", 2: "wednesday", 3: "thursday",
                   4: "friday", 5: "saturday", 6: "sunday"}

        # 每周活动汇总 (周日)
        day_name = day_map.get(sched_cfg.get("weekly_day", 6), "sunday")
        hour = sched_cfg.get("weekly_hour", 20)
        time_str = f"{hour:02d}:00"
        getattr(schedule.every(), day_name).at(time_str).do(run_weekly_report, config)

        # 每周代码评估 (周五)
        review_day = day_map.get(sched_cfg.get("review_day", 4), "friday")
        review_hour = sched_cfg.get("review_hour", 20)
        review_time = f"{review_hour:02d}:00"
        getattr(schedule.every(), review_day).at(review_time).do(run_all_reviews, config)

        repo_names = [f"{r['owner']}/{r['repo']}" for r in config["github"]["repos"]]
        print(f"[GitHub Monitor] 守护进程启动")
        print(f"  监控仓库: {repo_names}")
        print(f"  活动汇总: 每周{day_name} {time_str} → Github监控群")
        print(f"  代码评估: 每周{review_day} {review_time} → 各项目开发群")

        while True:
            schedule.run_pending()
            time.sleep(60)
    else:
        parser.print_help()


if __name__ == "__main__":
    main()
