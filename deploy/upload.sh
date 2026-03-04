#!/bin/bash
# ═══════════════════════════════════════════════════════
# Upload Trading SaaS code to server
# Run this from your Mac
# Usage: ./deploy/upload.sh [server_ip]
# ═══════════════════════════════════════════════════════
set -e

SERVER=${1:-139.162.41.38}
REMOTE_DIR="/opt/trading-saas"
LOCAL_DIR="$(cd "$(dirname "$0")/.." && pwd)"

echo "Uploading Trading SaaS to ${SERVER}:${REMOTE_DIR}..."
echo "Local: ${LOCAL_DIR}"

# Create remote directory
ssh root@${SERVER} "mkdir -p ${REMOTE_DIR}/logs"

# Upload code (exclude dev files)
rsync -avz --delete \
    --exclude='__pycache__' \
    --exclude='*.pyc' \
    --exclude='.git' \
    --exclude='trading_saas.db' \
    --exclude='venv' \
    --exclude='.env' \
    --exclude='node_modules' \
    --exclude='.DS_Store' \
    "${LOCAL_DIR}/" "root@${SERVER}:${REMOTE_DIR}/"

echo ""
echo "✓ Code uploaded successfully!"
echo ""
echo "Next steps on the server:"
echo "  1. ssh root@${SERVER}"
echo "  2. bash /opt/trading-saas/deploy/setup-server.sh"
echo ""
