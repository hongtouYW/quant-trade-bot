# ✅ 部署准备清单

## 📋 需要准备的资料

### 1️⃣ 服务器信息
- [ ] 服务器IP地址: ___________________
- [ ] SSH端口: ___________________（默认22）
- [ ] SSH登录方式: 
  - [ ] 密码登录
  - [ ] SSH密钥登录（推荐）
- [ ] 系统类型: Ubuntu / CentOS / 其他 ___________________

### 2️⃣ API密钥（必需）

#### Binance API
- [ ] API Key: ___________________
- [ ] API Secret: ___________________
- [ ] 获取地址: https://www.binance.com/zh-CN/my/settings/api-management

#### Telegram Bot
- [ ] Bot Token: ___________________
- [ ] Chat ID: ___________________
- [ ] 创建Bot: 与 @BotFather 对话
- [ ] 获取Chat ID: 与 @userinfobot 对话

### 3️⃣ 域名配置（可选）
- [ ] 域名: ___________________
- [ ] DNS已解析到服务器IP
- [ ] 需要SSL证书: 是 / 否

### 4️⃣ 本地准备
- [ ] config.json 已创建（复制 config.json.example 并填写）
- [ ] SSH密钥已配置（如使用密钥登录）
- [ ] 部署脚本已下载

---

## 🚀 快速部署流程

### 方式1: 一键自动部署（推荐）

```bash
# 1. 编辑配置文件
cp server_config_template.json config.json
nano config.json  # 填写你的API密钥

# 2. 执行自动部署
./deploy.sh 你的服务器IP 22

# 3. 等待部署完成（约3-5分钟）

# 4. 访问Web界面
open http://你的服务器IP
```

### 方式2: 手动部署

详细步骤请查看 [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)

---

## 🔍 部署后检查

### 1. 检查服务状态
```bash
./check_server.sh 你的服务器IP 22
```

### 2. 查看运行日志
```bash
ssh root@你的服务器IP
tail -f /var/log/trading-system.out.log
```

### 3. 访问Web界面
- 本地访问: http://你的服务器IP
- 公网访问: http://你的域名（如已配置）

### 4. 测试Telegram通知
系统会在交易时自动发送通知到你的Telegram

---

## 📊 常用管理命令

### SSH连接服务器
```bash
ssh root@你的服务器IP
```

### 查看服务状态
```bash
supervisorctl status
```

### 重启服务
```bash
# 重启交易系统
supervisorctl restart trading-system

# 重启Web服务
supervisorctl restart web-monitor

# 重启所有
supervisorctl restart all
```

### 查看日志
```bash
# 交易系统日志
tail -f /var/log/trading-system.out.log

# Web服务日志
tail -f /var/log/web-monitor.out.log

# 错误日志
tail -f /var/log/trading-system.err.log
```

### 查看数据库
```bash
cd /opt/trading-bot
sqlite3 paper_trading.db

# 在SQLite命令行中：
SELECT * FROM trades ORDER BY timestamp DESC LIMIT 10;
SELECT * FROM positions WHERE status='open';
.exit
```

### 手动备份
```bash
/opt/trading-bot/backup.sh
```

### 更新代码
```bash
# 在本地执行：
./deploy.sh 你的服务器IP 22

# 或手动上传：
scp enhanced_paper_trading.py root@你的服务器IP:/opt/trading-bot/
ssh root@你的服务器IP 'supervisorctl restart all'
```

---

## ⚠️ 注意事项

### 安全
- [ ] config.json 设置了600权限（只有root可读）
- [ ] 修改了SSH默认端口（推荐）
- [ ] 配置了防火墙规则
- [ ] 不要泄露API密钥到GitHub或其他公开地方

### 监控
- [ ] 定期查看交易日志
- [ ] 监控服务器资源（CPU/内存/磁盘）
- [ ] 检查Telegram通知是否正常
- [ ] 查看每日交易报表

### 备份
- [ ] 数据库每天自动备份（凌晨2点）
- [ ] 备份保留30天
- [ ] 定期下载备份到本地

### 维护
- [ ] 定期更新系统: `apt update && apt upgrade`
- [ ] 检查日志文件大小
- [ ] 监控数据库大小
- [ ] 测试备份恢复

---

## 🆘 常见问题

### Q: 部署后访问不了Web界面？
**A: 检查步骤：**
1. 检查防火墙是否开放80端口：`ufw status`
2. 检查Nginx是否运行：`systemctl status nginx`
3. 检查Web服务是否运行：`supervisorctl status web-monitor`
4. 查看错误日志：`tail -f /var/log/web-monitor.err.log`

### Q: Telegram通知收不到？
**A: 检查步骤：**
1. 验证Bot Token和Chat ID是否正确
2. 手动测试发送：
```bash
curl -X POST "https://api.telegram.org/bot你的BOT_TOKEN/sendMessage" \
     -d "chat_id=你的CHAT_ID&text=测试消息"
```
3. 查看错误日志

### Q: 数据库错误？
**A: 检查步骤：**
1. 检查数据库文件是否存在：`ls -la /opt/trading-bot/paper_trading.db`
2. 检查权限：`chmod 644 /opt/trading-bot/paper_trading.db`
3. 尝试手动连接：`sqlite3 /opt/trading-bot/paper_trading.db`

### Q: 如何更新API密钥？
**A: 步骤：**
```bash
ssh root@你的服务器IP
nano /opt/trading-bot/config.json
# 修改API密钥
supervisorctl restart all
```

### Q: 服务器重启后系统会自动运行吗？
**A:** 会的！Supervisor配置了autostart=true，服务器重启后会自动启动所有服务。

### Q: 如何查看账户余额？
**A:** 访问Web界面或查看数据库：
```bash
sqlite3 /opt/trading-bot/paper_trading.db \
  "SELECT balance FROM stats ORDER BY timestamp DESC LIMIT 1;"
```

---

## 📞 获取帮助

- 详细文档: [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)
- 健康检查: `./check_server.sh 服务器IP`
- 查看日志定位问题

---

## 🎯 部署成功标志

全部完成后，你应该能够：
- ✅ 通过浏览器访问Web监控界面
- ✅ 看到实时的交易数据和持仓信息
- ✅ 收到Telegram交易通知
- ✅ 每天凌晨1点收到日报
- ✅ 系统自动交易并记录到数据库

**祝部署成功！🎉**
