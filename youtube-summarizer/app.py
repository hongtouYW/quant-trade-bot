#!/usr/bin/env python3
"""YouTube Video Summarizer - 提取YouTube视频内容并生成重点总结"""

import os
import re
import json
import time
import threading
from datetime import datetime
from pathlib import Path

from dotenv import load_dotenv
from flask import Flask, render_template, request, jsonify, send_from_directory
import anthropic
import markdown

BASE_DIR = Path(__file__).parent
load_dotenv(BASE_DIR / '.env')

app = Flask(__name__)
app.config['SECRET_KEY'] = 'yt-summarizer-2026'

TEMP_DIR = BASE_DIR / 'temp'
SUMMARIES_DIR = BASE_DIR / 'summaries'

TEMP_DIR.mkdir(exist_ok=True)
SUMMARIES_DIR.mkdir(exist_ok=True)

# In-memory task tracking
tasks = {}  # video_id -> {status, progress, result, error}

# Whisper model (lazy loaded)
_whisper_model = None


def get_whisper_model():
    global _whisper_model
    if _whisper_model is None:
        import whisper
        _whisper_model = whisper.load_model("base")
    return _whisper_model


def get_video_id(url):
    """Extract video ID from YouTube URL."""
    patterns = [
        r'(?:v=|/v/|youtu\.be/)([a-zA-Z0-9_-]{11})',
        r'(?:embed/)([a-zA-Z0-9_-]{11})',
        r'(?:shorts/)([a-zA-Z0-9_-]{11})',
    ]
    for pattern in patterns:
        match = re.search(pattern, url)
        if match:
            return match.group(1)
    return None


def get_video_info_and_audio(url, video_id, update_progress):
    """Get video info, try captions, download audio if needed. Returns (video_info, transcript_or_audio_path)."""
    from pytubefix import YouTube

    update_progress("正在获取视频信息...")
    yt = YouTube(url)

    video_info = {
        'title': yt.title or '未知标题',
        'duration': yt.length or 0,
        'channel': yt.author or '未知频道',
        'upload_date': '',
        'description': (yt.description or '')[:500],
        'thumbnail': yt.thumbnail_url or '',
    }

    # Try to get publish date
    try:
        if yt.publish_date:
            video_info['upload_date'] = yt.publish_date.strftime('%Y-%m-%d')
    except Exception:
        pass

    # Try captions
    update_progress("正在检查字幕...")
    transcript = None
    if yt.captions:
        # Priority: zh > zh-Hans > zh-Hant > en > a.zh > a.en
        for lang_key in ['zh', 'zh-Hans', 'zh-Hant', 'zh-CN', 'zh-TW', 'a.zh', 'en', 'a.en']:
            try:
                cap = yt.captions.get(lang_key)
                if cap:
                    srt_text = cap.generate_srt_captions()
                    transcript = parse_srt_text(srt_text)
                    if transcript and len(transcript.strip()) > 50:
                        update_progress(f"获取到字幕 ({lang_key})")
                        return video_info, transcript, 'caption'
            except Exception:
                continue

    # No captions - download audio
    update_progress("无字幕，正在下载音频...")
    audio_path = TEMP_DIR / f"{video_id}.mp4"

    stream = yt.streams.filter(only_audio=True).order_by('abr').desc().first()
    if not stream:
        stream = yt.streams.filter(only_audio=True).first()
    if not stream:
        raise Exception("无法找到音频流")

    stream.download(output_path=str(TEMP_DIR), filename=f"{video_id}.mp4")

    if not audio_path.exists():
        # Try to find the actual downloaded file
        for f in TEMP_DIR.glob(f"{video_id}*"):
            if f.suffix in ('.mp4', '.m4a', '.webm', '.mp3'):
                audio_path = f
                break

    if not audio_path.exists():
        raise Exception("音频下载失败")

    return video_info, str(audio_path), 'audio'


def parse_srt_text(srt_content):
    """Parse SRT subtitle content to plain text."""
    lines = []
    for line in srt_content.split('\n'):
        line = line.strip()
        if not line or line.isdigit() or '-->' in line:
            continue
        line = re.sub(r'<[^>]+>', '', line)
        if line and line not in lines[-1:]:
            lines.append(line)
    return ' '.join(lines)


def transcribe_audio(audio_path, update_progress):
    """Transcribe audio file using Whisper."""
    update_progress("正在用 Whisper 转录音频（首次加载模型可能较慢）...")
    model = get_whisper_model()
    update_progress("正在转录音频中...")
    result = model.transcribe(audio_path, language="zh")
    return result.get('text', '')


def summarize_with_claude(text, video_info):
    """Use Claude API to summarize the transcript."""
    client = anthropic.Anthropic()

    prompt = f"""你是一个专业的视频内容总结助手。请根据以下YouTube视频的转录文本，生成一份结构清晰的重点总结。

## 视频信息
- 标题: {video_info.get('title', '未知')}
- 频道: {video_info.get('channel', '未知')}

## 转录文本
{text[:50000]}

## 要求
请用中文输出，格式要求：
1. **一句话总结** - 用一句话概括视频核心内容
2. **关键重点** - 列出视频中的主要观点/信息（用编号列表）
3. **详细笔记** - 按视频内容顺序，分段总结每个主题
4. **金句/重要数据** - 提取视频中值得记住的关键语句或数据

请确保总结准确、简洁、有条理，便于快速回顾视频内容。"""

    response = client.messages.create(
        model="claude-sonnet-4-20250514",
        max_tokens=4096,
        messages=[{"role": "user", "content": prompt}]
    )

    return response.content[0].text


def process_video(url, video_id):
    """Main processing pipeline for a video."""
    def update_progress(msg):
        tasks[video_id]['progress'] = msg

    try:
        tasks[video_id] = {'status': 'processing', 'progress': '开始处理...', 'result': None, 'error': None}

        # 1. Get video info + captions or audio
        video_info, content, content_type = get_video_info_and_audio(url, video_id, update_progress)
        tasks[video_id]['video_info'] = video_info
        update_progress(f"视频: {video_info['title'][:40]}...")

        # 2. Get transcript
        if content_type == 'caption':
            transcript = content
        else:
            # content is audio file path
            transcript = transcribe_audio(content, update_progress)
            # Clean up audio file
            try:
                Path(content).unlink()
            except Exception:
                pass

        if not transcript or len(transcript.strip()) < 30:
            raise Exception("无法提取视频内容（字幕和转录均失败）")

        # 3. Summarize with Claude
        update_progress("正在用 AI 生成重点总结...")
        summary = summarize_with_claude(transcript, video_info)

        # 4. Save result
        result = {
            'video_id': video_id,
            'url': url,
            'video_info': video_info,
            'transcript_length': len(transcript),
            'summary': summary,
            'summary_html': markdown.markdown(summary, extensions=['tables', 'fenced_code']),
            'created_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
        }

        save_path = SUMMARIES_DIR / f"{video_id}.json"
        with open(save_path, 'w', encoding='utf-8') as f:
            json.dump(result, f, ensure_ascii=False, indent=2)

        md_path = SUMMARIES_DIR / f"{video_id}.md"
        with open(md_path, 'w', encoding='utf-8') as f:
            f.write(f"# {video_info['title']}\n\n")
            f.write(f"> 频道: {video_info['channel']} | 日期: {video_info.get('upload_date', '')} | [视频链接]({url})\n\n")
            f.write(summary)

        tasks[video_id] = {'status': 'done', 'progress': '完成', 'result': result, 'error': None}

    except Exception as e:
        tasks[video_id] = {'status': 'error', 'progress': '', 'result': None, 'error': str(e)}

    finally:
        # Clean up temp files
        for f in TEMP_DIR.glob(f"{video_id}*"):
            try:
                f.unlink()
            except Exception:
                pass


# ============ Routes ============

@app.route('/')
def index():
    history = []
    for f in sorted(SUMMARIES_DIR.glob('*.json'), key=lambda x: x.stat().st_mtime, reverse=True):
        try:
            with open(f, 'r', encoding='utf-8') as fh:
                data = json.load(fh)
                history.append({
                    'video_id': data['video_id'],
                    'title': data['video_info']['title'],
                    'channel': data['video_info']['channel'],
                    'thumbnail': data['video_info'].get('thumbnail', ''),
                    'created_at': data['created_at'],
                    'url': data['url'],
                })
        except Exception:
            continue
    return render_template('index.html', history=history)


@app.route('/api/summarize', methods=['POST'])
def api_summarize():
    data = request.get_json()
    url = data.get('url', '').strip()

    if not url:
        return jsonify({'error': '请输入YouTube链接'}), 400

    video_id = get_video_id(url)
    if not video_id:
        return jsonify({'error': '无效的YouTube链接'}), 400

    # Check if already processed
    save_path = SUMMARIES_DIR / f"{video_id}.json"
    if save_path.exists() and not data.get('force'):
        with open(save_path, 'r', encoding='utf-8') as f:
            result = json.load(f)
        return jsonify({'status': 'done', 'video_id': video_id, 'result': result})

    # Check if already processing
    if video_id in tasks and tasks[video_id]['status'] == 'processing':
        return jsonify({'status': 'processing', 'video_id': video_id, 'progress': tasks[video_id]['progress']})

    # Start processing in background
    thread = threading.Thread(target=process_video, args=(url, video_id))
    thread.daemon = True
    thread.start()

    return jsonify({'status': 'processing', 'video_id': video_id, 'progress': '开始处理...'})


@app.route('/api/status/<video_id>')
def api_status(video_id):
    if video_id not in tasks:
        save_path = SUMMARIES_DIR / f"{video_id}.json"
        if save_path.exists():
            with open(save_path, 'r', encoding='utf-8') as f:
                result = json.load(f)
            return jsonify({'status': 'done', 'result': result})
        return jsonify({'status': 'not_found'}), 404

    task = tasks[video_id]
    response = {'status': task['status'], 'progress': task['progress']}
    if task['status'] == 'done':
        response['result'] = task['result']
    elif task['status'] == 'error':
        response['error'] = task['error']
    return jsonify(response)


@app.route('/api/history')
def api_history():
    history = []
    for f in sorted(SUMMARIES_DIR.glob('*.json'), key=lambda x: x.stat().st_mtime, reverse=True):
        try:
            with open(f, 'r', encoding='utf-8') as fh:
                data = json.load(fh)
                history.append({
                    'video_id': data['video_id'],
                    'title': data['video_info']['title'],
                    'channel': data['video_info']['channel'],
                    'thumbnail': data['video_info'].get('thumbnail', ''),
                    'created_at': data['created_at'],
                    'url': data['url'],
                })
        except Exception:
            continue
    return jsonify(history)


@app.route('/api/delete/<video_id>', methods=['DELETE'])
def api_delete(video_id):
    for ext in ['json', 'md']:
        path = SUMMARIES_DIR / f"{video_id}.{ext}"
        if path.exists():
            path.unlink()
    if video_id in tasks:
        del tasks[video_id]
    return jsonify({'ok': True})


@app.route('/summary/<video_id>')
def view_summary(video_id):
    save_path = SUMMARIES_DIR / f"{video_id}.json"
    if not save_path.exists():
        return "未找到该视频的总结", 404
    with open(save_path, 'r', encoding='utf-8') as f:
        result = json.load(f)
    return render_template('index.html', history=[], view_result=result)


@app.route('/md/<video_id>')
def download_md(video_id):
    md_path = SUMMARIES_DIR / f"{video_id}.md"
    if not md_path.exists():
        return "未找到", 404
    return send_from_directory(SUMMARIES_DIR, f"{video_id}.md",
                               mimetype='text/markdown',
                               as_attachment=True,
                               download_name=f"{video_id}.md")


@app.route('/api/settings', methods=['GET', 'POST'])
def api_settings():
    env_path = BASE_DIR / '.env'
    if request.method == 'GET':
        key = os.environ.get('ANTHROPIC_API_KEY', '')
        masked = key[:8] + '...' + key[-4:] if len(key) > 12 else ('已设置' if key else '未设置')
        return jsonify({'api_key_status': masked, 'has_key': bool(key)})

    data = request.get_json()
    api_key = data.get('api_key', '').strip()
    if not api_key:
        return jsonify({'error': '请输入 API Key'}), 400

    # Save to .env file
    env_content = f"ANTHROPIC_API_KEY={api_key}\n"
    with open(env_path, 'w') as f:
        f.write(env_content)
    os.environ['ANTHROPIC_API_KEY'] = api_key
    return jsonify({'ok': True, 'message': 'API Key 已保存'})


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5050, debug=True)
