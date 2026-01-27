# 📂 项目文件整理说明

整理日期: 2026-01-23

## 🎯 文件夹结构

### 📱 xmr_monitor/ - XMR合约监控
所有XMR监控相关文件，包括：
- Telegram自动通知
- 价格监控
- 盈亏计算
- 运行日志

**快速使用**:
```bash
# 从主目录直接管理
./xmr start    # 启动监控
./xmr status   # 查看状态
./xmr log      # 查看日志
./xmr stop     # 停止监控

# 或进入文件夹
cd xmr_monitor
./start_xmr_service.sh start
```

### 📊 strategy_tests/ - 策略测试
历史策略测试和回测文件，包括：
- 策略回测程序
- 多时间框架策略
- 历史分析数据
- 测试结果

## ✅ 当前运行状态

XMR监控已启动并正常运行：
- ⏰ 每5分钟自动发送Telegram通知
- 📱 Telegram通知已启用
- 🌐 自动网络重连
- 📊 实时价格监控

## 🔧 配置文件

主配置文件位于项目根目录：
- `config.json` - API密钥和Telegram配置

## 💡 后续操作

1. **XMR监控**: 在后台自动运行，无需手动干预
2. **策略测试**: 需要时可查看历史测试数据
3. **日志查看**: 定期检查 `xmr_monitor/logs/` 确保正常运行