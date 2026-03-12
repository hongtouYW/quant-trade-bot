"""
Changelog Web Server — 展示 Trading SaaS 开发日志
端口 5300 | 自动读取 CHANGELOG.md 并渲染为网页
"""
import os
import re
from flask import Flask, render_template_string
from datetime import datetime

app = Flask(__name__)

CHANGELOG_PATH = os.path.join(os.path.dirname(__file__), 'CHANGELOG.md')

HTML_TEMPLATE = r'''<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Trading SaaS — 开发日志</title>
<style>
:root {
  --bg: #0d1117;
  --card: #161b22;
  --border: #30363d;
  --text: #c9d1d9;
  --text-muted: #8b949e;
  --accent: #58a6ff;
  --green: #3fb950;
  --red: #f85149;
  --orange: #d29922;
  --purple: #bc8cff;
  --cyan: #39d353;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
  background: var(--bg);
  color: var(--text);
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
  line-height: 1.6;
  padding: 0;
}
.header {
  background: linear-gradient(135deg, #1a1f35 0%, #0d1117 100%);
  border-bottom: 1px solid var(--border);
  padding: 32px 0;
  text-align: center;
  position: sticky;
  top: 0;
  z-index: 100;
}
.header h1 {
  font-size: 28px;
  color: #fff;
  margin-bottom: 8px;
}
.header .subtitle {
  color: var(--text-muted);
  font-size: 14px;
}
.header .stats {
  margin-top: 16px;
  display: flex;
  justify-content: center;
  gap: 32px;
}
.header .stat {
  text-align: center;
}
.header .stat-value {
  font-size: 24px;
  font-weight: 700;
  color: var(--accent);
}
.header .stat-label {
  font-size: 12px;
  color: var(--text-muted);
  text-transform: uppercase;
}
.filter-bar {
  background: var(--card);
  border-bottom: 1px solid var(--border);
  padding: 10px 24px;
  display: flex;
  align-items: center;
  gap: 8px;
  position: sticky;
  top: 108px;
  z-index: 100;
}
.filter-bar .filter-label {
  color: var(--text-muted);
  font-size: 13px;
  margin-right: 4px;
}
.filter-bar button {
  background: transparent;
  border: 1px solid var(--border);
  color: var(--text-muted);
  padding: 5px 16px;
  border-radius: 16px;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s;
}
.filter-bar button:hover {
  border-color: var(--accent);
  color: var(--text);
}
.filter-bar button.active {
  background: var(--accent);
  border-color: var(--accent);
  color: #fff;
}
.filter-bar button.active-5111 {
  background: #0e7490;
  border-color: #38bdf8;
  color: #fff;
}
.filter-bar .filter-count {
  font-size: 11px;
  color: var(--text-muted);
  margin-left: auto;
}
.nav-timeline {
  background: var(--card);
  border-bottom: 1px solid var(--border);
  padding: 12px 24px;
  display: flex;
  gap: 8px;
  overflow-x: auto;
  position: sticky;
  top: 150px;
  z-index: 99;
}
.nav-timeline a {
  color: var(--text-muted);
  text-decoration: none;
  padding: 4px 12px;
  border-radius: 16px;
  font-size: 13px;
  white-space: nowrap;
  transition: all 0.2s;
}
.nav-timeline a:hover, .nav-timeline a.active {
  background: var(--accent);
  color: #fff;
}
.entry.filtered-out { display: none; }
.day-section.filtered-out { display: none; }
.container {
  max-width: 960px;
  margin: 0 auto;
  padding: 24px 16px 80px;
}
.day-section {
  margin-bottom: 48px;
  scroll-margin-top: 160px;
}
.day-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 2px solid var(--border);
}
.day-date {
  font-size: 22px;
  font-weight: 700;
  color: #fff;
}
.day-weekday {
  font-size: 14px;
  color: var(--text-muted);
  background: var(--card);
  padding: 2px 10px;
  border-radius: 12px;
}
.day-count {
  margin-left: auto;
  font-size: 13px;
  color: var(--text-muted);
}
.entry {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 8px;
  margin-bottom: 16px;
  overflow: hidden;
  transition: border-color 0.2s;
}
.entry:hover {
  border-color: var(--accent);
}
.entry-header {
  padding: 16px 20px 12px;
  cursor: pointer;
  display: flex;
  align-items: flex-start;
  gap: 12px;
}
.entry-header:hover {
  background: rgba(88, 166, 255, 0.04);
}
.entry-tag {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 4px;
  text-transform: uppercase;
  white-space: nowrap;
  margin-top: 2px;
}
.tag-fix { background: rgba(248, 81, 73, 0.15); color: var(--red); }
.tag-feat { background: rgba(63, 185, 80, 0.15); color: var(--green); }
.tag-maint { background: rgba(210, 153, 34, 0.15); color: var(--orange); }
.tag-perf { background: rgba(188, 140, 255, 0.15); color: var(--purple); }
.tag-5111 { background: rgba(56, 189, 248, 0.15); color: #38bdf8; }
.tag-5111-fix { background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(248, 81, 73, 0.3); }
.tag-5111-feat { background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(63, 185, 80, 0.3); }
.entry-title {
  font-size: 16px;
  font-weight: 600;
  color: #e6edf3;
  flex: 1;
}
.entry-toggle {
  color: var(--text-muted);
  font-size: 18px;
  transition: transform 0.2s;
  user-select: none;
}
.entry.open .entry-toggle {
  transform: rotate(90deg);
}
.entry-body {
  display: none;
  padding: 0 20px 16px;
  font-size: 14px;
  color: var(--text);
}
.entry.open .entry-body {
  display: block;
}
.entry-body ul {
  padding-left: 20px;
  margin: 8px 0;
}
.entry-body li {
  margin: 6px 0;
  line-height: 1.7;
}
.entry-body strong {
  color: #e6edf3;
}
.entry-body code {
  background: rgba(110, 118, 129, 0.2);
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 13px;
  color: var(--cyan);
  font-family: 'SF Mono', Consolas, monospace;
}
.entry-body .field-label {
  color: var(--orange);
  font-weight: 600;
}
.entry-body table {
  width: 100%;
  border-collapse: collapse;
  margin: 12px 0;
  font-size: 13px;
}
.entry-body th {
  background: rgba(110, 118, 129, 0.1);
  padding: 8px 12px;
  text-align: left;
  border-bottom: 1px solid var(--border);
  color: var(--text-muted);
  font-weight: 600;
}
.entry-body td {
  padding: 6px 12px;
  border-bottom: 1px solid rgba(48, 54, 61, 0.5);
}
.entry-files {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid var(--border);
  font-size: 13px;
  color: var(--text-muted);
}
.entry-files code {
  font-size: 12px;
}
.ref-section {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 8px;
  margin-bottom: 16px;
  padding: 20px;
}
.ref-section h2 {
  font-size: 18px;
  color: #fff;
  margin-bottom: 16px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border);
}
.ref-section pre {
  background: var(--bg);
  padding: 16px;
  border-radius: 6px;
  overflow-x: auto;
  font-size: 13px;
  color: var(--cyan);
  line-height: 1.5;
}
.ref-section table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.ref-section th {
  background: rgba(110, 118, 129, 0.1);
  padding: 8px 12px;
  text-align: left;
  border-bottom: 1px solid var(--border);
  color: var(--text-muted);
}
.ref-section td {
  padding: 6px 12px;
  border-bottom: 1px solid rgba(48, 54, 61, 0.5);
}
.footer {
  text-align: center;
  padding: 32px;
  color: var(--text-muted);
  font-size: 13px;
  border-top: 1px solid var(--border);
}
@media (max-width: 640px) {
  .header h1 { font-size: 22px; }
  .header .stats { gap: 16px; }
  .header .stat-value { font-size: 18px; }
  .container { padding: 16px 8px; }
  .entry-header { padding: 12px 14px; }
  .entry-body { padding: 0 14px 12px; }
  .day-date { font-size: 18px; }
}
</style>
</head>
<body>

<div class="header">
  <h1>Trading SaaS + 5111 开发日志</h1>
  <div class="subtitle">量化交易系统 | SaaS（端口80） + Paper Trader（端口5111） | 2026-01-27 上线至今</div>
  <div class="stats">
    <div class="stat">
      <div class="stat-value">{{ total_days }}</div>
      <div class="stat-label">活跃天数</div>
    </div>
    <div class="stat">
      <div class="stat-value">{{ total_entries }}</div>
      <div class="stat-label">更新条目</div>
    </div>
    <div class="stat">
      <div class="stat-value">{{ total_fixes }}</div>
      <div class="stat-label">Bug 修复</div>
    </div>
    <div class="stat">
      <div class="stat-value">{{ total_features }}</div>
      <div class="stat-label">新功能</div>
    </div>
    <div class="stat">
      <div class="stat-value">{{ total_5111 }}</div>
      <div class="stat-label">5111 条目</div>
    </div>
  </div>
</div>

<div class="filter-bar">
  <span class="filter-label">筛选：</span>
  <button class="active" data-filter="all" onclick="filterEntries('all')">全部 <small>({{ total_entries }})</small></button>
  <button data-filter="saas" onclick="filterEntries('saas')">SaaS <small>({{ total_saas }})</small></button>
  <button data-filter="5111" onclick="filterEntries('5111')">5111 <small>({{ total_5111 }})</small></button>
  <span class="filter-count" id="filter-status"></span>
</div>

<div class="nav-timeline">
  {% for date in dates %}
  <a href="#day-{{ date }}">{{ date[5:] }}</a>
  {% endfor %}
</div>

<div class="container">
  {% for day in days %}
  <div class="day-section" id="day-{{ day.date }}">
    <div class="day-header">
      <span class="day-date">{{ day.date }}</span>
      <span class="day-weekday">{{ day.weekday }}</span>
      <span class="day-count">{{ day.entries|length }} 条更新</span>
    </div>
    {% for entry in day.entries %}
    <div class="entry" data-system="{{ entry.system }}" onclick="this.classList.toggle('open')">
      <div class="entry-header">
        <span class="entry-tag tag-{{ entry.tag_class }}">{{ entry.tag }}</span>
        <span class="entry-title">{{ entry.title }}</span>
        <span class="entry-toggle">&#9654;</span>
      </div>
      <div class="entry-body" onclick="event.stopPropagation()">
        {{ entry.body_html }}
      </div>
    </div>
    {% endfor %}
  </div>
  {% endfor %}

  {% for section in ref_sections %}
  <div class="ref-section">
    <h2>{{ section.title }}</h2>
    {{ section.body_html }}
  </div>
  {% endfor %}
</div>

<div class="footer">
  Trading SaaS Changelog | 最后更新: {{ last_update }} | 自动生成自 CHANGELOG.md
</div>

<script>
// Highlight active nav on scroll
const sections = document.querySelectorAll('.day-section');
const navLinks = document.querySelectorAll('.nav-timeline a');
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      navLinks.forEach(l => l.classList.remove('active'));
      const id = e.target.id;
      const link = document.querySelector(`.nav-timeline a[href="#${id}"]`);
      if (link) link.classList.add('active');
    }
  });
}, { rootMargin: '-160px 0px -60% 0px' });
sections.forEach(s => observer.observe(s));

// Expand all on double-click header
document.querySelector('.header').addEventListener('dblclick', () => {
  const entries = document.querySelectorAll('.entry:not(.filtered-out)');
  const allOpen = [...entries].every(e => e.classList.contains('open'));
  entries.forEach(e => {
    if (allOpen) e.classList.remove('open');
    else e.classList.add('open');
  });
});

// Filter entries by system
function filterEntries(filter) {
  // Update active button
  document.querySelectorAll('.filter-bar button').forEach(b => {
    b.classList.remove('active', 'active-5111');
    if (b.dataset.filter === filter) {
      b.classList.add(filter === '5111' ? 'active-5111' : 'active');
    }
  });

  let shown = 0;
  // Filter entries
  document.querySelectorAll('.entry').forEach(el => {
    const sys = el.dataset.system;
    if (filter === 'all' || sys === filter) {
      el.classList.remove('filtered-out');
      shown++;
    } else {
      el.classList.add('filtered-out');
      el.classList.remove('open');
    }
  });

  // Hide day-sections with no visible entries
  document.querySelectorAll('.day-section').forEach(day => {
    const visible = day.querySelectorAll('.entry:not(.filtered-out)').length;
    if (visible === 0) {
      day.classList.add('filtered-out');
    } else {
      day.classList.remove('filtered-out');
      // Update count
      day.querySelector('.day-count').textContent = visible + ' 条更新';
    }
  });

  // Update status
  const total = document.querySelectorAll('.entry').length;
  const status = document.getElementById('filter-status');
  if (filter === 'all') {
    status.textContent = '';
  } else {
    status.textContent = '显示 ' + shown + ' / ' + total + ' 条';
  }
}
</script>
</body>
</html>'''

WEEKDAYS_ZH = ['周一', '周二', '周三', '周四', '周五', '周六', '周日']


def md_inline_to_html(text):
    """Convert inline markdown (bold, code, links) to HTML."""
    # Bold
    text = re.sub(r'\*\*(.+?)\*\*', r'<strong>\1</strong>', text)
    # Inline code
    text = re.sub(r'`(.+?)`', r'<code>\1</code>', text)
    return text


def md_block_to_html(lines):
    """Convert a block of markdown lines to HTML."""
    html_parts = []
    i = 0
    in_list = False
    in_table = False
    in_code = False
    code_lines = []

    while i < len(lines):
        line = lines[i].rstrip()

        # Code block
        if line.startswith('```'):
            if in_code:
                html_parts.append('<pre>' + '\n'.join(code_lines) + '</pre>')
                code_lines = []
                in_code = False
            else:
                if in_list:
                    html_parts.append('</ul>')
                    in_list = False
                in_code = True
            i += 1
            continue

        if in_code:
            code_lines.append(line)
            i += 1
            continue

        # Table
        if '|' in line and line.strip().startswith('|'):
            cells = [c.strip() for c in line.strip().strip('|').split('|')]
            if all(re.match(r'^[-:]+$', c) for c in cells):
                i += 1
                continue
            if not in_table:
                if in_list:
                    html_parts.append('</ul>')
                    in_list = False
                html_parts.append('<table>')
                # First row as header
                html_parts.append('<tr>' + ''.join(f'<th>{md_inline_to_html(c)}</th>' for c in cells) + '</tr>')
                in_table = True
            else:
                html_parts.append('<tr>' + ''.join(f'<td>{md_inline_to_html(c)}</td>' for c in cells) + '</tr>')
            i += 1
            continue
        elif in_table:
            html_parts.append('</table>')
            in_table = False

        # List item
        stripped = line.lstrip()
        if stripped.startswith('- ') or stripped.startswith('* ') or re.match(r'^\d+\.\s', stripped):
            if not in_list:
                html_parts.append('<ul>')
                in_list = True
            content = re.sub(r'^[-*]\s+|\d+\.\s+', '', stripped)
            html_parts.append(f'<li>{md_inline_to_html(content)}</li>')
            i += 1
            continue

        # Sub-list (indented list)
        if line.startswith('  ') and (stripped.startswith('- ') or stripped.startswith('* ')):
            content = re.sub(r'^[-*]\s+', '', stripped)
            html_parts.append(f'<li style="margin-left:20px">{md_inline_to_html(content)}</li>')
            i += 1
            continue

        # Close list if needed
        if in_list and not stripped.startswith('- ') and not stripped.startswith('* '):
            html_parts.append('</ul>')
            in_list = False

        # Empty line
        if not stripped:
            i += 1
            continue

        # Regular paragraph
        html_parts.append(f'<p>{md_inline_to_html(line)}</p>')
        i += 1

    if in_list:
        html_parts.append('</ul>')
    if in_table:
        html_parts.append('</table>')
    if in_code:
        html_parts.append('<pre>' + '\n'.join(code_lines) + '</pre>')

    return '\n'.join(html_parts)


def parse_changelog(md_text):
    """Parse CHANGELOG.md into structured data."""
    lines = md_text.split('\n')
    days = []
    ref_sections = []
    current_day = None
    current_entry = None
    entry_lines = []
    in_ref = False
    ref_title = None
    ref_lines = []

    def flush_entry():
        nonlocal current_entry, entry_lines
        if current_entry and entry_lines:
            current_entry['body_html'] = md_block_to_html(entry_lines)
            entry_lines = []

    def flush_ref():
        nonlocal ref_title, ref_lines
        if ref_title and ref_lines:
            ref_sections.append({
                'title': ref_title,
                'body_html': md_block_to_html(ref_lines),
            })
            ref_lines = []
            ref_title = None

    for line in lines:
        # Day header: ## 2026-03-11
        m = re.match(r'^## (\d{4}-\d{2}-\d{2})', line)
        if m:
            flush_entry()
            flush_ref()
            in_ref = False
            date_str = m.group(1)
            try:
                dt = datetime.strptime(date_str, '%Y-%m-%d')
                weekday = WEEKDAYS_ZH[dt.weekday()]
            except:
                weekday = ''
            current_day = {
                'date': date_str,
                'weekday': weekday,
                'entries': [],
            }
            days.append(current_day)
            continue

        # Reference section header (no date)
        if line.startswith('## ') and not re.match(r'^## \d{4}', line):
            flush_entry()
            flush_ref()
            in_ref = True
            ref_title = line[3:].strip()
            current_day = None
            continue

        if in_ref:
            ref_lines.append(line)
            continue

        # Entry header: ### [修复] or ### [功能]
        m = re.match(r'^### \[(.+?)\]\s*(.+)', line)
        if m and current_day is not None:
            flush_entry()
            tag = m.group(1)
            title = m.group(2)

            if tag.startswith('5111-'):
                sub = tag[5:]
                if '修复' in sub or 'fix' in sub.lower():
                    tag_class = '5111-fix'
                    tag_display = '5111 修复'
                else:
                    tag_class = '5111-feat'
                    tag_display = '5111 功能'
            elif '修复' in tag or 'fix' in tag.lower():
                tag_class = 'fix'
                tag_display = '修复'
            elif '功能' in tag or 'feat' in tag.lower():
                tag_class = 'feat'
                tag_display = '功能'
            elif '维护' in tag or '清理' in tag:
                tag_class = 'maint'
                tag_display = '维护'
            else:
                tag_class = 'perf'
                tag_display = tag

            system = '5111' if tag_class.startswith('5111') else 'saas'
            current_entry = {
                'tag': tag_display,
                'tag_class': tag_class,
                'title': title,
                'body_html': '',
                'system': system,
            }
            current_day['entries'].append(current_entry)
            entry_lines = []
            continue

        # Collect entry body lines
        if current_entry is not None and line.strip() != '---':
            entry_lines.append(line)

    flush_entry()
    flush_ref()
    return days, ref_sections


@app.route('/')
def changelog():
    try:
        with open(CHANGELOG_PATH, 'r', encoding='utf-8') as f:
            md_text = f.read()
    except FileNotFoundError:
        return '<h1>CHANGELOG.md not found</h1>', 404

    days, ref_sections = parse_changelog(md_text)

    total_entries = sum(len(d['entries']) for d in days)
    total_fixes = sum(1 for d in days for e in d['entries'] if e['tag_class'] in ('fix', '5111-fix'))
    total_features = sum(1 for d in days for e in d['entries'] if e['tag_class'] in ('feat', '5111-feat'))
    total_5111 = sum(1 for d in days for e in d['entries'] if e['tag_class'].startswith('5111'))
    total_saas = total_entries - total_5111
    dates = [d['date'] for d in days]

    return render_template_string(
        HTML_TEMPLATE,
        days=days,
        ref_sections=ref_sections,
        dates=dates,
        total_days=len(days),
        total_entries=total_entries,
        total_fixes=total_fixes,
        total_features=total_features,
        total_5111=total_5111,
        total_saas=total_saas,
        last_update=datetime.now().strftime('%Y-%m-%d %H:%M'),
    )


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5300, debug=False)
