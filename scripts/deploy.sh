#!/bin/bash

# 🚀 交易系统自动化部署脚本
# 用法: ./deploy.sh [服务器IP] [SSH端口]

set -e  # 遇到错误立即退出

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 配置
SERVER_IP=${1:-"请输入服务器IP"}
SSH_PORT=${2:-22}
REMOTE_USER="root"
REMOTE_DIR="/opt/trading-bot"
LOCAL_DIR="$(cd "$(dirname "$0")" && pwd)"

echo -e "${GREEN}=================================${NC}"
echo -e "${GREEN}  交易系统部署脚本 v1.0${NC}"
echo -e "${GREEN}=================================${NC}"

# 检查参数
if [ "$SERVER_IP" == "请输入服务器IP" ]; then
    echo -e "${RED}错误: 请提供服务器IP${NC}"
    echo "用法: ./deploy.sh [服务器IP] [SSH端口]"
    echo "示例: ./deploy.sh 192.168.1.100 22"
    exit 1
fi

echo -e "${YELLOW}服务器IP: ${SERVER_IP}${NC}"
echo -e "${YELLOW}SSH端口: ${SSH_PORT}${NC}"
echo -e "${YELLOW}远程目录: ${REMOTE_DIR}${NC}"
echo ""

# 确认部署
read -p "确认部署到以上服务器？(yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "部署已取消"
    exit 0
fi

# 1. 测试SSH连接
echo -e "\n${GREEN}[1/10] 测试SSH连接...${NC}"
if ssh -p $SSH_PORT -o ConnectTimeout=5 $REMOTE_USER@$SERVER_IP "echo 'SSH连接成功'" 2>/dev/null; then
    echo -e "${GREEN}✓ SSH连接正常${NC}"
else
    echo -e "${RED}✗ SSH连接失败，请检查服务器IP、端口和密钥${NC}"
    exit 1
fi

# 2. 安装系统依赖
echo -e "\n${GREEN}[2/10] 安装系统依赖...${NC}"
ssh -p $SSH_PORT $REMOTE_USER@$SERVER_IP << 'ENDSSH'
    # 检测系统类型
    if [ -f /etc/debian_version ]; then
        echo "检测到 Debian/Ubuntu 系统"
        export DEBIAN_FRONTEND=noninteractive
        apt-get update -qq
        apt-get install -y -qq python3 python3-pip git nginx supervisor sqlite3 curl
    elif [ -f /etc/redhat-release ]; then
        echo "检测到 CentOS/RHEL 系统"
        yum install -y python3 python3-pip git nginx supervisor sqlite curl
    else
        echo "警告: 未知系统类型"
    fi
ENDSSH
echo -e "${GREEN}✓ 系统依赖安装完成${NC}"

# 3. 创建目录
echo -e "\n${GREEN}[3/10] 创建应用目录...${NC}"
ssh -p $SSH_PORT $REMOTE_USER@$SERVER_IP "mkdir -p $REMOTE_DIR/templates $REMOTE_DIR/backups"
echo -e "${GREEN}✓ 目录创建完成${NC}"

# 4. 上传文件
echo -e "\n${GREEN}[4/10] 上传应用文件...${NC}"
rsync -avz --progress -e "ssh -p $SSH_PORT" \
    --exclude='*.db' \
    --exclude='*.log' \
    --exclude='__pycache__' \
    --exclude='.git' \
    --exclude='*.json' \
    --exclude='venv' \
    $LOCAL_DIR/*.py \
    $LOCAL_DIR/requirements.txt \
    $LOCAL_DIR/*.sh \
    $REMOTE_USER@$SERVER_IP:$REMOTE_DIR/

rsync -avz --progress -e "ssh -p $SSH_PORT" \
    $LOCAL_DIR/templates/ \
    $REMOTE_USER@$SERVER_IP:$REMOTE_DIR/templates/

echo -e "${GREEN}✓ 文件上传完成${NC}"

# 5. 配置文件
echo -e "\n${GREEN}[5/10] 配置文件...${NC}"
if [ -f "$LOCAL_DIR/config.json" ]; then
    echo "发现本地 config.json，正在上传..."
    scp -P $SSH_PORT $LOCAL_DIR/config.json $REMOTE_USER@$SERVER_IP:$REMOTE_DIR/
    ssh -p $SSH_PORT $REMOTE_USER@$SERVER_IP "chmod 600 $REMOTE_DIR/config.json"
    echo -e "${GREEN}✓ config.json 已上传并设置权限${NC}"
else
    echo -e "${YELLOW}⚠ 本地未找到 config.json${NC}"
    echo -e "${YELLOW}请手动创建: nano $REMOTE_DIR/config.json${NC}"
fi

# 6. 安装Python依赖
echo -e "\n${GREEN}[6/10] 安装Python依赖...${NC}"
ssh -p $SSH_PORT $REMOTE_USER@$SERVER_IP << ENDSSH
    cd $REMOTE_DIR
    pip3 install -r requirements.txt -q
ENDSSH
echo -e "${GREEN}✓ Python依赖安装完成${NC}"

# 7. 配置Supervisor
echo -e "\n${GREEN}[7/10] 配置进程守护...${NC}"
ssh -p $SSH_PORT $REMOTE_USER@$SERVER_IP << 'ENDSSH'
cat > /etc/supervisor/conf.d/trading-bot.conf << 'EOF'
[program:trading-system]
command=/usr/bin/python3 /opt/trading-bot/integrated_trading_system.py
directory=/opt/trading-bot
user=root
autostart=true
autorestart=true
stderr_logfile=/var/log/trading-system.err.log
stdout_logfile=/var/log/trading-system.out.log
redirect_stderr=true

[program:web-monitor]
command=/usr/bin/python3 /opt/trading-bot/web_monitor.py
directory=/opt/trading-bot
user=root
autostart=true
autorestart=true
stderr_logfile=/var/log/web-monitor.err.log
stdout_logfile=/var/log/web-monitor.out.log
redirect_stderr=true
EOF

    supervisorctl reread
    supervisorctl update
ENDSSH
echo -e "${GREEN}✓ Supervisor配置完成${NC}"

# 8. 配置Nginx
echo -e "\n${GREEN}[8/10] 配置Web服务器...${NC}"
ssh -p $SSH_PORT $REMOTE_USER@$SERVER_IP << 'ENDSSH'
cat > /etc/nginx/sites-available/trading-bot << 'EOF'
server {
    listen 80;
    server_name _;

    location / {
        proxy_pass http://127.0.0.1:5001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_cache_bypass $http_upgrade;
    }
}
EOF

    # 启用配置
    ln -sf /etc/nginx/sites-available/trading-bot /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default
    nginx -t && systemctl restart nginx
ENDSSH
echo -e "${GREEN}✓ Nginx配置完成${NC}"

# 9. 配置防火墙
echo -e "\n${GREEN}[9/10] 配置防火墙...${NC}"
ssh -p $SSH_PORT $REMOTE_USER@$SERVER_IP << 'ENDSSH'
    if command -v ufw &> /dev/null; then
        # Ubuntu UFW
        ufw allow 22/tcp
        ufw allow 80/tcp
        ufw allow 443/tcp
        echo "y" | ufw enable
        echo "UFW防火墙已配置"
    elif command -v firewall-cmd &> /dev/null; then
        # CentOS Firewalld
        firewall-cmd --permanent --add-service=ssh
        firewall-cmd --permanent --add-service=http
        firewall-cmd --permanent --add-service=https
        firewall-cmd --reload
        echo "Firewalld防火墙已配置"
    fi
ENDSSH
echo -e "${GREEN}✓ 防火墙配置完成${NC}"

# 10. 设置定时备份
echo -e "\n${GREEN}[10/10] 设置定时备份...${NC}"
ssh -p $SSH_PORT $REMOTE_USER@$SERVER_IP << 'ENDSSH'
cat > /opt/trading-bot/backup.sh << 'EOF'
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/opt/trading-bot/backups"
mkdir -p $BACKUP_DIR
cp /opt/trading-bot/paper_trading.db $BACKUP_DIR/paper_trading_$DATE.db
find $BACKUP_DIR -name "*.db" -mtime +30 -delete
echo "[$DATE] 数据库备份完成"
EOF

    chmod +x /opt/trading-bot/backup.sh
    
    # 添加到crontab（每天凌晨2点备份）
    (crontab -l 2>/dev/null | grep -v backup.sh; echo "0 2 * * * /opt/trading-bot/backup.sh >> /var/log/backup.log 2>&1") | crontab -
ENDSSH
echo -e "${GREEN}✓ 定时备份设置完成${NC}"

# 启动服务
echo -e "\n${GREEN}启动服务...${NC}"
ssh -p $SSH_PORT $REMOTE_USER@$SERVER_IP << 'ENDSSH'
    supervisorctl start all
    sleep 2
    supervisorctl status
ENDSSH

# 部署完成
echo -e "\n${GREEN}=================================${NC}"
echo -e "${GREEN}  ✓ 部署完成！${NC}"
echo -e "${GREEN}=================================${NC}"
echo ""
echo -e "访问地址:"
echo -e "  ${GREEN}http://${SERVER_IP}${NC}"
echo ""
echo -e "常用命令:"
echo -e "  查看服务状态: ${YELLOW}ssh $REMOTE_USER@$SERVER_IP 'supervisorctl status'${NC}"
echo -e "  查看交易日志: ${YELLOW}ssh $REMOTE_USER@$SERVER_IP 'tail -f /var/log/trading-system.out.log'${NC}"
echo -e "  查看Web日志:  ${YELLOW}ssh $REMOTE_USER@$SERVER_IP 'tail -f /var/log/web-monitor.out.log'${NC}"
echo -e "  重启服务:     ${YELLOW}ssh $REMOTE_USER@$SERVER_IP 'supervisorctl restart all'${NC}"
echo ""
echo -e "${YELLOW}⚠ 重要提醒:${NC}"
echo -e "  1. 如果没有上传 config.json，请手动创建"
echo -e "  2. 配置完成后重启服务: supervisorctl restart all"
echo -e "  3. 查看详细部署文档: DEPLOYMENT_GUIDE.md"
echo ""
