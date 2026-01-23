# 核心服务管理指南

## 📊 核心服务

本项目有2个核心服务需要持续运行：

1. **实盘模拟交易监控** - `integrated_trading_system.py`
   - 监控多个币种（BTC/ETH/XMR/BNB/SOL）
   - 自动执行交易策略
   - 记录所有交易到数据库
   - 发送Telegram通知

2. **XMR定时更新** - `xmr_continuous_notify.py`
   - 每5分钟更新XMR价格
   - 计算盈亏和ROI
   - 发送Telegram报警

## 🚀 快速启动

### 一键启动所有核心服务
```bash
./scripts/start_core_services.sh
```

### 检查服务状态
```bash
./scripts/check_core_services.sh
```

### 停止所有服务
```bash
./scripts/stop_core_services.sh
```

### 重启所有服务
```bash
./scripts/restart_core_services.sh
```

## 🔄 自动保活

### 方式1: 使用crontab（推荐）
每5分钟自动检查并重启崩溃的服务：

```bash
# 安装crontab任务
crontab -e

# 添加以下行（或使用配置文件）
*/5 * * * * /Users/hongtou/newproject/quant-trade-bot/scripts/keep_alive.sh

# 或者直接导入配置
crontab config/crontab_core_services.txt
```

### 方式2: 手动运行保活脚本
```bash
./scripts/keep_alive.sh
```

## 📋 服务详情

### 实盘模拟交易监控
- **文件**: `src/core/integrated_trading_system.py`
- **PID文件**: `logs/trading_system.pid`
- **日志文件**: `logs/trading_system.log`
- **数据库**: `data/db/paper_trading.db`
- **功能**:
  - 每5分钟扫描交易机会
  - 每30秒检查持仓状态
  - 自动止损止盈
  - 记录所有交易

### XMR定时更新
- **文件**: `xmr_monitor/xmr_continuous_notify.py`
- **PID文件**: `xmr_monitor/xmr_monitor.pid`
- **日志文件**: `xmr_monitor/logs/xmr_monitor.log`
- **功能**:
  - 每5分钟更新价格
  - 计算杠杆盈亏
  - Telegram实时通知

## 📊 监控

### 查看实时日志
```bash
# 实盘模拟交易日志
tail -f logs/trading_system.log

# XMR监控日志
tail -f xmr_monitor/logs/xmr_monitor.log

# 保活脚本日志
tail -f logs/keep_alive.log
```

### 查看进程状态
```bash
# 查看所有Python进程
ps aux | grep python

# 查看特定服务
ps aux | grep integrated_trading_system
ps aux | grep xmr_continuous_notify
```

## 🛡️ 容错机制

### 自动重启
- `keep_alive.sh` 每5分钟检查一次服务状态
- 如果服务崩溃，自动重启
- 所有操作记录在日志中

### 网络容错
- 所有服务内置网络重试机制
- 网络断开时自动等待恢复
- 恢复后继续正常运行

### 异常处理
- 完整的异常捕获和日志记录
- 不会因为单个错误而整体崩溃
- 自动跳过错误继续运行

## 🔧 故障排查

### 服务无法启动
```bash
# 1. 检查端口占用
lsof -i :5001  # 如果Web监控也需要运行

# 2. 检查Python依赖
pip3 install -r requirements.txt

# 3. 检查配置文件
cat config/config.json

# 4. 查看错误日志
tail -100 logs/trading_system.log
tail -100 xmr_monitor/logs/xmr_monitor.log
```

### 服务频繁重启
```bash
# 查看保活日志
tail -f logs/keep_alive.log

# 检查系统资源
top
df -h  # 磁盘空间
free -h  # 内存（Linux）
```

### 清理旧进程
```bash
# 停止所有服务
./scripts/stop_core_services.sh

# 强制清理
pkill -f integrated_trading_system
pkill -f xmr_continuous_notify

# 清理PID文件
rm -f logs/trading_system.pid
rm -f xmr_monitor/xmr_monitor.pid
```

## 📝 日志管理

### 日志轮换（可选）
```bash
# 创建日志轮换配置
cat > /etc/logrotate.d/quant-trading << EOF
/Users/hongtou/newproject/quant-trade-bot/logs/*.log
/Users/hongtou/newproject/quant-trade-bot/xmr_monitor/logs/*.log {
    daily
    rotate 7
    compress
    missingok
    notifempty
    create 0644 hongtou staff
}
EOF
```

### 手动清理日志
```bash
# 保留最近100行
tail -100 logs/trading_system.log > logs/trading_system.log.tmp
mv logs/trading_system.log.tmp logs/trading_system.log

# 删除旧日志
find logs/ -name "*.log" -mtime +7 -delete
```

## 🎯 最佳实践

1. **定期检查**
   - 每天至少检查一次服务状态
   - 查看日志是否有异常

2. **定期重启**
   - 建议每周重启一次服务
   - 清理日志和临时文件

3. **备份配置**
   - 定期备份 `config/config.json`
   - 备份数据库文件

4. **监控资源**
   - 注意磁盘空间
   - 注意内存使用
   - 注意网络连接

## 🆘 紧急停止

如果需要立即停止所有服务：

```bash
# 方式1: 使用脚本
./scripts/stop_core_services.sh

# 方式2: 直接Kill
pkill -f python3

# 方式3: 重启电脑
sudo reboot
```

## 📞 支持

如果遇到问题：
1. 查看日志文件
2. 检查网络连接
3. 验证配置文件
4. 重启服务
5. 查看文档：`docs/`目录

## ⚙️ 高级配置

### 修改检查间隔
编辑 `scripts/keep_alive.sh`，修改crontab时间：
```bash
# 每1分钟检查（更频繁）
* * * * * /path/to/keep_alive.sh

# 每10分钟检查（较少）
*/10 * * * * /path/to/keep_alive.sh
```

### 添加更多服务
在 `scripts/keep_alive.sh` 中添加：
```bash
check_and_restart \
    "新服务名称" \
    "$NEW_PID_FILE" \
    "启动命令"
```

## 🔐 安全建议

1. 定期更改API密钥
2. 不要暴露配置文件
3. 限制服务器访问权限
4. 使用防火墙保护端口
5. 定期更新依赖包
