"""每周汇总报告生成器"""

from datetime import datetime, timedelta
from collections import defaultdict
import pytz


def generate_weekly_report(github_client, repos: list, watch_users: list, timezone_str: str = "Asia/Shanghai") -> str:
    """生成每周GitHub活动汇总报告"""
    tz = pytz.timezone(timezone_str)
    now = datetime.now(tz)
    week_start = now - timedelta(days=7)
    since = week_start.strftime("%Y-%m-%dT%H:%M:%SZ")

    report_lines = [
        f"📊 <b>GitHub 每周监控报告</b>",
        f"📅 {week_start.strftime('%Y-%m-%d')} ~ {now.strftime('%Y-%m-%d')}",
        "",
    ]

    total_commits = 0
    total_events = 0

    for repo_cfg in repos:
        owner = repo_cfg["owner"]
        repo = repo_cfg["repo"]
        deploy_server = repo_cfg.get("deploy_server", "")
        full_name = f"{owner}/{repo}"

        report_lines.append(f"{'='*30}")
        report_lines.append(f"🔗 <b>{full_name}</b>")
        if deploy_server:
            report_lines.append(f"🖥 部署服务器: <code>{deploy_server}</code>")
        report_lines.append("")

        # 获取仓库信息
        info = github_client.get_repo_info(owner, repo)
        if info:
            report_lines.append(
                f"⭐ {info.get('stars', 0)} | 🍴 {info.get('forks', 0)} | "
                f"❗ Issues: {info.get('open_issues', 0)} | 语言: {info.get('language', 'N/A')}"
            )
            report_lines.append("")

        # 获取提交记录
        commits = github_client.get_commits(owner, repo, since=since)
        if commits:
            # 按用户分组
            user_commits = defaultdict(list)
            for c in commits:
                user_commits[c["author"]].append(c)

            report_lines.append(f"📝 <b>本周提交 ({len(commits)} 次)</b>")
            for user, user_c in user_commits.items():
                is_watched = "👀" if user in watch_users else ""
                report_lines.append(f"  {is_watched}<b>{user}</b> - {len(user_c)} 次提交:")
                for c in user_c[:5]:  # 最多显示5条
                    date_str = _format_date(c["date"])
                    report_lines.append(f"    • <code>{c['sha']}</code> {c['message'][:60]} ({date_str})")
                if len(user_c) > 5:
                    report_lines.append(f"    ... 还有 {len(user_c) - 5} 次提交")
            report_lines.append("")
            total_commits += len(commits)
        else:
            report_lines.append("📝 本周无新提交")
            report_lines.append("")

        # 获取发布
        releases = github_client.get_releases(owner, repo, since=since)
        if releases:
            report_lines.append(f"🚀 <b>本周发布 ({len(releases)} 个)</b>")
            for r in releases:
                date_str = _format_date(r["date"])
                report_lines.append(f"  • {r['tag']} - {r['name']} by {r['author']} ({date_str})")
                if r["body"]:
                    report_lines.append(f"    {r['body'][:100]}")
            report_lines.append("")

        # 获取事件
        events = github_client.get_events(owner, repo, since=since)
        # 过滤掉已经在commits里展示的PushEvent
        other_events = [e for e in events if e["type"] != "PushEvent"]
        if other_events:
            report_lines.append(f"📋 <b>其他活动 ({len(other_events)} 项)</b>")
            for e in other_events[:10]:
                date_str = _format_date(e["date"])
                report_lines.append(f"  • [{e['type']}] {e['actor']}: {e['summary']} ({date_str})")
            if len(other_events) > 10:
                report_lines.append(f"  ... 还有 {len(other_events) - 10} 项活动")
            report_lines.append("")
            total_events += len(other_events)

    # 汇总
    report_lines.append(f"{'='*30}")
    report_lines.append(f"📈 <b>本周总结</b>")
    report_lines.append(f"  • 监控仓库: {len(repos)} 个")
    report_lines.append(f"  • 总提交数: {total_commits}")
    report_lines.append(f"  • 其他事件: {total_events}")
    report_lines.append(f"\n⏰ 生成时间: {now.strftime('%Y-%m-%d %H:%M:%S %Z')}")

    return "\n".join(report_lines)


def _format_date(date_str: str) -> str:
    """将ISO日期转为简短格式"""
    if not date_str:
        return ""
    try:
        dt = datetime.strptime(date_str, "%Y-%m-%dT%H:%M:%SZ")
        return dt.strftime("%m-%d %H:%M")
    except ValueError:
        return date_str[:10]
