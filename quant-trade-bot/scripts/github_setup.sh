#!/bin/bash

# GitHub推送配置说明

echo "📦 量化交易系统已准备就绪"
echo "🔗 GitHub仓库配置："

echo "
方式1: 使用Personal Access Token (推荐)
1. 在GitHub创建仓库: https://github.com/new
   - 仓库名: quant-trade-bot
   - 设为Private
   
2. 生成Personal Access Token:
   - GitHub -> Settings -> Developer settings -> Personal access tokens
   - 选择repo权限
   
3. 推送命令:
   git remote set-url origin https://github.com/hongtouyw/quant-trade-bot.git
   git push -u origin main
   # 密码处输入token而非密码

方式2: 使用SSH密钥
1. 生成SSH密钥:
   ssh-keygen -t ed25519 -C 'hongtouyw@gmail.com'
   
2. 添加到GitHub:
   - 复制公钥: cat ~/.ssh/id_ed25519.pub
   - GitHub -> Settings -> SSH keys -> New SSH key
   
3. 推送:
   git remote set-url origin git@github.com:hongtouyw/quant-trade-bot.git
   git push -u origin main
"

echo "💡 本地代码已完全准备好，包含："
echo "   - 完整的交易机器人系统"
echo "   - 4种策略模块"
echo "   - 回测分析系统"  
echo "   - 前端展示界面"
echo "   - 共4次提交记录"

git log --oneline