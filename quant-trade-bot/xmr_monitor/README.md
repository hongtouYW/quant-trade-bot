# XMR监控系统

## 📂 文件结构

### 核心监控文件
- `xmr_continuous_notify.py` - 主监控程序（每5分钟发送Telegram通知）
- `xmr_simple_telegram.py` - 监控核心类
- `start_xmr_service.sh` - 服务管理脚本

### 其他监控版本
- `xmr_contract_monitor.py` - 原始完整版本（需要ccxt）
- `xmr_auto_monitor.py` - 自动监控版本（仅控制台）
- `xmr_test_30s.py` - 30秒测试版本
- `xmr_quick_check.py` - 快速价格检查

### 工具文件
- `test_xmr_apis.py` - API价格测试
- `test_telegram.py` - Telegram通知测试
- `test_new_format.py` - 新格式测试

### 日志和数据
- `logs/xmr_monitor.log` - 运行日志
- `logs/*.json` - 历史分析数据

## 🚀 快速开始

### 启动监控
```bash
cd /Users/hongtou/newproject/quant-trade-bot/xmr_monitor
./start_xmr_service.sh start
```

### 查看状态
```bash
./start_xmr_service.sh status
```

### 查看日志
```bash
./start_xmr_service.sh logshow
```

### 停止监控
```bash
./start_xmr_service.sh stop
```

## ⚙️ 配置

### 监控参数
- **入场价格**: $502.41
- **杠杆倍数**: 10x
- **止损位**: $492.36 (-2%)
- **止盈位**: $512.46 (+2%)
- **通知频率**: 每5分钟

### Telegram配置
配置文件位于: `../config.json`
```json
{
  "telegram": {
    "bot_token": "你的机器人Token",
    "chat_id": "你的聊天ID"
  }
}
```

## 📱 通知功能

### 定期通知（每5分钟）
- 💰 当前价格
- 📊 投资回报率（ROI）
- 💰 盈亏金额（带emoji）
- 📈 状态判断

### 特殊通知
- 🎯 启动通知
- ⏹️ 停止通知
- 🚨 网络连接失败

## 💡 使用建议

1. **24/7运行**: 服务可在后台持续运行
2. **网络自动恢复**: 网络断开时自动等待重连
3. **日志监控**: 定期查看日志确保正常运行
4. **定期重启**: 建议每周重启一次服务