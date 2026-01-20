# Git自动化管理系统

## 📋 功能说明

本系统自动管理量化交易机器人的代码版本控制：

### ⏰ 定时任务
- **下午 2:00**: 自动提交本地代码变更
- **晚上 11:00**: 自动提交本地代码变更  
- **午夜 12:00**: 自动推送所有变更到GitHub

### 📁 文件结构
```
scripts/
├── auto_git_commit.sh    # 本地git提交脚本
├── auto_git_push.sh      # GitHub推送脚本
└── cron_config.txt       # Cron定时任务配置

logs/
└── git_auto.log          # 自动化操作日志
```

### 🔧 手动操作

查看定时任务：
```bash
crontab -l
```

查看操作日志：
```bash
cat logs/git_auto.log
```

手动测试脚本：
```bash
./scripts/auto_git_commit.sh
./scripts/auto_git_push.sh
```

修改定时任务：
```bash
crontab -e
```

停用定时任务：
```bash
crontab -r
```

### 📝 日志说明
- 每次操作都会记录到 `logs/git_auto.log`
- 包含时间戳、操作类型和执行结果
- 自动检测是否有变更需要提交/推送

### ⚠️ 注意事项
- 确保SSH密钥已正确配置
- 系统会自动跳过没有变更的情况
- 日志文件会持续增长，建议定期清理