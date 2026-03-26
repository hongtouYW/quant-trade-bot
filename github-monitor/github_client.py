"""GitHub API 客户端 - 获取仓库活动数据"""

import requests
from datetime import datetime, timedelta
from typing import Optional


class GitHubClient:
    BASE_URL = "https://api.github.com"

    def __init__(self, token: str = ""):
        self.session = requests.Session()
        self.session.headers.update({
            "Accept": "application/vnd.github.v3+json",
            "User-Agent": "GitHub-Monitor-Bot"
        })
        if token:
            self.session.headers["Authorization"] = f"token {token}"

    def _get(self, endpoint: str, params: dict = None):
        url = f"{self.BASE_URL}{endpoint}"
        resp = self.session.get(url, params=params or {}, timeout=30)
        resp.raise_for_status()
        return resp.json()

    def get_commits(self, owner: str, repo: str, since: str = None, until: str = None) -> list:
        """获取仓库提交记录"""
        params = {"per_page": 100}
        if since:
            params["since"] = since
        if until:
            params["until"] = until

        try:
            commits = self._get(f"/repos/{owner}/{repo}/commits", params)
        except requests.exceptions.HTTPError as e:
            if e.response.status_code == 409:  # empty repo
                return []
            raise

        results = []
        for c in commits:
            commit_data = c.get("commit", {})
            author_login = c.get("author", {}).get("login", "") if c.get("author") else ""
            author_name = commit_data.get("author", {}).get("name", "unknown")
            results.append({
                "sha": c["sha"][:7],
                "message": commit_data.get("message", "").split("\n")[0],  # 只取第一行
                "author": author_login or author_name,
                "date": commit_data.get("author", {}).get("date", ""),
                "url": c.get("html_url", ""),
            })
        return results

    def get_releases(self, owner: str, repo: str, since: str = None) -> list:
        """获取发布版本"""
        try:
            releases = self._get(f"/repos/{owner}/{repo}/releases", {"per_page": 20})
        except requests.exceptions.HTTPError:
            return []

        results = []
        for r in releases:
            published = r.get("published_at", "")
            if since and published and published < since:
                continue
            results.append({
                "tag": r.get("tag_name", ""),
                "name": r.get("name", ""),
                "author": r.get("author", {}).get("login", "unknown"),
                "date": published,
                "url": r.get("html_url", ""),
                "body": (r.get("body", "") or "")[:200],
            })
        return results

    def get_events(self, owner: str, repo: str, since: str = None) -> list:
        """获取仓库事件 (push, issues, PRs, etc.)"""
        try:
            events = self._get(f"/repos/{owner}/{repo}/events", {"per_page": 100})
        except requests.exceptions.HTTPError:
            return []

        results = []
        for e in events:
            created = e.get("created_at", "")
            if since and created and created < since:
                continue

            event_type = e.get("type", "")
            actor = e.get("actor", {}).get("login", "unknown")
            payload = e.get("payload", {})

            summary = self._summarize_event(event_type, payload)
            if summary:
                results.append({
                    "type": event_type,
                    "actor": actor,
                    "date": created,
                    "summary": summary,
                })
        return results

    def get_repo_info(self, owner: str, repo: str) -> dict:
        """获取仓库基本信息"""
        try:
            info = self._get(f"/repos/{owner}/{repo}")
            return {
                "full_name": info.get("full_name", ""),
                "description": info.get("description", ""),
                "stars": info.get("stargazers_count", 0),
                "forks": info.get("forks_count", 0),
                "open_issues": info.get("open_issues_count", 0),
                "language": info.get("language", ""),
                "updated_at": info.get("updated_at", ""),
            }
        except requests.exceptions.HTTPError:
            return {}

    def _summarize_event(self, event_type: str, payload: dict) -> str:
        """将事件类型转为可读摘要"""
        summaries = {
            "PushEvent": lambda p: f"推送 {p.get('size', 0)} 个提交到 {p.get('ref', '').replace('refs/heads/', '')}",
            "CreateEvent": lambda p: f"创建 {p.get('ref_type', '')} {p.get('ref', '') or ''}",
            "DeleteEvent": lambda p: f"删除 {p.get('ref_type', '')} {p.get('ref', '')}",
            "IssuesEvent": lambda p: f"{p.get('action', '')} Issue #{p.get('issue', {}).get('number', '')} {p.get('issue', {}).get('title', '')}",
            "PullRequestEvent": lambda p: f"{p.get('action', '')} PR #{p.get('pull_request', {}).get('number', '')} {p.get('pull_request', {}).get('title', '')}",
            "IssueCommentEvent": lambda p: f"评论 Issue #{p.get('issue', {}).get('number', '')}",
            "ReleaseEvent": lambda p: f"{p.get('action', '')} Release {p.get('release', {}).get('tag_name', '')}",
            "ForkEvent": lambda _: "Fork了仓库",
            "WatchEvent": lambda _: "Star了仓库",
        }
        handler = summaries.get(event_type)
        return handler(payload) if handler else ""
