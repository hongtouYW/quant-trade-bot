#!/bin/bash
# V4-Lite 部署脚本
# 用法: bash scripts/deploy.sh

set -e

SERVER="trading-server"
REMOTE_DIR="/opt/quant-v4lite"
LOCAL_DIR="$(cd "$(dirname "$0")/.." && pwd)"

echo "=== V4-Lite 部署 ==="
echo "本地: $LOCAL_DIR"
echo "远程: $SERVER:$REMOTE_DIR"
echo ""

# 1. 同步代码 (排除 .env, data, logs, __pycache__)
echo "[1/4] 同步代码..."
rsync -avz --delete \
  --exclude '.env' \
  --exclude 'data/' \
  --exclude 'logs/' \
  --exclude '__pycache__/' \
  --exclude '*.pyc' \
  --exclude '.git/' \
  --exclude '.venv/' \
  "$LOCAL_DIR/" "$SERVER:$REMOTE_DIR/"

# 2. 安装依赖
echo "[2/4] 安装依赖..."
ssh "$SERVER" "cd $REMOTE_DIR && pip3 install -r requirements.txt -q"

# 3. 重启服务
echo "[3/4] 重启服务..."
ssh "$SERVER" "supervisorctl restart v4lite-bot v4lite-web 2>/dev/null || echo '手动重启: supervisorctl restart v4lite-bot v4lite-web'"

# 4. 检查状态
echo "[4/4] 检查状态..."
sleep 2
ssh "$SERVER" "supervisorctl status v4lite-bot v4lite-web 2>/dev/null || echo '请检查 supervisord 配置'"

echo ""
echo "=== 部署完成 ==="
echo "面板: http://139.162.41.38:5210/"
