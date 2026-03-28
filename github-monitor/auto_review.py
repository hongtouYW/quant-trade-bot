#!/usr/bin/env python3
"""自动代码评估 - 分析GitHub仓库AI使用情况和代码质量，推送到Telegram"""

import os
import sys
import re
import json
import yaml
import argparse
import requests
from datetime import datetime, timedelta
from collections import defaultdict

import pytz


def load_config(config_path="config.yaml"):
    with open(config_path, "r", encoding="utf-8") as f:
        return yaml.safe_load(f)


class GitHubReviewer:
    BASE_URL = "https://api.github.com"

    def __init__(self, token):
        self.session = requests.Session()
        self.session.headers.update({
            "Accept": "application/vnd.github.v3+json",
            "User-Agent": "GitHub-Monitor-Bot",
            "Authorization": f"token {token}",
        })

    def _get(self, endpoint, params=None):
        resp = self.session.get(f"{self.BASE_URL}{endpoint}", params=params or {}, timeout=30)
        resp.raise_for_status()
        return resp.json()

    def get_all_commits(self, owner, repo, max_pages=10):
        """获取所有提交"""
        all_commits = []
        for page in range(1, max_pages + 1):
            try:
                commits = self._get(f"/repos/{owner}/{repo}/commits", {"per_page": 100, "page": page})
                if not commits:
                    break
                all_commits.extend(commits)
            except Exception:
                break
        return all_commits

    def get_recent_commits(self, owner, repo, since):
        """获取指定日期后的提交"""
        try:
            return self._get(f"/repos/{owner}/{repo}/commits", {"per_page": 100, "since": since})
        except Exception:
            return []

    def get_repo_info(self, owner, repo):
        try:
            return self._get(f"/repos/{owner}/{repo}")
        except Exception:
            return {}

    def get_languages(self, owner, repo):
        try:
            return self._get(f"/repos/{owner}/{repo}/languages")
        except Exception:
            return {}

    def get_contributors(self, owner, repo):
        try:
            return self._get(f"/repos/{owner}/{repo}/contributors")
        except Exception:
            return []

    def get_file_tree(self, owner, repo, branch="main"):
        try:
            return self._get(f"/repos/{owner}/{repo}/git/trees/{branch}", {"recursive": "1"})
        except Exception:
            return {"tree": []}

    def get_file_content(self, owner, repo, path):
        try:
            resp = self.session.get(
                f"https://raw.githubusercontent.com/{owner}/{repo}/main/{path}", timeout=30
            )
            if resp.status_code == 200:
                return resp.text
        except Exception:
            pass
        return ""


def analyze_ai_usage(commits):
    """分析AI使用情况"""
    ai_patterns = [
        "co-authored-by: claude",
        "co-authored-by: github copilot",
        "co-authored-by: cursor",
        "co-authored-by: chatgpt",
        "co-authored-by: devin",
    ]

    total = len(commits)
    ai_commits = []
    ai_tools = defaultdict(int)
    monthly = defaultdict(lambda: {"total": 0, "ai": 0})

    for c in commits:
        msg = c.get("commit", {}).get("message", "")
        date = c.get("commit", {}).get("author", {}).get("date", "")[:7]
        monthly[date]["total"] += 1

        is_ai = any(p in msg.lower() for p in ai_patterns)
        if is_ai:
            ai_commits.append(c)
            monthly[date]["ai"] += 1
            for line in msg.split("\n"):
                if "co-authored-by" in line.lower():
                    match = re.search(r"Co-Authored-By:\s*(.+?)\s*<", line)
                    if match:
                        ai_tools[match.group(1).strip()] += 1

    return {
        "total": total,
        "ai_count": len(ai_commits),
        "ai_pct": len(ai_commits) * 100 // max(total, 1),
        "ai_tools": dict(ai_tools),
        "monthly": dict(monthly),
    }


def analyze_code_quality(reviewer, owner, repo):
    """分析代码质量"""
    tree_data = reviewer.get_file_tree(owner, repo)
    tree = tree_data.get("tree", [])

    issues = []
    strengths = []
    suggestions = []

    # 文件统计
    file_exts = defaultdict(int)
    total_files = 0
    test_files = 0
    ci_files = 0
    env_files = []
    large_files = []

    for f in tree:
        if f["type"] != "blob":
            continue
        total_files += 1
        path = f["path"]
        ext = path.rsplit(".", 1)[-1] if "." in path else "no-ext"
        file_exts[ext] += 1

        if "__test" in path or ".test." in path or ".spec." in path:
            test_files += 1
        if ".github" in path or "Dockerfile" in path.lower():
            ci_files += 1
        if ".env" in path and ".example" not in path and ".gitignore" not in path:
            env_files.append(path)
        if f.get("size", 0) > 50000:
            large_files.append((path, f["size"]))

    # 检查测试
    if test_files == 0:
        issues.append(("🟡 中等", "零测试覆盖", f"{total_files}个文件, 0个测试"))
        suggestions.append("添加测试框架 (Vitest/Jest) + 覆盖核心逻辑")
    elif test_files < total_files * 0.05:
        issues.append(("🟡 中等", "测试覆盖率极低", f"{test_files}/{total_files}"))

    # 检查CI
    if ci_files == 0:
        issues.append(("🟡 中等", "无 CI/CD 流水线", "缺少自动化构建/检查"))
        suggestions.append("添加 GitHub Actions CI (lint + build + type check)")

    # 检查 .env 暴露
    if env_files:
        issues.append(("🔴 严重", ".env 文件提交到仓库", ", ".join(env_files)))

    # 检查大文件
    for path, size in large_files:
        if any(k in path for k in ["mock", "sample", "fixture", "seed"]):
            issues.append(("🟡 中等", f"大型Mock数据打包到生产", f"{path} ({size // 1024}KB)"))

    # 检查 package.json
    pkg_content = reviewer.get_file_content(owner, repo, "package.json")
    if pkg_content:
        try:
            pkg = json.loads(pkg_content)
            deps = pkg.get("dependencies", {})
            dev_deps = pkg.get("devDependencies", {})

            # TypeScript strict?
            tsconfig = reviewer.get_file_content(owner, repo, "tsconfig.json") or reviewer.get_file_content(owner, repo, "tsconfig.app.json")
            if tsconfig and '"strict": true' in tsconfig:
                strengths.append("TypeScript 严格模式")

            # ESLint?
            if any("eslint" in d for d in dev_deps):
                strengths.append("ESLint 代码检查")

            # 状态管理
            if "@tanstack/react-query" in deps:
                strengths.append("React Query 服务端状态管理")
            if "zustand" in deps:
                strengths.append("Zustand 客户端状态管理")
            if "redux" in deps or "@reduxjs/toolkit" in deps:
                strengths.append("Redux 状态管理")

        except json.JSONDecodeError:
            pass

    # 检查关键文件的安全问题
    for check_path in ["src/lib/axios.ts", "src/lib/axios.js", "src/api/index.ts", "src/utils/request.ts"]:
        content = reviewer.get_file_content(owner, repo, check_path)
        if content:
            # 硬编码 URL
            if re.search(r'baseURL:\s*["\']https?://', content):
                issues.append(("🔴 严重", "API baseURL 硬编码", "建议用环境变量"))
                suggestions.append("环境变量管理 API URL (.env)")
            break

    # 检查加密密钥硬编码
    for check_path in tree:
        path = check_path["path"]
        if check_path["type"] != "blob":
            continue
        if any(k in path.lower() for k in ["encrypt", "crypto", "secret", "utils.ts"]):
            content = reviewer.get_file_content(owner, repo, path)
            if content and re.search(r'(key|secret|iv)\s*[:=]\s*["\'][A-Za-z0-9+/=]{16,}["\']', content, re.IGNORECASE):
                issues.append(("🔴 严重", "加密密钥硬编码在源码", f"{path}"))
                suggestions.append("密钥不应硬编码在前端源码")
                break

    # 计算评分
    score_map = {"代码质量": 7, "安全性": 7, "可维护性": 7, "工程化": 7, "性能": 7}

    severe = sum(1 for s, _, _ in issues if "严重" in s)
    medium = sum(1 for s, _, _ in issues if "中等" in s)

    score_map["安全性"] -= severe * 1.5
    score_map["工程化"] -= (1 if test_files == 0 else 0) * 2 - (1 if ci_files == 0 else 0)
    score_map["可维护性"] -= (1 if test_files == 0 else 0)

    # 加分项
    for s in strengths:
        if "TypeScript" in s:
            score_map["代码质量"] += 0.5
        if "ESLint" in s:
            score_map["代码质量"] += 0.5
        if "React Query" in s:
            score_map["可维护性"] += 0.5

    # 限制范围
    for k in score_map:
        score_map[k] = max(2, min(10, round(score_map[k])))

    overall = round(sum(score_map.values()) / len(score_map), 1)

    return {
        "total_files": total_files,
        "file_exts": dict(file_exts),
        "test_files": test_files,
        "issues": issues,
        "strengths": strengths,
        "suggestions": suggestions,
        "scores": score_map,
        "overall": overall,
    }


def format_report(repo_info, languages, contributors, ai_data, quality_data, owner, repo):
    """生成 Telegram HTML 报告"""
    now = datetime.now(pytz.timezone("Asia/Shanghai"))
    full_name = f"{owner}/{repo}"

    # 语言统计
    total_bytes = sum(languages.values()) or 1
    lang_str = ", ".join(f"{k} {v * 100 // total_bytes}%" for k, v in
                         sorted(languages.items(), key=lambda x: -x[1])[:3])

    # 贡献者
    contrib_str = ", ".join(f"{c['login']} ({c['contributions']})" for c in contributors[:5])

    lines = [
        f"📊 <b>{full_name} 每周代码评估</b>",
        f"📅 {now.strftime('%Y-%m-%d')}",
        "",
        f"<b>📌 项目概况</b>",
        f"• 技术栈: {lang_str}",
        f"• 文件数: {quality_data['total_files']}",
        f"• 贡献者: {contrib_str or 'N/A'}",
        f"• 版本: {repo_info.get('default_branch', 'main')}",
        "",
        "━━━━━━━━━━━━━━━━━━━━",
        "",
        f"<b>🤖 AI 使用分析</b>",
        f"• 总提交: <b>{ai_data['total']}</b> | AI辅助: <b>{ai_data['ai_count']} ({ai_data['ai_pct']}%)</b>",
    ]

    if ai_data["ai_tools"]:
        tools_str = ", ".join(f"{k}: {v}" for k, v in sorted(ai_data["ai_tools"].items(), key=lambda x: -x[1]))
        lines.append(f"• AI工具: {tools_str}")

    # 月度趋势
    lines.append("")
    lines.append("<code>")
    for month in sorted(ai_data["monthly"].keys())[-6:]:
        d = ai_data["monthly"][month]
        pct = d["ai"] * 100 // max(d["total"], 1)
        bar = "█" * (pct // 10) + "░" * (10 - pct // 10)
        lines.append(f"{month}: {d['ai']:3d}/{d['total']:3d} ({pct:2d}%) {bar}")
    lines.append("</code>")

    # 问题
    lines.extend(["", "━━━━━━━━━━━━━━━━━━━━", ""])
    lines.append("<b>🐛 发现的问题</b>")
    if quality_data["issues"]:
        for severity, title, detail in quality_data["issues"]:
            lines.append(f"{severity} {title}: {detail}")
    else:
        lines.append("✅ 未发现明显问题")

    # 优势
    lines.extend(["", "<b>💪 做得好的点</b>"])
    for s in quality_data["strengths"]:
        lines.append(f"✅ {s}")

    # 建议
    if quality_data["suggestions"]:
        lines.extend(["", "<b>🚀 建议加强</b>"])
        for i, s in enumerate(quality_data["suggestions"], 1):
            lines.append(f"{i}. {s}")

    # 评分
    lines.extend(["", "━━━━━━━━━━━━━━━━━━━━", ""])
    lines.append(f"<b>⭐ 综合评分: {quality_data['overall']} / 10</b>")
    lines.append("")
    lines.append("<code>")
    star_map = {10: "★★★★★", 9: "★★★★★", 8: "★★★★☆", 7: "★★★★☆",
                6: "★★★☆☆", 5: "★★★☆☆", 4: "★★☆☆☆", 3: "★★☆☆☆", 2: "★☆☆☆☆"}
    for dim, score in quality_data["scores"].items():
        stars = star_map.get(score, "★★★☆☆")
        lines.append(f"{dim:　<5} {stars} {score}/10")
    lines.append("</code>")

    lines.append(f"\n⏰ {now.strftime('%Y-%m-%d %H:%M %Z')}")

    return "\n".join(lines)


def send_telegram(bot_token, chat_id, text):
    """发送到 Telegram"""
    MAX_LEN = 4000
    chunks = []
    current = ""
    for line in text.split("\n"):
        if len(current) + len(line) + 1 > MAX_LEN:
            chunks.append(current)
            current = line
        else:
            current = f"{current}\n{line}" if current else line
    if current:
        chunks.append(current)

    for chunk in chunks:
        resp = requests.post(
            f"https://api.telegram.org/bot{bot_token}/sendMessage",
            json={"chat_id": chat_id, "text": chunk, "parse_mode": "HTML", "disable_web_page_preview": True},
            timeout=30,
        )
        result = resp.json()
        if not result.get("ok"):
            print(f"[TG Error] {result.get('description')}")
            return False
    return True


def run_review(config, repo_cfg, chat_id=None):
    """对单个仓库执行评估"""
    # 支持 per-repo token，fallback 到全局 token
    token = repo_cfg.get("token") or config["github"]["token"]
    bot_token = config["telegram"]["bot_token"]
    if not chat_id:
        chat_id = str(config["telegram"]["chat_id"])

    owner = repo_cfg["owner"]
    repo = repo_cfg["repo"]
    print(f"[{datetime.now()}] 开始评估 {owner}/{repo}...")

    reviewer = GitHubReviewer(token)

    # 获取数据
    repo_info = reviewer.get_repo_info(owner, repo)
    languages = reviewer.get_languages(owner, repo)
    contributors = reviewer.get_contributors(owner, repo)
    all_commits = reviewer.get_all_commits(owner, repo)

    # 分析
    ai_data = analyze_ai_usage(all_commits)
    quality_data = analyze_code_quality(reviewer, owner, repo)

    # 生成报告
    report = format_report(repo_info, languages, contributors, ai_data, quality_data, owner, repo)

    # 发送
    if send_telegram(bot_token, chat_id, report):
        print(f"[{datetime.now()}] 评估报告发送成功! -> {chat_id}")
    else:
        print(f"[{datetime.now()}] 发送失败!")

    # 保存本地
    os.makedirs("reports", exist_ok=True)
    date_str = datetime.now().strftime("%Y-%m-%d")
    filename = f"reports/{owner}_{repo}_review_{date_str}.md"
    # 简单转 markdown (去 HTML tags)
    md = report.replace("<b>", "**").replace("</b>", "**").replace("<code>", "`").replace("</code>", "`")
    with open(filename, "w", encoding="utf-8") as f:
        f.write(md)
    print(f"  保存到 {filename}")


def main():
    parser = argparse.ArgumentParser(description="GitHub 代码评估")
    parser.add_argument("--config", default="config.yaml", help="配置文件路径")
    parser.add_argument("--now", action="store_true", help="立即执行评估")
    parser.add_argument("--repo", help="指定仓库 owner/repo")
    parser.add_argument("--chat-id", help="指定推送的 chat_id")
    parser.add_argument("--token", help="指定 GitHub token")
    args = parser.parse_args()

    os.chdir(os.path.dirname(os.path.abspath(__file__)))
    config = load_config(args.config)

    if args.now:
        if args.repo:
            parts = args.repo.split("/")
            repo_cfg = {"owner": parts[0], "repo": parts[1]}
            if args.token:
                repo_cfg["token"] = args.token
            run_review(config, repo_cfg, args.chat_id)
        else:
            # 评估所有配置的仓库
            for repo_cfg in config["github"]["repos"]:
                chat_id = repo_cfg.get("review_chat_id") or args.chat_id
                run_review(config, repo_cfg, chat_id)


if __name__ == "__main__":
    main()
