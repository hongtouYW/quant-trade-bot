# 📦 部署到线上服务器指南

## 🖥️ 服务器要求

### 最低配置
- **CPU**: 2核心
- **内存**: 2GB RAM
- **存储**: 20GB SSD
- **系统**: Ubuntu 20.04 LTS 或 CentOS 7+
- **网络**: 稳定的互联网连接

### 推荐配置
- **CPU**: 4核心
- **内存**: 4GB RAM
- **存储**: 40GB SSD
- **系统**: Ubuntu 22.04 LTS

## 📋 需要准备的资料

### 1. 服务器信息
```bash
# 记录下来：
服务器IP: _________________
SSH端口: 22 (或自定义)
用户名: _________________
密码/SSH密钥: _________________
```

### 2. API密钥（必需）
```json
{
  "binance": {
    "api_key": "你的Binance API Key",
    "api_secret": "你的Binance API Secret"
  },
  "telegram": {
    "bot_token": "你的Telegram Bot Token",
    "chat_id": "你的Telegram Chat ID"
  }
}
```

### 3. 域名（可选）
如果需要通过域名访问Web面板：
- 域名: example.com
- SSL证书（Let's Encrypt免费）

### 4. 需要的文件
准备好以下文件传到服务器：
```
quant-trade-bot/
├── config.json              # API配置（⚠️ 不要泄露）
├── *.py                     # 所有Python文件
├── templates/               # Web模板
├── requirements.txt         # Python依赖
└── *.sh                     # 启动脚本
```

## 🚀 部署步骤

### 步骤1: 连接服务器
```bash
# SSH连接
ssh root@你的服务器IP

# 或使用SSH密钥
ssh -i ~/.ssh/your_key.pem root@你的服务器IP
```

### 步骤2: 安装基础环境
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y python3 python3-pip git nginx supervisor sqlite3

# CentOS
sudo yum install -y python3 python3-pip git nginx supervisor sqlite
```

### 步骤3: 创建工作目录
```bash
# 创建应用目录
sudo mkdir -p /opt/trading-bot
cd /opt/trading-bot
```

### 步骤4: 上传文件
```bash
# 方式1: 使用 scp 从本地上传
# 在本地执行：
scp -r /Users/hongtou/newproject/quant-trade-bot/* root@服务器IP:/opt/trading-bot/

# 方式2: 使用 Git
git clone your-repository-url /opt/trading-bot

# 方式3: 使用 rsync（推荐）
rsync -avz --exclude='*.db' --exclude='*.log' \
  /Users/hongtou/newproject/quant-trade-bot/ \
  root@服务器IP:/opt/trading-bot/
```

### 步骤5: 安装Python依赖
```bash
cd /opt/trading-bot
pip3 install -r requirements.txt
```

### 步骤6: 配置文件
```bash
# 创建config.json（⚠️ 重要）
nano /opt/trading-bot/config.json

# 粘贴配置：
{
  "binance": {
    "api_key": "你的API Key",
    "api_secret": "你的API Secret"
  },
  "telegram": {
    "bot_token": "你的Bot Token",
    "chat_id": "你的Chat ID"
  }
}

# 设置权限（只有root能读）
chmod 600 /opt/trading-bot/config.json
```

### 步骤7: 测试运行
```bash
# 测试交易系统
cd /opt/trading-bot
python3 test_system.py

# 测试Web服务
python3 web_monitor.py
# 访问 http://服务器IP:5001 测试
```

### 步骤8: 配置进程守护
使用Supervisor保持程序运行

```bash
# 创建配置文件
sudo nano /etc/supervisor/conf.d/trading-bot.conf
```

粘贴以下内容：
```ini
[program:trading-system]
command=/usr/bin/python3 /opt/trading-bot/integrated_trading_system.py
directory=/opt/trading-bot
user=root
autostart=true
autorestart=true
stderr_logfile=/var/log/trading-system.err.log
stdout_logfile=/var/log/trading-system.out.log

[program:web-monitor]
command=/usr/bin/python3 /opt/trading-bot/web_monitor.py
directory=/opt/trading-bot
user=root
autostart=true
autorestart=true
stderr_logfile=/var/log/web-monitor.err.log
stdout_logfile=/var/log/web-monitor.out.log
```

启动服务：
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all

# 查看状态
sudo supervisorctl status
```

### 步骤9: 配置Nginx反向代理（可选）
```bash
sudo nano /etc/nginx/sites-available/trading-bot
```

粘贴：
```nginx
server {
    listen 80;
    server_name 你的域名或IP;

    location / {
        proxy_pass http://127.0.0.1:5001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
}
```

启用配置：
```bash
sudo ln -s /etc/nginx/sites-available/trading-bot /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### 步骤10: 配置SSL（推荐）
```bash
# 安装certbot
sudo apt install certbot python3-certbot-nginx

# 获取证书
sudo certbot --nginx -d 你的域名

# 自动续期
sudo certbot renew --dry-run
```

## 🔒 安全配置

### 1. 防火墙设置
```bash
# Ubuntu UFW
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable

# CentOS Firewalld
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

### 2. 修改SSH端口（推荐）
```bash
sudo nano /etc/ssh/sshd_config
# 修改 Port 22 为其他端口，如 2222
sudo systemctl restart sshd
```

### 3. 配置文件加密
```bash
# config.json 权限设置
chmod 600 /opt/trading-bot/config.json
chown root:root /opt/trading-bot/config.json
```

### 4. 日志轮转
```bash
sudo nano /etc/logrotate.d/trading-bot
```

内容：
```
/var/log/trading-*.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    create 0640 root root
}
```

## 📊 监控和维护

### 查看日志
```bash
# 交易系统日志
tail -f /var/log/trading-system.out.log

# Web服务日志
tail -f /var/log/web-monitor.out.log

# 错误日志
tail -f /var/log/trading-system.err.log
```

### 重启服务
```bash
# 重启交易系统
sudo supervisorctl restart trading-system

# 重启Web服务
sudo supervisorctl restart web-monitor

# 重启所有
sudo supervisorctl restart all
```

### 查看运行状态
```bash
# Supervisor状态
sudo supervisorctl status

# 进程状态
ps aux | grep python

# 端口监听
netstat -tlnp | grep 5001
```

### 数据库备份
```bash
# 创建备份脚本
nano /opt/trading-bot/backup.sh
```

内容：
```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/opt/trading-bot/backups"
mkdir -p $BACKUP_DIR

# 备份数据库
cp /opt/trading-bot/paper_trading.db $BACKUP_DIR/paper_trading_$DATE.db

# 保留最近30天的备份
find $BACKUP_DIR -name "*.db" -mtime +30 -delete

echo "Backup completed: $DATE"
```

设置定时备份：
```bash
chmod +x /opt/trading-bot/backup.sh

# 添加到crontab（每天凌晨2点备份）
crontab -e
# 添加：
0 2 * * * /opt/trading-bot/backup.sh
```

## 🎯 快速部署脚本

我会为你创建一个自动化部署脚本！

## 📞 常见问题

### Q: 如何访问Web界面？
A: http://你的服务器IP:5001 或配置域名后 https://你的域名

### Q: 系统崩溃怎么办？
A: Supervisor会自动重启。查看日志：`tail -f /var/log/trading-system.err.log`

### Q: 如何更新代码？
```bash
cd /opt/trading-bot
# 备份
cp -r . ../trading-bot-backup-$(date +%Y%m%d)
# 更新文件
# 重启
sudo supervisorctl restart all
```

### Q: 数据库在哪？
A: `/opt/trading-bot/paper_trading.db`

### Q: 如何修改配置？
```bash
nano /opt/trading-bot/config.json
sudo supervisorctl restart all
```

## ⚠️ 重要提醒

1. **不要泄露 config.json**
2. **定期备份数据库**
3. **监控服务器资源**
4. **查看交易日志**
5. **保持系统更新**

## 📝 检查清单

部署前确认：
- [ ] 服务器已购买并可访问
- [ ] Python 3.9+ 已安装
- [ ] config.json 已准备好
- [ ] 所有文件已上传
- [ ] 依赖包已安装
- [ ] Supervisor 已配置
- [ ] 防火墙已设置
- [ ] 备份脚本已创建
- [ ] 测试运行正常
- [ ] Telegram通知正常

部署后确认：
- [ ] 交易系统正在运行
- [ ] Web界面可访问
- [ ] Telegram通知正常
- [ ] 数据正常记录到数据库
- [ ] 日志文件正常生成
- [ ] 备份任务已设置

---

**下一步**: 我为你创建自动化部署脚本
