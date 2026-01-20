#!/bin/bash

# 自动git提交脚本
# 用于每天 11:00 PM 和 2:00 PM 的本地git更新

cd /Users/hongtou/newproject/quant-trade-bot

# 记录日志
LOG_FILE="/Users/hongtou/newproject/quant-trade-bot/logs/git_auto.log"
mkdir -p logs

echo "=== $(date '+%Y-%m-%d %H:%M:%S') ===" >> $LOG_FILE

# 检查是否有变更
if [ -n "$(git status --porcelain)" ]; then
    echo "检测到文件变更，开始自动提交..." >> $LOG_FILE
    
    # 添加所有变更
    git add -A >> $LOG_FILE 2>&1
    
    # 提交变更
    COMMIT_MSG="🤖 自动提交: $(date '+%Y-%m-%d %H:%M:%S')"
    git commit -m "$COMMIT_MSG" >> $LOG_FILE 2>&1
    
    echo "本地git提交完成" >> $LOG_FILE
else
    echo "没有文件变更，跳过提交" >> $LOG_FILE
fi

echo "本地git更新任务完成" >> $LOG_FILE
echo "" >> $LOG_FILE