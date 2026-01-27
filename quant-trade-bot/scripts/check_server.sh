#!/bin/bash

# 服务器健康检查脚本
# 用法: ./check_server.sh [服务器IP] [SSH端口]

SERVER_IP=${1:-"localhost"}
SSH_PORT=${2:-22}
REMOTE_USER="root"

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}=================================${NC}"
echo -e "${GREEN}  服务器健康检查${NC}"
echo -e "${GREEN}=================================${NC}"
echo ""

# 检查SSH连接
echo -e "${YELLOW}[检查] SSH连接...${NC}"
if [ "$SERVER_IP" == "localhost" ]; then
    EXEC_CMD="bash -c"
else
    if ssh -p $SSH_PORT -o ConnectTimeout=5 $REMOTE_USER@$SERVER_IP "exit" 2>/dev/null; then
        echo -e "${GREEN}✓ SSH连接正常${NC}"
        EXEC_CMD="ssh -p $SSH_PORT $REMOTE_USER@$SERVER_IP"
    else
        echo -e "${RED}✗ SSH连接失败${NC}"
        exit 1
    fi
fi

# 检查系统资源
echo -e "\n${YELLOW}[检查] 系统资源...${NC}"
$EXEC_CMD << 'ENDSSH'
    echo "CPU使用率:"
    top -bn1 | grep "Cpu(s)" | awk '{print "  " $2}' || echo "  N/A"
    
    echo "内存使用:"
    free -h | grep Mem | awk '{print "  已用: " $3 " / 总计: " $2 " (" int($3/$2 * 100) "%)"}'
    
    echo "磁盘使用:"
    df -h / | tail -1 | awk '{print "  已用: " $3 " / 总计: " $2 " (" $5 ")"}'
ENDSSH

# 检查服务状态
echo -e "\n${YELLOW}[检查] 服务状态...${NC}"
$EXEC_CMD << 'ENDSSH'
    if command -v supervisorctl &> /dev/null; then
        supervisorctl status 2>/dev/null || echo "  Supervisor未运行"
    else
        echo "  Supervisor未安装"
    fi
ENDSSH

# 检查端口监听
echo -e "\n${YELLOW}[检查] 端口监听...${NC}"
$EXEC_CMD << 'ENDSSH'
    if netstat -tlnp 2>/dev/null | grep -q ":5001"; then
        echo "  ✓ Web服务 (5001) 正在运行"
    else
        echo "  ✗ Web服务 (5001) 未运行"
    fi
    
    if netstat -tlnp 2>/dev/null | grep -q ":80"; then
        echo "  ✓ Nginx (80) 正在运行"
    else
        echo "  ✗ Nginx (80) 未运行"
    fi
ENDSSH

# 检查数据库
echo -e "\n${YELLOW}[检查] 数据库...${NC}"
$EXEC_CMD << 'ENDSSH'
    if [ -f /opt/trading-bot/paper_trading.db ]; then
        echo "  ✓ 数据库文件存在"
        DB_SIZE=$(du -h /opt/trading-bot/paper_trading.db | cut -f1)
        echo "  大小: $DB_SIZE"
        
        # 检查记录数
        TRADES=$(sqlite3 /opt/trading-bot/paper_trading.db "SELECT COUNT(*) FROM trades;" 2>/dev/null || echo "0")
        POSITIONS=$(sqlite3 /opt/trading-bot/paper_trading.db "SELECT COUNT(*) FROM positions WHERE status='open';" 2>/dev/null || echo "0")
        echo "  交易记录: $TRADES 条"
        echo "  持仓数量: $POSITIONS 个"
    else
        echo "  ✗ 数据库文件不存在"
    fi
ENDSSH

# 检查日志
echo -e "\n${YELLOW}[检查] 最近日志...${NC}"
$EXEC_CMD << 'ENDSSH'
    if [ -f /var/log/trading-system.out.log ]; then
        echo "交易系统最近3条日志:"
        tail -3 /var/log/trading-system.out.log | sed 's/^/  /'
    fi
    
    if [ -f /var/log/trading-system.err.log ]; then
        ERROR_COUNT=$(wc -l < /var/log/trading-system.err.log)
        if [ $ERROR_COUNT -gt 0 ]; then
            echo -e "\n${RED}发现 $ERROR_COUNT 条错误日志${NC}"
            tail -3 /var/log/trading-system.err.log | sed 's/^/  /'
        fi
    fi
ENDSSH

# Web访问测试
echo -e "\n${YELLOW}[检查] Web访问...${NC}"
if [ "$SERVER_IP" == "localhost" ]; then
    TEST_URL="http://localhost:5001/api/stats"
else
    TEST_URL="http://$SERVER_IP/api/stats"
fi

if curl -s --connect-timeout 5 "$TEST_URL" > /dev/null 2>&1; then
    echo -e "  ${GREEN}✓ Web API 可访问${NC}"
    echo -e "  URL: ${TEST_URL}"
else
    echo -e "  ${RED}✗ Web API 无法访问${NC}"
fi

echo -e "\n${GREEN}=================================${NC}"
echo -e "${GREEN}  检查完成${NC}"
echo -e "${GREEN}=================================${NC}"
