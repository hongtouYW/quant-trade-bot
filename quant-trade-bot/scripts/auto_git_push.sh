#!/bin/bash

# 自动git推送脚本
# 用于每天 12:00 AM (午夜) 推送到GitHub

cd /Users/hongtou/newproject/quant-trade-bot

# 记录日志
LOG_FILE="/Users/hongtou/newproject/quant-trade-bot/logs/git_auto.log"
mkdir -p logs

echo "=== $(date '+%Y-%m-%d %H:%M:%S') - GitHub推送任务开始 ===" >> $LOG_FILE

# 确保SSH密钥已加载
ssh-add ~/.ssh/github_key >> $LOG_FILE 2>&1

# 检查是否有未推送的提交
UNPUSHED=$(git log origin/main..HEAD --oneline)
if [ -n "$UNPUSHED" ]; then
    echo "检测到未推送的提交，开始推送到GitHub..." >> $LOG_FILE
    echo "待推送提交：" >> $LOG_FILE
    echo "$UNPUSHED" >> $LOG_FILE
    
    # 推送到GitHub
    git push origin main >> $LOG_FILE 2>&1
    
    if [ $? -eq 0 ]; then
        echo "✅ GitHub推送成功" >> $LOG_FILE
    else
        echo "❌ GitHub推送失败" >> $LOG_FILE
    fi
else
    echo "没有新的提交需要推送" >> $LOG_FILE
fi

echo "GitHub推送任务完成" >> $LOG_FILE
echo "" >> $LOG_FILE