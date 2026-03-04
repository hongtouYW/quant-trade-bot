# Trading SaaS 优化路线图

> 生成日期: 2026-02-26
> 全员讨论结果汇总

---

## 🔴 P0 — 立即执行

### 1. 安全加固（密钥 + CORS）

**现状问题：**
- `JWT_SECRET_KEY` 是开发占位符，可被猜测伪造 token
- `FLASK_SECRET_KEY` 同理
- `CORS_ORIGINS=*` 允许任意域名调用 API

**执行方案：**
- 用 `openssl rand -hex 32` 生成 FLASK_SECRET_KEY
- 用 `openssl rand -hex 64` 生成 JWT_SECRET_KEY
- `CORS_ORIGINS` 改为 `http://139.162.41.38`（后续绑定域名后更新）
- 注意：更换 JWT 密钥后所有现有 token 失效，用户需重新登录

**验证清单：**
- [ ] Admin 登录正常
- [ ] Agent 登录正常
- [ ] Token 刷新正常
- [ ] 非白名单域名请求被 CORS 拦截

---

### 2. SQLite → MySQL 迁移

**现状问题：**
- `.env` 无 `MYSQL_HOST`，系统 fallback 到 SQLite
- 多 Bot 进程 + Gunicorn 4 workers 并发写入 → `database is locked`
- SQLite 不支持真正的并发写入，定时炸弹

**执行方案：**
1. 服务器安装 MySQL 8.0
2. 创建数据库 `trading_saas` + 用户 `saas_user`
3. `.env` 添加 MySQL 配置：
   ```
   MYSQL_HOST=127.0.0.1
   MYSQL_PORT=3306
   MYSQL_USER=saas_user
   MYSQL_PASSWORD=<强密码>
   MYSQL_DB=trading_saas
   ```
4. `alembic upgrade head` 在 MySQL 上重建表结构
5. SQLite 数据导出导入（当前数据量极小）
6. 保留 SQLite 文件作为回滚备份

**注意事项：**
- 迁移前停止所有 Bot
- SQLite 与 MySQL 的 DDL 差异：ENUM、TIMESTAMP 默认值、VARBINARY
- 代码层面零改动（`config.py` 已有切换逻辑）

**验证清单：**
- [ ] MySQL 服务运行正常
- [ ] Alembic 迁移无报错
- [ ] 原有数据完整导入（对比行数）
- [ ] API 读写正常
- [ ] 多并发写入无 lock 错误

---

## 🟡 P1 — 本周/下周

### 3. Bot 进程自动重启（Watchdog）

**现状问题：**
- Bot 子进程崩溃（API 超时、内存泄漏等）后无人恢复
- systemd 只管 Gunicorn 主进程，不管 Bot 子进程

**执行方案：**
- 在 `BotManager` 中添加 `_watchdog()` daemon 线程
- 每 30 秒检查 `active_bots` 中每个进程的 `is_alive()` 状态
- 崩溃自动重启，5 分钟内重启超 3 次 → 标记 error 状态停止重启
- Bot 重启时从数据库恢复 OPEN 状态持仓
- Bot 启动时做一次 Binance 持仓同步（防止崩溃瞬间的订单遗漏）

**验证清单：**
- [ ] 手动 kill Bot 进程后 30 秒内自动重启
- [ ] 重启次数超限后正确停止并标记 error
- [ ] 崩溃重启后持仓数据正确恢复
- [ ] Binance 侧持仓与本地数据一致

---

### 4. Landing Page（绩效展示 + 获客）

**现状问题：**
- 无公开页面展示系统价值
- 用户无法在注册前了解策略绩效
- 缺乏信任建立机制

**页面结构：**
1. **Hero 区**
   - 一句话价值主张
   - 系统历史收益曲线图
2. **信任区**
   - API Key 安全说明（只需交易权限，不能提币）
   - AES-256 加密存储
   - 服务器安全措施
3. **数据区**（使用 5111 paper_trader 真实回测数据）
   - 历史总收益
   - 月度胜率
   - 最大回撤
   - 前 10 最佳交易
4. **费用区**
   - 高水位线法图解
   - 月份示例（赚钱收分成 / 亏钱不收 / 恢复期不收）
5. **流程区**
   - 3 步开始：注册 → 配置 API Key → 启动 Bot
6. **CTA**
   - 注册按钮

**技术方案：**
- 纯静态 HTML/CSS，放在 Nginx `/` 根路径
- 现有 SaaS 应用路由不变（`/agent/*`, `/admin/*`）
- 零后端开发量

**验证清单：**
- [ ] 页面在桌面端和移动端正常显示
- [ ] 数据来源于真实回测记录
- [ ] 注册链接跳转正确
- [ ] 加载速度 < 2 秒

---

## 🟢 P2 — 2-3 周内

### 5. 实时信号面板

**现状问题：**
- Bot 运行后用户只能看原始日志流
- 无法直观了解系统在做什么
- 无信号时用户以为系统故障

**执行方案：**

后端：
- `_scan_once()` 每次扫描结果结构化存储（Redis 或内存缓存）
- 新增 API: `GET /api/agent/bot/signals`
- 返回数据：
  ```json
  {
    "last_scan_time": "2026-02-26T12:00:00Z",
    "next_scan_in": 45,
    "signals_analyzed": 150,
    "signals_passed": 3,
    "signals_filtered": [
      {"symbol": "BTC/USDT", "score": 72, "direction": "LONG", "reason": "MA slope filter"},
      {"symbol": "ETH/USDT", "score": 88, "direction": "LONG", "reason": "85+ LONG skip"}
    ],
    "positions_opened_this_scan": 1
  }
  ```

前端：
- BotControl 页面新增信号面板卡片
- 显示：监控币种数、已分析数、通过/跳过数
- 最近 10 个信号决策（开仓 / 跳过 + 原因）
- 下次扫描倒计时进度条

**验证清单：**
- [ ] API 返回正确的扫描结果
- [ ] 前端实时更新信号面板
- [ ] 跳过原因正确显示（SKIP_COINS / 85+ LONG / MA slope / 分数不够）

---

### 6. 平台内通知中心

**现状问题：**
- 通知仅靠 Telegram，不是所有用户都用
- 平台内无事件记录

**执行方案：**

后端：
- 新增 `notifications` 表：
  ```sql
  CREATE TABLE notifications (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      agent_id INT NOT NULL,
      type ENUM('trade_open','trade_close','risk_alert','billing','system') NOT NULL,
      title VARCHAR(200) NOT NULL,
      message TEXT,
      is_read BOOLEAN DEFAULT FALSE,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (agent_id) REFERENCES agents(id),
      INDEX idx_agent_read (agent_id, is_read)
  );
  ```
- 新增 API:
  - `GET /api/agent/notifications` — 获取通知列表（分页）
  - `GET /api/agent/notifications/unread-count` — 未读数量
  - `PUT /api/agent/notifications/:id/read` — 标记已读
  - `PUT /api/agent/notifications/read-all` — 全部已读
- Bot 开仓/平仓/风控告警时插入通知记录

前端：
- 侧栏顶部 🔔 图标 + 未读数量气泡
- 点击展开通知面板
- 每条通知可点击跳转相关页面

**验证清单：**
- [ ] 开仓/平仓自动生成通知
- [ ] 风控告警生成通知
- [ ] 未读数量实时更新
- [ ] 点击通知跳转正确

---

## 🟢 P3 — 后续优化

### 7. WebSocket 替代轮询

**现状问题：**
- Bot 状态 3s、持仓 5s、日志 5s 轮询
- Agent 数量增长后 API 压力线性增长

**执行方案：**
- 集成 Flask-SocketIO
- Gunicorn 切换到 eventlet/gevent worker
- Bot 状态变化、新交易、日志推送改为 WebSocket

**判断标准：** Agent 数量 > 20 或 API 延迟明显升高时再做。当前 3 个 Agent 轮询完全够用。

---

### 8. 移动端底部导航

**现状问题：**
- 当前 14px 移动端顶栏太窄
- 汉堡菜单需要两次点击才能导航

**执行方案：**
- 移动端（< 768px）使用底部 Tab 导航
- 5 个核心入口：Dashboard / Positions / Bot / History / Settings
- 桌面端保持侧栏不变

---

### 9. API Key 配置引导 Wizard

**现状问题：**
- 用户不知道怎么在 Binance 创建 API Key
- 常见错误：没设 IP 白名单、没开 Futures 权限

**执行方案：**
- Settings 页面 API Key 区域改为步骤式引导：
  1. 登录 Binance 账户
  2. 进入 API Management（带截图示意）
  3. 创建 Key → 设 IP 白名单 `139.162.41.38`
  4. 开启 Enable Futures 权限
  5. 粘贴 Key 和 Secret

---

### 10. FAQ / Help 页面

**常见问题覆盖：**
- 为什么 Bot 没有开仓？（信号不够强 / 被过滤 / 达到最大持仓数）
- API Key 报错怎么办？（IP 白名单 / Futures 权限）
- 利润分成怎么算？（高水位线法说明）
- Bot 显示 error 怎么办？
- 支持哪些交易对？（150 个币种列表）

---

## 执行时间线总览

```
第 1 天    ──── 安全加固（密钥 + CORS）
第 1-2 天  ──── SQLite → MySQL 迁移
第 3-4 天  ──── Bot Watchdog 自动重启
第 5-7 天  ──── Landing Page 绩效展示
第 8-10 天 ──── 实时信号面板
第 11-13 天 ─── 通知中心
后续       ──── WebSocket / 移动端 / Wizard / FAQ
```

---

## 备注

- 所有变更先在本地测试，通过后再部署到 139.162.41.38
- 每个功能部署后需要验证清单全部通过
- 保持与 5111 paper_trader 策略规则完全同步
