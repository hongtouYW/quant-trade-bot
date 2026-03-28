#!/usr/bin/env python3
"""深度代码审查 - 分析安全漏洞、改进建议、功能推荐"""

import os
import re
import json
import yaml
import requests
from datetime import datetime
from collections import defaultdict


def load_config(path="config.yaml"):
    with open(path, "r", encoding="utf-8") as f:
        return yaml.safe_load(f)


class Reviewer:
    BASE = "https://api.github.com"

    def __init__(self, token):
        self.s = requests.Session()
        self.s.headers.update({
            "Accept": "application/vnd.github.v3+json",
            "Authorization": f"token {token}",
        })

    def get(self, ep, params=None):
        r = self.s.get(f"{self.BASE}{ep}", params=params or {}, timeout=30)
        r.raise_for_status()
        return r.json()

    def raw(self, owner, repo, path):
        try:
            r = self.s.get(f"https://raw.githubusercontent.com/{owner}/{repo}/main/{path}", timeout=30)
            return r.text if r.status_code == 200 else ""
        except:
            return ""

    def tree(self, owner, repo):
        try:
            return self.get(f"/repos/{owner}/{repo}/git/trees/main", {"recursive": "1"}).get("tree", [])
        except:
            # try master branch
            try:
                return self.get(f"/repos/{owner}/{repo}/git/trees/master", {"recursive": "1"}).get("tree", [])
            except:
                return []

    def info(self, owner, repo):
        try:
            return self.get(f"/repos/{owner}/{repo}")
        except:
            return {}

    def langs(self, owner, repo):
        try:
            return self.get(f"/repos/{owner}/{repo}/languages")
        except:
            return {}

    def commits(self, owner, repo, pages=7):
        all_c = []
        for p in range(1, pages + 1):
            try:
                c = self.get(f"/repos/{owner}/{repo}/commits", {"per_page": 100, "page": p})
                if not c:
                    break
                all_c.extend(c)
            except:
                break
        return all_c

    def contribs(self, owner, repo):
        try:
            return self.get(f"/repos/{owner}/{repo}/contributors")
        except:
            return []


def deep_review(rv, owner, repo):
    """深度审查一个仓库"""
    files = rv.tree(owner, repo)
    repo_info = rv.info(owner, repo)
    languages = rv.langs(owner, repo)
    all_commits = rv.commits(owner, repo)
    contributors = rv.contribs(owner, repo)

    total_bytes = sum(languages.values()) or 1
    lang_str = ", ".join(f"{k} {v*100//total_bytes}%" for k, v in sorted(languages.items(), key=lambda x: -x[1])[:4])
    contrib_str = ", ".join(f"{c['login']} ({c['contributions']})" for c in contributors[:5])

    # AI 分析
    ai_count = 0
    ai_tools = defaultdict(int)
    for c in all_commits:
        msg = c.get("commit", {}).get("message", "").lower()
        if "co-authored-by: claude" in msg or "co-authored-by: cursor" in msg or "co-authored-by: copilot" in msg:
            ai_count += 1
            for line in c["commit"]["message"].split("\n"):
                if "co-authored-by" in line.lower():
                    m = re.search(r"Co-Authored-By:\s*(.+?)\s*<", line)
                    if m:
                        ai_tools[m.group(1).strip()] += 1

    # 文件分析
    blobs = [f for f in files if f["type"] == "blob"]
    total_files = len(blobs)
    exts = defaultdict(int)
    test_files = []
    ci_files = []
    env_exposed = []
    large_files = []
    lock_files = []

    for f in blobs:
        p = f["path"]
        ext = p.rsplit(".", 1)[-1] if "." in p else ""
        exts[ext] += 1
        if "__test" in p or ".test." in p or ".spec." in p or "_test.go" in p or "tests/" in p:
            test_files.append(p)
        if ".github/workflows" in p or "Dockerfile" in p.lower() or "Jenkinsfile" in p:
            ci_files.append(p)
        if p.endswith(".env") or (p.endswith(".env.local") and ".example" not in p):
            env_exposed.append(p)
        if f.get("size", 0) > 100000:
            large_files.append((p, f["size"]))
        if "package-lock" in p or "yarn.lock" in p or "composer.lock" in p:
            lock_files.append(p)

    # ===== 安全漏洞检查 =====
    vulnerabilities = []
    improvements = []
    features = []

    # 1. 检查密钥硬编码
    secret_patterns = [
        (r'(api[_-]?key|secret[_-]?key|password|token|private[_-]?key)\s*[:=]\s*["\'][A-Za-z0-9+/=_\-]{8,}["\']', "密钥/密码硬编码"),
        (r'(sk-[a-zA-Z0-9]{20,})', "OpenAI API Key 泄露"),
        (r'(ghp_[a-zA-Z0-9]{36})', "GitHub Token 泄露"),
        (r'(AKIA[0-9A-Z]{16})', "AWS Access Key 泄露"),
    ]

    checked_files = 0
    for f in blobs:
        p = f["path"]
        if any(p.endswith(e) for e in [".ts", ".tsx", ".js", ".jsx", ".py", ".go", ".php", ".java", ".kt", ".env"]):
            if f.get("size", 0) < 50000 and checked_files < 30:  # 限制检查量
                content = rv.raw(owner, repo, p)
                checked_files += 1
                if not content:
                    continue

                for pattern, desc in secret_patterns:
                    if re.search(pattern, content, re.IGNORECASE):
                        vulnerabilities.append(("🔴 严重", f"{desc}", p))
                        break

                # 检查 SQL 注入风险
                if re.search(r'(query|execute|raw)\s*\(.*\+.*\)|f".*SELECT.*{|f".*INSERT.*{', content):
                    vulnerabilities.append(("🔴 严重", "潜在 SQL 注入风险", p))

                # 检查 XSS 风险 (dangerouslySetInnerHTML, v-html)
                if "dangerouslySetInnerHTML" in content or "v-html" in content:
                    vulnerabilities.append(("🟡 中等", "XSS 风险 (innerHTML)", p))

                # 检查硬编码 URL
                if re.search(r'baseURL\s*[:=]\s*["\']https?://', content):
                    vulnerabilities.append(("🟡 中等", "API URL 硬编码", p))

                # 检查 CORS 配置
                if re.search(r'(cors|access-control).*\*', content, re.IGNORECASE):
                    vulnerabilities.append(("🟡 中等", "CORS 允许所有来源 (*)", p))

                # 检查 eval/exec
                if re.search(r'\beval\s*\(|\bexec\s*\(', content):
                    vulnerabilities.append(("🔴 严重", "使用 eval/exec (代码注入风险)", p))

                # 检查未加密 HTTP
                if re.search(r'http://(?!localhost|127\.0\.0\.1|0\.0\.0\.0)', content):
                    vulnerabilities.append(("🟢 轻微", "使用未加密 HTTP 连接", p))

    # 2. .env 文件暴露
    if env_exposed:
        vulnerabilities.append(("🔴 严重", f".env 文件提交到仓库", ", ".join(env_exposed)))

    # 3. 无 .gitignore 检查
    gitignore = [f for f in blobs if f["path"] == ".gitignore"]
    if not gitignore:
        vulnerabilities.append(("🟡 中等", "缺少 .gitignore 文件", "可能提交敏感文件"))

    # ===== 改进建议 =====

    # 测试覆盖
    if not test_files:
        improvements.append(("🔴 关键", "零测试覆盖", f"{total_files}个文件无任何测试", "添加测试框架，至少覆盖核心业务逻辑"))
    elif len(test_files) < total_files * 0.05:
        improvements.append(("🟡 建议", "测试覆盖率极低", f"{len(test_files)}/{total_files}", "提高测试覆盖率到 20% 以上"))

    # CI/CD
    if not ci_files:
        improvements.append(("🟡 建议", "无 CI/CD 流水线", "缺少自动化构建/测试/部署", "添加 GitHub Actions: lint → test → build → deploy"))

    # 大文件
    for p, size in large_files:
        if any(k in p.lower() for k in ["mock", "sample", "fixture", "seed", "test-data"]):
            improvements.append(("🟢 优化", f"大型数据文件", f"{p} ({size//1024}KB)", "动态加载或移到 CDN"))

    # 检查 README
    readme = [f for f in blobs if f["path"].lower() in ["readme.md", "readme.rst", "readme.txt"]]
    if not readme:
        improvements.append(("🟢 优化", "缺少 README", "", "添加项目说明、安装步骤、使用方式"))

    # 检查 TypeScript strict
    for f in blobs:
        if f["path"] in ["tsconfig.json", "tsconfig.app.json"]:
            content = rv.raw(owner, repo, f["path"])
            if content and '"strict": true' not in content and '"strict":true' not in content:
                improvements.append(("🟡 建议", "TypeScript 未开启严格模式", f["path"], "设置 strict: true 提高类型安全"))
            break

    # 检查 package.json 依赖版本
    pkg_content = rv.raw(owner, repo, "package.json")
    if pkg_content:
        try:
            pkg = json.loads(pkg_content)
            deps = pkg.get("dependencies", {})
            dev_deps = pkg.get("devDependencies", {})

            # 检查是否有 prettier/eslint
            if not any("eslint" in d for d in {**deps, **dev_deps}):
                improvements.append(("🟢 优化", "缺少 ESLint", "", "添加代码规范检查"))
            if not any("prettier" in d for d in {**deps, **dev_deps}):
                improvements.append(("🟢 优化", "缺少 Prettier", "", "添加代码格式化工具"))

            # 检查过时依赖 (主版本号为0的不稳定依赖)
            unstable = [k for k, v in deps.items() if v.startswith("0.") or v.startswith("^0.")]
            if len(unstable) > 3:
                improvements.append(("🟢 优化", f"{len(unstable)}个不稳定依赖 (0.x)", ", ".join(unstable[:5]), "评估是否有稳定替代"))

        except json.JSONDecodeError:
            pass

    # 检查 Go 项目
    go_mod = rv.raw(owner, repo, "go.mod")
    if go_mod:
        if "go 1.2" in go_mod and "go 1.22" not in go_mod and "go 1.23" not in go_mod:
            improvements.append(("🟢 优化", "Go 版本较旧", "", "考虑升级到 Go 1.22+"))

    # 检查 PHP/Laravel 项目
    composer_content = rv.raw(owner, repo, "composer.json") or rv.raw(owner, repo, "backend/composer.json")
    if composer_content:
        try:
            composer = json.loads(composer_content)
            php_deps = composer.get("require", {})
            if "laravel/framework" in php_deps:
                # 检查 Laravel 版本
                lv = php_deps["laravel/framework"]
                if "8." in lv or "9." in lv:
                    improvements.append(("🟡 建议", f"Laravel 版本较旧 ({lv})", "", "考虑升级到 Laravel 11"))
        except json.JSONDecodeError:
            pass

    # ===== 功能推荐 =====

    # 根据项目类型推荐
    main_lang = max(languages, key=languages.get) if languages else ""

    if main_lang in ["TypeScript", "JavaScript"]:
        if not any("sentry" in str(d).lower() for d in [pkg_content or ""]):
            features.append(("📊 监控", "添加 Sentry 错误监控", "线上错误实时追踪，快速定位问题"))
        if "react" in (pkg_content or "").lower():
            if "error" not in str([f["path"] for f in blobs if "boundary" in f["path"].lower()]):
                features.append(("🛡️ 稳定性", "添加 Error Boundary", "防止单组件错误导致整页崩溃"))
            if not any("storybook" in f["path"].lower() for f in blobs):
                features.append(("📖 文档", "添加 Storybook 组件文档", "可视化组件开发和测试"))

    if main_lang in ["PHP"]:
        if not any("telescope" in str(composer_content or "").lower()):
            features.append(("📊 监控", "添加 Laravel Telescope", "请求/查询/队列可视化调试"))
        if not any("horizon" in str(composer_content or "").lower()):
            features.append(("⚡ 性能", "添加 Laravel Horizon", "队列任务监控和管理"))

    if main_lang in ["Go"]:
        if not any("prometheus" in str(go_mod or "").lower()):
            features.append(("📊 监控", "添加 Prometheus Metrics", "服务健康状态和性能监控"))
        if not any("swagger" in str(go_mod or "").lower() or "swag" in str(go_mod or "").lower()):
            features.append(("📖 文档", "添加 Swagger API 文档", "自动生成 API 文档"))

    if main_lang in ["Kotlin", "Java"]:
        features.append(("🔒 安全", "添加 ProGuard/R8 混淆", "防止 APK 反编译"))
        features.append(("📊 监控", "添加 Firebase Crashlytics", "线上崩溃实时监控"))

    # 通用推荐
    if not ci_files:
        features.append(("🔄 DevOps", "添加 GitHub Actions CI/CD", "自动化测试 → 构建 → 部署"))

    if not any("docker" in f["path"].lower() for f in blobs):
        features.append(("🐳 容器化", "添加 Docker 支持", "统一开发/部署环境"))

    # ===== 评分 =====
    scores = {"安全性": 8, "代码质量": 7, "可维护性": 7, "工程化": 7, "性能": 7}

    severe = sum(1 for v in vulnerabilities if "严重" in v[0])
    medium_v = sum(1 for v in vulnerabilities if "中等" in v[0])
    scores["安全性"] -= severe * 1.5 + medium_v * 0.5

    if not test_files:
        scores["代码质量"] -= 1.5
        scores["工程化"] -= 1.5
    if not ci_files:
        scores["工程化"] -= 1

    scores = {k: max(2, min(10, round(v, 1))) for k, v in scores.items()}
    overall = round(sum(scores.values()) / len(scores), 1)

    return {
        "repo_info": repo_info,
        "lang_str": lang_str,
        "contrib_str": contrib_str,
        "total_files": total_files,
        "total_commits": len(all_commits),
        "ai_count": ai_count,
        "ai_pct": ai_count * 100 // max(len(all_commits), 1),
        "ai_tools": dict(ai_tools),
        "test_files": len(test_files),
        "ci_files": len(ci_files),
        "vulnerabilities": vulnerabilities,
        "improvements": improvements,
        "features": features,
        "scores": scores,
        "overall": overall,
    }


def format_tg_report(data, owner, repo):
    """格式化 Telegram 报告"""
    now = datetime.now().strftime("%Y-%m-%d")
    ai_tools_str = ", ".join(f"{k}: {v}" for k, v in sorted(data["ai_tools"].items(), key=lambda x: -x[1])) if data["ai_tools"] else "无"

    lines = [
        f"🔍 <b>{owner}/{repo} 深度代码审查</b>",
        f"📅 {now}",
        "",
        f"<b>📌 概况</b>",
        f"• 技术栈: {data['lang_str']}",
        f"• 文件: {data['total_files']} | 提交: {data['total_commits']} | 测试: {data['test_files']}",
        f"• 贡献者: {data['contrib_str']}",
        f"• AI使用: {data['ai_count']}/{data['total_commits']} ({data['ai_pct']}%) — {ai_tools_str}",
    ]

    # 安全漏洞
    lines.extend(["", "━━━━━━━━━━━━━━━━━━━━", ""])
    lines.append("<b>🔒 安全漏洞</b>")
    if data["vulnerabilities"]:
        for sev, title, detail in data["vulnerabilities"]:
            lines.append(f"{sev} <b>{title}</b>")
            lines.append(f"   → {detail}")
    else:
        lines.append("✅ 未发现明显安全漏洞")

    # 改进建议
    lines.extend(["", "<b>🔧 改进建议</b>"])
    if data["improvements"]:
        for sev, title, detail, suggestion in data["improvements"]:
            lines.append(f"{sev} <b>{title}</b>: {detail}")
            lines.append(f"   💡 {suggestion}")
    else:
        lines.append("✅ 代码质量良好")

    # 功能推荐
    lines.extend(["", "<b>🚀 功能推荐</b>"])
    if data["features"]:
        for icon, title, desc in data["features"]:
            lines.append(f"{icon} <b>{title}</b>")
            lines.append(f"   {desc}")
    else:
        lines.append("暂无推荐")

    # 评分
    lines.extend(["", "━━━━━━━━━━━━━━━━━━━━", ""])
    lines.append(f"<b>⭐ 综合评分: {data['overall']} / 10</b>")
    star_map = {10: "★★★★★", 9: "★★★★★", 8: "★★★★☆", 7: "★★★★☆",
                6: "★★★☆☆", 5: "★★★☆☆", 4: "★★☆☆☆", 3: "★★☆☆☆", 2: "★☆☆☆☆"}
    lines.append("<code>")
    for dim, score in data["scores"].items():
        stars = star_map.get(int(score), "★★★☆☆")
        lines.append(f"{dim:　<5} {stars} {score}/10")
    lines.append("</code>")

    return "\n".join(lines)


def format_md_report(data, owner, repo):
    """格式化 Markdown 报告"""
    now = datetime.now().strftime("%Y-%m-%d")
    ai_tools_str = ", ".join(f"{k}: {v}" for k, v in sorted(data["ai_tools"].items(), key=lambda x: -x[1])) if data["ai_tools"] else "无"

    lines = [
        f"# {owner}/{repo} 深度代码审查",
        f"",
        f"**日期:** {now}",
        f"**技术栈:** {data['lang_str']}",
        f"**文件:** {data['total_files']} | **提交:** {data['total_commits']} | **测试:** {data['test_files']}",
        f"**贡献者:** {data['contrib_str']}",
        f"**AI使用:** {data['ai_count']}/{data['total_commits']} ({data['ai_pct']}%) — {ai_tools_str}",
        "",
        "---",
        "",
        "## 安全漏洞",
        "",
    ]

    if data["vulnerabilities"]:
        for sev, title, detail in data["vulnerabilities"]:
            lines.append(f"### {sev} {title}")
            lines.append(f"- 位置: `{detail}`")
            lines.append("")
    else:
        lines.append("✅ 未发现明显安全漏洞")
        lines.append("")

    lines.extend(["---", "", "## 改进建议", ""])
    if data["improvements"]:
        for sev, title, detail, suggestion in data["improvements"]:
            lines.append(f"### {sev} {title}")
            lines.append(f"- 问题: {detail}")
            lines.append(f"- 建议: {suggestion}")
            lines.append("")
    else:
        lines.append("✅ 代码质量良好")
        lines.append("")

    lines.extend(["---", "", "## 功能推荐", ""])
    if data["features"]:
        for icon, title, desc in data["features"]:
            lines.append(f"- **{icon} {title}** — {desc}")
    else:
        lines.append("暂无推荐")

    lines.extend(["", "---", "", f"## 综合评分: {data['overall']} / 10", ""])
    lines.append("| 维度 | 评分 |")
    lines.append("|------|------|")
    for dim, score in data["scores"].items():
        lines.append(f"| {dim} | {score}/10 |")

    return "\n".join(lines)


def send_tg(bot_token, chat_id, text):
    MAX = 4000
    chunks = []
    cur = ""
    for line in text.split("\n"):
        if len(cur) + len(line) + 1 > MAX:
            chunks.append(cur)
            cur = line
        else:
            cur = f"{cur}\n{line}" if cur else line
    if cur:
        chunks.append(cur)
    for chunk in chunks:
        r = requests.post(f"https://api.telegram.org/bot{bot_token}/sendMessage",
                          json={"chat_id": chat_id, "text": chunk, "parse_mode": "HTML", "disable_web_page_preview": True}, timeout=30)
        if not r.json().get("ok"):
            print(f"  [TG Error] {r.json().get('description')}")
            return False
    return True


def review_repo(token, bot_token, chat_id, owner, repo):
    """审查单个仓库"""
    print(f"[{datetime.now().strftime('%H:%M:%S')}] 审查 {owner}/{repo}...")
    rv = Reviewer(token)
    data = deep_review(rv, owner, repo)

    # 发 TG
    tg_report = format_tg_report(data, owner, repo)
    if send_tg(bot_token, chat_id, tg_report):
        print(f"  ✅ TG 发送成功")
    else:
        print(f"  ❌ TG 发送失败")

    # 保存 MD
    os.makedirs("reports", exist_ok=True)
    date_str = datetime.now().strftime("%Y-%m-%d")
    md_path = f"reports/{owner}_{repo}_review_{date_str}.md"
    md_report = format_md_report(data, owner, repo)
    with open(md_path, "w", encoding="utf-8") as f:
        f.write(md_report)
    print(f"  📄 MD 保存: {md_path}")
    return data


if __name__ == "__main__":
    config = load_config()
    bot_token = config["telegram"]["bot_token"]
    chat_id = "-5184003915"  # Github监控群

    yelangawei_token = config["github"]["token"]

    repos = [
        # AI JAV - 开发
        (yelangawei_token, "yelangawei", "aijav_seo"),
        (yelangawei_token, "yelangawei", "newav-frontend"),
        (yelangawei_token, "yelangawei", "newav-api"),
        (yelangawei_token, "yelangawei", "newav-backend"),
        # INS-AV（运营）
        (yelangawei_token, "yelangawei", "grab_back"),
        (yelangawei_token, "yelangawei", "ins-api"),
        (yelangawei_token, "yelangawei", "ins_front"),
        # ins-禁漫18
        (yelangawei_token, "yelangawei", "18toon"),
        (yelangawei_token, "yelangawei", "multilang-comic-front"),
        # 明顺
        (yelangawei_token, "yelangawei", "backend_mingshun"),
    ]

    print(f"开始深度审查 {len(repos)} 个仓库...\n")
    for token, owner, repo in repos:
        try:
            review_repo(token, bot_token, chat_id, owner, repo)
            print()
        except Exception as e:
            print(f"  ❌ 失败: {e}\n")
