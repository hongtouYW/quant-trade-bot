# Trading SaaS 改进计划

> 审查日期: 2026-02-26
> 审查人: BMAD 团队 (Winston, Mary, John, Sally, Murat, Barry)
> 系统状态: Phase 1-6 已完成，核心功能可用

---

## P0 — 立刻修复（影响可用性）

### 1. Bot 服务重启后自动恢复
- **问题**: `systemctl restart trading-saas` 后所有 Bot 线程丢失，不会自动恢复
- **方案**: 服务启动时检查 `bot_state` 表中 `status='running'` 的记录，自动调用 `BotManager.start_bot()`
- **文件**: `app/__init__.py` 或 `wsgi.py` — 在 app 创建后注册 startup hook
- **验证**: 重启服务后，之前 running 的 Bot 自动恢复扫描

### 2. Agent Onboarding 引导
- **问题**: 新 Agent 登录后不知道操作顺序（API Key → Telegram → 策略 → Start Bot）
- **方案**: Dashboard 顶部加 Setup Checklist 组件，显示配置进度
  - [ ] 设置 Binance API Key
  - [ ] 验证 API Key
  - [ ] 配置 Telegram 通知
  - [ ] 调整交易策略/资金
  - [ ] 启动 Bot
- **文件**: 新建 `frontend/src/components/agent/SetupChecklist.jsx`，嵌入 `pages/agent/Dashboard.jsx`
- **验证**: 未完成配置时显示引导，全部完成后自动隐藏

### 3. 页面 Empty State
- **问题**: Dashboard、Positions、History 无数据时显示空白或错误
- **方案**: 各页面加 Empty State 提示，引导用户下一步操作
- **文件**: `pages/agent/Dashboard.jsx`, `pages/agent/Positions.jsx`, `pages/agent/History.jsx`
- **验证**: 新 Agent 登录看到友好提示而非空白

---

## P1 — 尽快完成（影响商业价值）

### 4. Binance 账户余额同步
- **问题**: Agent Dashboard 显示的 capital 是手动填写的，不反映实际 Binance 余额
- **方案**:
  - 后端新增 API `GET /agent/balance` — 调用 ccxt 获取实时 USDT 余额
  - Dashboard StatCard 显示实际余额
  - 可选：定时同步缓存到 Redis，避免频繁调用 Binance API
- **文件**: `app/api/agent.py` 新增端点, `pages/agent/Dashboard.jsx` 展示
- **验证**: Dashboard 显示与 Binance 一致的余额

### 5. 交易历史 CSV 导出
- **问题**: 交易者需要导出数据做复盘和报税
- **方案**:
  - 后端 `GET /agent/trades/export?format=csv` 返回 CSV 文件
  - 前端 History 页面加 "Export CSV" 按钮
- **文件**: `app/api/trading.py` 新增导出端点, `pages/agent/History.jsx` 加按钮
- **验证**: 下载的 CSV 包含完整交易记录

### 6. Admin 审计日志查看器
- **问题**: 后端记录了 `audit_log`，但没有 UI 查看
- **方案**:
  - 后端 `GET /admin/audit-log?page=&user_type=&action=` 分页查询
  - 前端新增 `/admin/audit` 页面，表格展示，支持按类型/操作筛选
- **文件**: `app/api/admin.py` 新增端点, 新建 `pages/admin/AuditLog.jsx`, `App.jsx` 加路由
- **验证**: Admin 可查看所有敏感操作记录

### 7. 计费周期自动生成
- **问题**: 月度账单需手动操作，无定时任务
- **方案**:
  - 使用 APScheduler 或 cron 定时任务
  - 每月1号自动 close 上月 period + 创建新 period
  - 自动计算高水位线利润分成
- **文件**: 新建 `app/tasks/billing_scheduler.py`, 注册到 app startup
- **验证**: 月初自动生成上月账单

### 8. Bot 实时活动日志
- **问题**: Bot Control 页面信息太少，用户不知道 Bot 在做什么
- **方案**:
  - 后端在 AgentBot 扫描时记录最近 N 条活动到内存或 DB
  - 新增 API `GET /agent/bot/logs?limit=20`
  - Bot Control 页面下方展示实时日志流
- **文件**: `app/engine/agent_bot.py` 加日志缓冲, `app/api/bot_control.py` 新增端点, `pages/agent/BotControl.jsx` 展示
- **验证**: Bot 运行时可看到扫描记录、信号发现、开仓/平仓动态

---

## P2 — 后续迭代（提升竞争力）

### 9. Agent 自助注册
- **问题**: 每个 Agent 都需要 Admin 手动创建，不可规模化
- **方案**:
  - 公开注册页面 `/register`
  - 注册后默认 `is_trading_enabled=false`，需 Admin 审批
  - 可选: 邀请码机制
- **文件**: 新建注册页面 + 后端注册 API
- **验证**: 用户自助注册 → Admin 审批 → 开始使用

### 10. Agent 绩效排行榜
- **问题**: Admin 无法快速对比多个 Agent 的交易表现
- **方案**:
  - Admin Dashboard 加排行榜卡片（按 PnL / Win Rate / ROI 排序）
  - 支持时间范围筛选（7天/30天/全部）
- **文件**: `app/api/admin.py` 新增统计端点, `pages/admin/Dashboard.jsx` 加排行榜组件
- **验证**: Admin 一眼看到哪个 Agent 表现最好

### 11. 手机端响应式优化
- **问题**: 表格和图表在窄屏上可能显示不佳
- **方案**:
  - 表格在移动端改为卡片列表布局
  - 图表宽度自适应
  - 侧边栏改为抽屉式
- **文件**: 各 Table 组件 + Sidebar + 图表容器
- **验证**: 手机浏览器体验流畅

### 12. 紧急熔断通知
- **问题**: Bot 异常连续亏损时，只有 daily_loss_limit 限制，Admin 不知道
- **方案**:
  - 当 Agent 触发 daily_loss_limit 或连续 N 笔亏损时，向 Admin 发 Telegram 告警
  - Admin Dashboard 显示风险告警横幅
- **文件**: `app/engine/risk_manager.py` 加告警逻辑, `app/services/notification_service.py`
- **验证**: 极端亏损时 Admin 立即收到通知

### 13. 健康检查 + 数据库备份
- **问题**: 无健康检查端点，无数据备份
- **方案**:
  - `GET /health` 返回服务状态 + DB 连接 + Bot 运行数
  - cron 定时 mysqldump 备份到远程存储
- **文件**: `app/api/health.py`, cron script
- **验证**: 监控系统可探测服务健康，数据可恢复

### 14. 单元测试 + 集成测试
- **问题**: 零测试覆盖，生产交易系统风险极高
- **方案**:
  - 核心模块测试: EncryptionService, BillingService, RiskManager, SignalAnalyzer
  - API 集成测试: 认证流程, CRUD 操作
  - 交易引擎 mock 测试: 开仓/平仓/止损逻辑
- **文件**: 新建 `tests/` 目录
- **验证**: `pytest` 通过，核心逻辑有保障

---

## 实施建议

| 阶段 | 内容 | 预估工作量 |
|------|------|-----------|
| Sprint 1 | P0 #1-3 (Bot自动恢复 + Onboarding + Empty State) | 中 |
| Sprint 2 | P1 #4-5 (余额同步 + CSV导出) | 中 |
| Sprint 3 | P1 #6-8 (审计日志 + 计费自动化 + Bot日志) | 大 |
| Sprint 4 | P2 #9-10 (自助注册 + 排行榜) | 中 |
| Sprint 5 | P2 #11-14 (手机优化 + 熔断 + 健康检查 + 测试) | 大 |

---

## 当前系统统计

| 项目 | 数量 |
|------|------|
| 前端页面 | 13 (4 Admin + 7 Agent + 2 Login) |
| 前端组件 | 8 可复用组件 |
| API 端点 | 50+ |
| 数据库表 | 10+ |
| 监控币种 | 128 (135 - 7 skip) |
| 策略预设 | 3 |
