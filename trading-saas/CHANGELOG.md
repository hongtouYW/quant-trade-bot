# CHANGELOG — Trading SaaS + 5111 Paper Trader

> 时间范围：2026-01-27 ~ 2026-04-27
> 仅包含有实质代码变更的提交，跳过纯自动提交（🤖 自动提交）。
> [SaaS] = Trading SaaS 生产系统（端口 80）| [5111] = Paper Trader 模拟系统（端口 5111）

---

## 2026-04-27

### [SaaS-迁移] 独立服务器部署 43.98.81.206

- **背景**：trading-saas 之前与 SignalHive 共用 139.162.31.86 服务器，需要独立服务器隔离
- **新服务器**：43.98.81.206（Ubuntu 22, 4GB RAM, 99GB 磁盘）
- **部署**：完整代码 + DB（13 表）从 signalhive-server 同步到 saas-server
- **配置**：MySQL + Redis + Nginx + Supervisor，端口 80/5301，时区 Asia/Kuala_Lumpur
- **SSH 别名**：`saas-server` (root@43.98.81.206)
- **Binance API IP 白名单**：已添加 43.98.81.206
- **状态**：bot 运行中，余额 2787 USDT

### [SaaS-修复] 幽灵持仓批量同步 + 自动检测机制

- **问题**：DB 有 15 个 OPEN 持仓，但 Binance 实际只有 1 个（BLUR/USDT）。其他 14 个是幽灵持仓（最早 04-09 的 TURBO peak_roi 已 100%）
- **根因**：bot 重启或服务器迁移时，内存持仓没同步到 DB；交易所平仓但 DB 未更新
- **修复**：
  1. 一次性脚本 `sync_ghosts.py` 从 Binance `fapiPrivateGetUserTrades` 拉真实平仓价，批量同步 14 个幽灵持仓
  2. **新增 `_reconcile_db_against_exchange()` 方法**到 `agent_bot.py`：bot 启动时自动检测 DB OPEN 但交易所没有的持仓，自动从币安交易历史同步真实平仓价/PnL/时间，更新 DB 状态为 CLOSED 并发 Telegram 通知
- **同步结果**：14 笔幽灵持仓 PnL 合计约 -54U（AAVE +80, ATOM -69, GRIFFAIN -18, AXS -18 等）
- **文件**：`agent_bot.py`（备份 .bak.20260427）

### [SaaS-功能] 评分段表现统计

- **新增**：首页 Dashboard 添加"评分段表现"表格，按评分分组显示交易数/胜负/胜率/总盈亏/平均ROI
- **后端**：`/api/agent/trades/score-stats` endpoint，按 70-74/75-79/80-84/85-89/90+/<70 分组聚合
- **前端**：`Dashboard.jsx` 加表格展示，胜率<50% 红色、>=50% 绿色
- **文件**：`app/api/trading.py`（备份 .bak.20260427）、`frontend/src/pages/agent/Dashboard.jsx`（备份 .bak.20260427）
- **数据洞察**：85-89 分胜率 83.3%/+20U，<70 分胜率 47.6%/-520U（最差）

### [SaaS-调参] min_score 60→80，专注高分信号 + 最小开单 100U

- **背景**：评分段数据显示 <70 分历史亏 -520U，70-74 占 159 笔但平均 ROI 仅 +0.9%
- **改动**：
  - DB `agent_trading_config` agent_id=1: `min_score 60 → 80`, `long_min_score 70 → 85`
  - `signal_analyzer.py:623`: `size = max(50, int(size))` → `max(100, int(size))`
  - `agent_bot.py:568`: `if amount < 50: skip` → `if amount < 100: skip`
- **效果**：只开 80+ 分高质量信号，每笔至少 100U
- **预期**：交易频率大幅下降，但单笔质量提升（85-89 历史胜率 83%）
- **备份**：signal_analyzer.py.bak（已存在）、agent_bot.py.bak.20260427

---

## 2026-04-01

### [SaaS-重构] 引擎全面对齐 5111 Paper Trader — 单一 v6 策略

- **背景**：SaaS 引擎存在 v1~v7 多策略路由，参数、币种筛选、扫描频率、风控逻辑均与 5111 回测/模拟不一致，导致实盘表现偏差
- **目标**：删除所有旧策略，100% 对齐 5111 auto_trader_v6.py
- **改动 — signal_analyzer.py**：
  1. 删除 `analyze_signal()` (v4)、`analyze_signal_v5()`、`calculate_position_size_v5()`、`calculate_stop_take_v5()`、`get_btc_trend()`
  2. 删除 `COIN_TIERS` / `TIER_MULTIPLIER`（5111 无此逻辑）
  3. `DEFAULT_WATCHLIST`: 150 → **133 币**（对齐 5111）
  4. `SKIP_COINS`: 10 → **22 币**（对齐 5111，实际活跃 118 币）
  5. `SYMBOL_1000` 新增 NEIRO
  6. LONG min_score=85 过滤 + MA slope 趋势过滤 移入 `analyze_signal_v6()` 内部
  7. `calculate_position_size()` 简化：去掉 config/symbol 参数，加 `risk_multiplier`，对齐 5111 5 档仓位
- **改动 — agent_bot.py**：
  1. 删除 `_is_v5_strategy()`、`_is_advanced_strategy()`、`_partial_close()`
  2. 删除所有 v4/v5/v6 策略路由分支，统一调用 `analyze_signal_v6()`
  3. 扫描频率：每 60 秒止损检查 + **仅 1H 整点扫描新信号**（`_is_candle_close()`）
  4. 风控替换：移除 `RiskManager` 类，用 `_check_risk_v6()` 对齐 5111（连亏/回撤/日亏）
  5. 删除 Telegram 通知中的 COIN_TIERS 引用
- **改动 — DB**：
  1. `strategy_presets` 表清空（v1~v7 全删）
  2. `agent_trading_config` agent=2 更新：SL=-10%, trailing +6%/3%, 3x, 15仓, min_score=70, cooldown=60min
- **保留**：币安服务端止损单、Telegram 通知、WebSocket、AuditLog、ghost position 检测
- **文件**：`signal_analyzer.py`、`agent_bot.py`（备份 .bak.20260401）
- **部署**：gunicorn reload + bot start，6 个持仓正常恢复

---

## 2026-03-24

### [SaaS-修复] BCH/USDT 幽灵持仓同步 — DB 与交易所状态不一致

- **问题**：BCH/USDT LONG (id=311, 03-18 开仓) 在币安已于 03-20 13:40 平仓（+13.40U, ROI+10.71%），但 DB 状态仍为 OPEN
- **根因**：bot 重启时 BCH 内存持仓丢失，交易所端已平仓但 DB 未同步
- **修复**：通过 Binance `fapiPrivateGetUserTrades` 查到实际平仓记录，手动修正 DB
- **待改进**：bot 启动时应检查 DB 中 OPEN 持仓是否仍在交易所存在，自动同步关闭幽灵持仓

---

## 2026-03-23

### [SaaS-优化] 币安服务端止损单 + 仓位对齐回测

- **背景**：v6 实盘亏损 -180U，诊断发现两大根因：① 仓位计算与回测不一致（线上远小于回测）② 60秒轮询导致止损/止盈滑点（止损平均多亏1%，止盈平均少赚1.2%）
- **改动1 — 仓位对齐回测** (`signal_analyzer.py`):
  - score≥85: min(150,8%) → **min(400,25%)**
  - score≥80 档合并到≥75
  - score≥75: min(350,22%) → **min(300,20%)**
  - score≥70: min(250,15%) → **min(200,15%)**
  - 移除 COIN_TIERS 缩减（回测无此逻辑）
  - 最小仓位: 50U → **100U**
- **改动2 — 币安服务端止损单** (`order_executor.py` + `agent_bot.py`):
  - 开仓后自动挂 **STOP_MARKET** 单（`workingType: MARK_PRICE`），由交易所执行止损
  - 平仓前先 `cancel_all_orders` 取消挂单，防止双重平仓
  - Trailing stop 阶段：peak ROI 每上升 0.5%+，更新服务端止损价到 `peak - trail_distance`
  - 新增方法：`place_stop_order()`, `cancel_open_orders()`, `update_stop_order()`
- **改动3 — 最小仓位检查** (`agent_bot.py`):
  - `if amount < 50` → `if amount < 100`
- **部署**：3个文件已部署到 /opt/trading-saas/，supervisorctl restart auto-trader-v6 + gunicorn reload
- **预期**：止损精度从 -11%→-10%（精确），止盈滑点从 -1.2% 减少到接近 0

### [SaaS-诊断] Agent 2 v6 实盘亏损根因分析

- **数据**：239笔交易，142赢/97亏，总 PnL -180U，费用 21U+7U funding
- **核心问题**：
  1. **盈亏比失衡**：赢 +3.9U/笔 vs 亏 -7.6U/笔（亏是赢的2倍）
  2. **SHORT 重度亏损**：197笔 SHORT 亏 -206U，42笔 LONG 赚 +25U
  3. **90+分单反亏最多**：-84U（short_bias=1.05 推高假信号）
  4. **止损滑点**：设定 -10% 但实际平均 -11%，最差 -19%
  5. **同一币反复止损**：NEAR 4次亏-31U，INIT 3次亏-29U
  6. **特定时段亏损**：UTC 02:00 (-57U), 11:00 (-47U)
- **已修复**：仓位对齐 + 服务端止损（本次部署）
- **待观察**：short_bias / 冷却时间 / SHORT占比限制（用户决定先跑几天看效果）

---

## 2026-03-20

### [SaaS-调参] Agent 2 v6 止盈止损调整 — TP+6% / SL-3%

- **背景**：v6 原参数 SL=-10% / trailing +6%/3%，盈亏比约 1:1.7，实盘亏损
- **改动**：
  - `roi_stop_loss`: -10% → **-3%**（快速止损，减少单笔亏损额度）
  - `roi_trailing_start`: 6%（不变，到价即 TP）
  - 盈亏比：2:1（+6% / -3%），需胜率 >33% 即可盈利
- **修改位置**：DB `agent_trading_config` + `strategy_presets v6.0`
- **部署**：gunicorn graceful reload（kill -HUP），bot 热加载新配置
- **预期**：单笔亏损从 ~7.5U 降到 ~2-3U，胜率可能略降但盈亏比大幅改善

### [SaaS-操作] Agent 2 bot 启动

- **操作**：通过 API `/api/agent/bot/start` 启动 agent 2 交易
- **当前配置**：v6.0 | 3x杠杆 | 15仓 | min_score=70 | TP+6% / SL-3% | 最低仓位100U
- **已生效功能**：
  - 币安服务端止损单（STOP_MARKET，03-18部署）
  - 仓位对齐回测 min(400,25%)（03-18部署）
  - 最低仓位100U（03-18部署）
- **启动后状态**：7个持仓（BCH/BNB/TRX/GRIFFAIN/TIA/WIF/DUSK），全部 ≥100U ✅

---

## 2026-03-19

### [SaaS-分析] Agent 2 v6 策略诊断 — 观察中，暂不调参

- **数据范围**：2026-03-04 ~ 03-18，共 239 笔已平仓交易
- **总PnL**：-180U（回测同期 v6 盈利）
- **已修复（03-18部署）**：仓位对齐回测 + 币安服务端止损单，等待生效
- **发现的5个问题（待观察几天再决定是否调整）**：
  1. **SHORT 占比过高**：82%（197/239），LONG 只 18%，SHORT 总亏 -205U，LONG 赚 +25U
  2. **90+ 高分单亏最多**：38笔亏 -84U，疑似 short_bias=1.05 推高假分数
  3. **时段差异大**：UTC 02:00/11:00/17:00 亏损集中，00:00/06:00/12:00 盈利好
  4. **同币反复止损**：NEAR 4次-31U、INIT 3次-29U、KAVA 3次-27U，60分钟冷却不够
  5. **大亏日全是 SHORT**：3/9 亏-109U（21笔SHORT仅6胜），3/16 亏-112U（19笔SHORT仅2胜）
- **候选优化（先不改，跑几天再看）**：
  - short_bias 1.05 → 1.00
  - 止损后冷却 60min → 4-6h
  - 限制 SHORT 最大持仓数（如 max 10）
  - 90+ 分加额外验证
- **下次评估**：03-22 前后再看服务端止损单和仓位调整的效果

---

## 2026-03-18

### [SaaS-优化] 币安服务端止损单 — 消除轮询滑点

- **背景**：Agent 2 v6 策略实盘亏损 -$180，回测 v6 盈利 +$658K。分析发现核心差异不是信号或参数，而是**止损/止盈执行精度**：
  - 回测：每根K线用 high/low 判断，触发后按精确 -10% 价格计算 → 止损精确在 -10%
  - 实盘：60秒轮询 get_price()，发现时已滑到 -13%/-17%/-19% → 平均止损 -11%
  - 止盈同理：trailing stop 回撤到 +4% 才发现，回测精确在 peak-3%
  - 两头滑点叠加，240笔累积差距数百U
- **改动**：
  1. **`order_executor.py`** — 新增 3 个方法：
     - `place_stop_order()`: 开仓后在币安挂 STOP_MARKET 止损单（服务端执行，不依赖轮询）
     - `cancel_open_orders()`: 平仓前取消所有挂单，防止重复触发
     - `update_stop_order()`: trailing 阶段更新止损价（取消旧单+挂新单）
  2. **`agent_bot.py`** — 3 处改动：
     - 开仓后立刻挂币安 STOP_MARKET 止损单（`workingType: MARK_PRICE`）
     - 平仓前先 `cancel_open_orders()` 再市价平仓
     - peak ROI 上升 0.5%+ 时自动 `update_stop_order()` 移动服务端止损价
- **预期效果**：止损精度从平均 -11% 提升到接近 -10%（交易所端触发），止盈滑点也大幅减少
- **部署**：supervisorctl restart auto-trader-v6

### [SaaS-修复] 仓位大小对齐回测 + 最低仓位100U

- **背景**：线上 `calculate_position_size()` 与回测 `backtest_engine.py` 的仓位计算逻辑不一致
- **差异**：
  | 评分 | 旧（线上） | 新（对齐回测） |
  |---|---|---|
  | >=85 | min(150, 8%) | min(400, 25%) |
  | >=80 | min(250, 15%) | _(合并到>=75)_ |
  | >=75 | min(350, 22%) | min(300, 20%) |
  | >=70 | min(250, 15%) | min(200, 15%) |
  | >=60 | min(150, 10%) | min(150, 10%) |
  | COIN_TIERS缩减 | T3打7折 | **已移除** |
  | 最低仓位 | 50U | **100U** |
- **改动**：
  1. `signal_analyzer.py` L601-611: 仓位梯度对齐回测
  2. `signal_analyzer.py` L623: `max(50,...)` → `max(100,...)`
  3. `agent_bot.py` L443: `amount < 50` → `amount < 100`
- **文件**：`signal_analyzer.py`, `agent_bot.py`
- **部署**：supervisorctl restart auto-trader-v6

---

## 2026-03-17

### [5111-修复] auto_trader_v6.py 回测对齐 — 7项关键差异修复

- **背景**：v6 策略回测年年盈利(+617K/6年)，但模拟盘 7 天亏损 -$350。分析发现实盘执行层与回测引擎存在 7 项关键差异
- **根因**：回测是 1H K线级别低频策略，但实盘每 20 秒扫描+开仓，变成高频策略，手续费($343)吞噬全部利润
- **改动**：
  1. **扫描频率**：开仓扫描从每 20 秒改为**每小时整点**（整点后 0~2 分钟触发），止损检查保持每 60 秒
  2. **仓位大小对齐回测**：85+→min(400,25%), 75+→min(300,20%), 70+→min(200,15%)（原: 90+→350/15%, 80+→280/12%）
  3. **删除 min_hold_minutes 保护**：回测无此逻辑，ROI<=-10% 立即止损，不再延迟
  4. **删除每轮开 3 个限制**：`opened >= 3` 检查移除，与回测对齐（有信号就开）
  5. **止损退出价精确化**：止损/移动止盈触发时，用计算的精确 ROI 价格退出（不再用当前市价），与回测 `_check_position_bar` 对齐
  6. **最低开仓量**：50U → 100U
- **保留不改**：单币种冷却（用户确认正确）、max_hold_minutes=2880 安全网、风控系统
- **预期效果**：交易频率从 ~135 笔/天降至 ~5-15 笔/天，手续费大幅减少
- **文件**：`auto_trader_v6.py`（本地: `scripts/auto_trader_v6.py`）
- **部署**：supervisorctl restart auto-trader-v6，PID 14213

### [SaaS-修复] agent_bot.py v6 策略执行层 6 项对齐修复

- **背景**：评估发现 Agent 2（v6.0）亏损 -$242.56，根因是 v6 信号分析正确但执行层走了 v5 的代码路径，导致仓位计算、止盈止损、冷却机制全部与 Report v6 回测策略不一致
- **改动**：
  1. **max_positions 检查**：`_scan_once()` 循环中新增 `if len(positions) >= max_positions: break`，按用户配置限制
  2. **仓位计算**：v6 从 `calculate_position_size_v5()`（ATR制）改回 `calculate_position_size()`（评分制），与 Report v6 对齐
  3. **TP1/TP2 禁用**：v6 跳过分批止盈逻辑（仅 v5 生效），匹配文档 "不启用 v5_mode, 无 TP1/TP2"
  4. **止损止盈**：v6 从 `calculate_stop_take_v5()`（ATR动态）改回 `calculate_stop_take()`（固定 ROI -10% / trailing 6%/3%）
  5. **单币冷却**：v6 平仓后只冷却该币（per-symbol cooldown），其他币不受影响，所有版本统一使用单币冷却
  6. **最小资金**：新增 `if available < 100: return`，可用资金 < 100U 不再开新仓
- **代码改动**：新增 `_is_v5_strategy()` 辅助函数，拆分 v5 专用逻辑；`_is_advanced_strategy()` 保留用于 MA slope 过滤等共享逻辑；移除 `_global_cooldown_until` 全局冷却变量
- **文件**：`app/engine/agent_bot.py`
- **部署**：已 SCP 至服务器，Agent 2 bot restart 完成

---

## 2026-03-14

### [5111-优化] 冷却机制改为单币种冷却

- **变更**：移除全局冷却（平仓后所有币种禁开 1h），改为**单币种冷却**（只冷却平仓的那个币 1h，其他币不受影响）
- **原因**：用户要求 — 某币平仓后不应阻止其他币种开仓
- **改动**：
  - 删除 `global_cooldown_until` 变量及所有相关逻辑
  - 保留 `cooldowns` 字典（per-symbol），平仓时只记录该币种的冷却时间
  - `scan_market()` 不再有全局冷却阻断，直接进入逐币扫描
- **文件**：`auto_trader_v6.py`

### [5111-功能] Report 新增 v7 策略：资金流+波动率+动量

- **功能描述**：基于 `strategy_new.md` 方案，在 5111 Report 回测系统中新增 v7 策略预设
- **策略理念**：完全不同于 v1-v6 的技术指标打分，v7 使用三大全新维度：
  - **A. 成交量流向 (0-35分)**：用 volume + price 方向四象限分析替代 OI，买卖压力不平衡检测
  - **B. 波动率压缩/爆发 (0-35分)**：历史波动率分位数 + 加速度，检测压缩→爆发入场时机
  - **C. 多周期动量加速 (0-35分)**：5/10/20/50 周期回报率加权 + 动量反转检测
- **入场条件**：至少 2 个策略同向确认才入场，三重确认 +15% 加分
- **v7 参数**：`min_score=50, long_min_score=55, max_positions=8, roi_trailing_start=8, roi_trailing_distance=4`
- **初步回测**（2024 年）：BTC -231, ETH -25, SOL -17, DOT +231, NEAR +43 — 策略代码已跑通，需进一步调优
- **改动**：
  - `backtest_engine.py`：新增 `analyze_signal_v7()` 函数（~180行）+ v7 路由
  - `trading_assistant_dashboard.py`：新增 v7 preset + 三处 strategy select 下拉菜单
  - Report VERSIONS 列表添加 v7

---

## 2026-03-13

### [5111-修复] auto_trader_v6 三大 Bug 修复：冷却失效 + 趋势过滤缺失 + 双进程

- **问题**：连续 8 天亏损 $1,164（-37.8%），从 $3,075 跌到 $1,911
- **根因分析**：
  1. **双进程冲突**：两个 auto_trader_v6 同时运行（PID 24937 + 26999），各自独立的内存冷却互不感知，导致同币种止损后 9 分钟即重新开仓（应冷却 60 分钟）
  2. **break bug**：冷却检查代码在冷却过期后执行了 `break` 而非继续开仓，导致冷却过期的币种永远无法再交易
  3. **趋势过滤缺失**：回测 v6 有 `enable_trend_filter=True`（MA20 斜率过滤），实盘完全没有，导致逆势开仓
- **修复**：
  - 添加**全局冷却**（与回测 v6 对齐）：任意平仓后全局禁止开仓 1 小时
  - 保留 per-symbol 冷却防止同币种重复开仓
  - 修复 break bug：冷却过期后正常参与开仓而非跳出循环
  - 添加 MA20 斜率趋势过滤（`long_threshold=0.02, short_threshold=0.01`），LONG 做多时 MA20 下行则跳过，SHORT 做空时 MA20 上行则跳过
  - 添加 PID 文件锁防止重复启动
  - 杀掉双进程，通过 supervisord 单进程管理
- **典型案例**：3月11日 XAI 被反复做空 10 次（亏 $119）、PIXEL 14 次（亏 $122）
- **文件**：`auto_trader_v6.py`（冷却逻辑重写 + 趋势过滤 + PID 锁）

---

## 2026-03-12

### [5111-修复] Dashboard 数据源切换：交易助手 → 量化v6

- **原因**：5111 首页 Dashboard 仍在读取旧 `assistant='交易助手'` 的数据，显示的是已停止的旧 paper trader 的交易记录，而非当前活跃的 auto_trader_v6 的交易。
- **表现**：首页显示 26 个旧仓位（无限持仓的旧策略），与 Report v6 的 15 仓位上限不一致，用户误以为 5111 和 SaaS 策略不同。
- **修复**：将 `trading_assistant_dashboard.py` 中全部 7 处 `assistant='交易助手'` 改为 `assistant='量化v6'`，使 Dashboard 读取 auto_trader_v6 写入的数据。
- **文件**：`xmr_monitor/trading_assistant_dashboard.py`（7处 SQL 查询修改）

---

### [5111-功能] 监控币种扩展：24 → 140（匹配 Report v6）

- **原因**：5111 首页 `/api/watchlist` 接口硬编码了 24 个币种（XMR/AXS/ROSE 等旧列表），与 Report v6 和 SaaS 的 150 币种监控列表严重脱节。
- **修复**：
  - 删除硬编码 24 币种列表
  - 改为使用 `WATCH_SYMBOLS`（~147 币）减去 `SKIP_COINS`（10 币）= 140 币
  - WATCH_SYMBOLS 和 SKIP_COINS 定义在文件顶部，与 SaaS `signal_analyzer.py` 保持同步
- **参数变更**：监控币种 24 → 140
- **文件**：`xmr_monitor/trading_assistant_dashboard.py` — `/api/watchlist` 端点

---

### [功能] SaaS v6 MA 趋势过滤器（slope>0.02，匹配 Report v6）

- **原因**：SaaS 80 的 agent=2 缺少 MA 趋势过滤，而 Report v6 回测和 5111 paper trader 都有此过滤器，导致 SaaS 开出更多逆势仓位。
- **修复**：在 `agent_bot.py` 的 `_scan_and_trade()` 中，v4.2 过滤器之前插入 v6 MA slope 过滤：
  - LONG 方向：MA slope < -0.02 时拒绝（下跌趋势不做多）
  - SHORT 方向：MA slope > 0.01 时拒绝（上涨趋势不做空）
  - slope = (MA7 - MA20) / MA20
- **文件**：`app/engine/agent_bot.py` — `_scan_and_trade()`

---

### [修复] risk_multiplier 导致仓位过小被跳过

- **原因**：`risk_manager` 评分 6 分（MEDIUM 级别）→ `risk_multiplier=0.5` → 仓位减半后低于 50U 最低门槛，绝大部分开仓被拒绝。
- **表现**：SaaS agent=2 只有 3-5 个仓位，远少于预期的 15 个上限。
- **修复**：在 `agent_bot.py` 中硬编码 `risk_multiplier = 1.0`，禁用风险缩仓功能。
- **文件**：`app/engine/agent_bot.py` — `_open_position()`

---

### [修复] 资金计算 bug：未扣除已平仓盈亏和手续费

- **原因**：`available = initial_capital - used_margin` 没有扣除已平仓的 PnL、手续费、资金费率。同时 `used_margin` 错误地使用名义价值而非保证金。
- **表现**：DB 显示 900U 可用，Binance 实际只有 859U。
- **修复**：
  - `real_capital = initial_capital + closed_pnl - closed_fees - closed_funding`
  - `used_margin = sum(amount / leverage)` 而非 `sum(amount)`
- **文件**：`app/engine/agent_bot.py` — `_open_position()`

---

## 2026-03-11

### [5111-功能] auto_trader_v6.py 独立运行 + v6 bug 修复

- **功能描述**：`auto_trader_v6.py` 作为独立进程运行（非 SaaS 引擎），直接写入 `paper_trader.db`，assistant 标识为 `量化v6`。
- **核心参数**：
  - `min_score=70, long_min_score=85, max_positions=15, max_leverage=3`
  - `cooldown_seconds=3600`（per-symbol 60min）
  - `short_bias=1.05, roi_stop_loss=-10, roi_trailing_start=6, roi_trailing_distance=3`
- **Bug 修复**（b0ce979）：
  1. 重复交易：开仓前检查 DB 是否已有该 symbol 的 OPEN 记录
  2. 资金计算：扣除已平仓 PnL + 手续费 + 资金费率
  3. ROI 显示：修复持仓页面 ROI 计算
  4. 扫描间隔：60s → 20s
- **风控调整**（0d873d9）：
  - 冷却期改为 per-symbol（全局冷却会阻塞所有币种）
  - 禁用 risk pause（避免盈亏波动时暂停开仓）
- **文件**：`auto_trader_v6.py`（864行）

---

### [修复] v1.3.2: 冷却期从全局改回 per-symbol

- **原因**：v1.3.1 为了与 5111 paper trader 对齐而改成了全局冷却，但全局冷却会导致平仓一个币后，所有其他币种都被卡住无法开仓，资金利用率大幅下降。
- **表现**：一笔止损后，整个 scan 周期内所有币种都被 `Global cooldown: Xm remaining` 跳过。
- **修复**：将 `self.last_close_time = None`（全局时间戳）改为 `self.cooldowns = {}`（字典，key=symbol）。平仓时只在该 symbol 上设置冷却，其他 symbol 不受影响。同时移除了 `risk_manager.check_can_open()` 前置检查门，避免 risk gate 误拦开仓。
- **参数变更**：全局冷却 → per-symbol 冷却（冷却时间 `cooldown_minutes` 不变）
- **文件**：`app/engine/agent_bot.py` — `__init__()`, `_close_position()`, `_scan_and_trade()`

---

### [修复] v1.3.1: SaaS v6 与 5111 paper trader 逻辑统一

- **原因**：SaaS 侧 v6 策略包含 BTC 趋势过滤和 score cap 85 逻辑，而 5111 paper trader / backtest report 的 v6 没有这两项，导致两边回测结果无法对照。
- **表现**：SaaS 中很多信号因 BTC filter 被过滤掉，5111 却能开仓，两系统行为不一致。
- **修复**：
  - `signal_analyzer.py`：删除 `analyze_signal_v6()` 中的 BTC trend filter（29行删除）；删除 score cap at 85 的代码。
  - `agent_bot.py`：将 per-symbol 冷却改回全局冷却（此次与 5111 对齐，但在 v1.3.2 再次调整）。
  - `agent.py`：策略预设字段映射新增 `'cooldown' → 'cooldown_minutes'`，修复预设应用时冷却时间丢失的问题。
- **文件**：`app/engine/signal_analyzer.py`（-29行），`app/engine/agent_bot.py`，`app/api/agent.py`

---

### [修复] v1.3.0: 手续费提取三段降级 + 策略预设冷却时间映射

- **原因**：Binance Futures 的 ccxt 订单响应中 `order['fee']` 字段经常为空或不准，导致手续费记录为 0，PnL 计算偏高。
- **表现**：Trade 记录中 `open_fee`/`close_fee` 为 0，实际净盈亏被高估约 0.1%。
- **修复**：新增 `_extract_fee()` 方法，三级降级：
  1. 优先读 `order['fee']['cost']` 或 `order['fees']` 列表求和
  2. 若为 0，调用 `exchange.fetch_my_trades()` 按 `order_id` 匹配（延迟 0.5s 等待结算）
  3. 仍为 0 则按名义价值估算（0.05% taker 费率）
- **文件**：`app/engine/order_executor.py` — 新增 `_extract_fee()`

---

### [修复] v1.2.1: 安全后续修复（速率限制清理/错误回滚/认证限速）

- **原因**：v1.2.0 安全审计后遗留的几个小问题。
- **修复**：
  - `rate_limiter.py`：`cleanup()` 从不实际删除过期条目（逻辑错误），修复后正确移除过期 key。
  - `error_handler.py`：500 错误处理器未 rollback DB session，可能导致连接泄漏，加入 `db.session.rollback()`。
  - `auth.py`：`/refresh` 端点缺少速率限制，加入 30 req/min 限制防暴力刷取 token。
  - `trading.py`：未实现盈亏扣除开仓手续费，现在 unrealized PnL 计算时减去 `open_fee`。
  - `Sidebar.jsx`：`disconnectSocket` 用静态 import 代替动态引用，修复热更新报错。
- **文件**：`app/api/auth.py`, `app/api/trading.py`, `app/middleware/error_handler.py`, `app/middleware/rate_limiter.py`, `frontend/src/components/layout/Sidebar.jsx`

---

### [功能] v1.2.0: 安全与稳定性审计修复（10项）

- **功能描述**：全面安全审计，涵盖后端线程安全、前端错误边界、JWT 时效、Nginx 安全头。
- **主要修改**：
  - `rate_limiter.py`：重写为 Redis-backed + 线程安全内存降级（原实现无锁）
  - `signal_analyzer.py`：BTC trend cache 加 `threading.Lock()` 防竞态
  - `risk_manager.py`：ATR 止损 clamp 逻辑重写，逻辑更清晰
  - `api/trading.py`：profit_factor 在全部盈利时返回 999.99（原返回 0，分母为零）
  - `agent.py` / `market.py`：ccxt exchange 调用后主动 `close()` 连接，防连接泄漏
  - `config.py`：JWT refresh token 有效期 7天 → 2天
  - `bot_manager.py`：修复 BotState 多进程并发 insert 竞态
  - `Sidebar.jsx`：退出时 disconnect socket，防止 stale 连接
  - 新增 `ErrorBoundary.jsx`：React 渲染错误捕获组件，防白屏
  - `nginx-trading-saas.conf`：新增 CSP、Referrer-Policy、Permissions-Policy、WebSocket proxy 配置
- **文件**：`app/api/agent.py`, `app/api/trading.py`, `app/config.py`, `app/engine/bot_manager.py`, `app/engine/signal_analyzer.py`, `app/middleware/rate_limiter.py`, `frontend/src/App.jsx`, `frontend/src/components/common/ErrorBoundary.jsx`, `frontend/src/components/layout/Sidebar.jsx`

---

### [功能] v6.1: auto_trader_v6 + paper trader 更新 + v41 策略（quant-trade-bot 端）

- **功能描述**：quant-trade-bot 端引入全新 v6 策略自动交易器，并更新 paper trader 和 dashboard。
- **主要内容**：
  - 新增 `quant-trade-bot/auto_trader_v6.py`（864行）：v6 策略实现（v4.2 base + MACD/ADX/BB bonus）
  - 新增 `quant-trade-bot/xmr_monitor/paper_trader_v41.py`（1550行）：v4.1 策略 paper trader（对照组）
  - 新增 `quant-trade-bot/xmr_monitor/paper_trader_fixed.py`（1142行）：修复版 paper trader
  - 重命名 `auto_trader_v1.py` → `auto_trader_v41.py`，删除旧 `auto_trader_v1_ab.py`（-632行）
  - `quant-trade-bot/xmr_monitor/backtest_engine.py` 大幅扩展（+769行），支持多年度批量回测
  - 新增 `quant-trade-bot/xmr_monitor/batch_backtest_years.py`（113行）
  - `quant-trade-bot/xmr_monitor/trading_assistant_dashboard.py` 扩展（+832行）
  - 新增 `quant-trade-bot/src/dashboard/dashboard.py`（221行）
- **文件**：16个文件，+6650行/-1332行

---

### [功能] v1.1.0: v6 策略全面审计与修复（10项）

- **原因**：v6 策略在 SaaS 引擎中存在多个严重 bug，与 paper trader 行为不一致。
- **修复清单**：
  1. **策略路由错误**：`startswith('v5')` 无法匹配 `v6.x`，新建 `_is_advanced_strategy()` 函数统一处理 v5/v6
  2. **BTC 过滤缺失**：v6 `analyze_signal_v6()` 完全没有调用 BTC trend filter，此次补入（注：v1.3.1 又删除）
  3. **部分平仓手续费双重计算**：新增 `partial_fees` 字段，与主仓位 `close_fee` 分开记录
  4. **ccxt `order.get('average')` 返回 None**：加入 `or order.get('price') or 0` 降级
  5. **peak_roi 未持久化**：每次 `check_position` 时将最新 `peak_roi` 写入 DB
  6. **entry_price=0 幽灵持仓**：检测到 entry_price=0 时跳过并发告警
  7. **时区不一致**：全部统一为 `datetime.now(timezone.utc)`
  8. **risk_manager is_v5 逻辑**：更新为同时包含 v6 策略
  9. **cross-process peak_roi**：多进程间 peak_roi 改从 DB 读取而非内存
  10. **bot started_at**：新增启动时间戳记录
- **版本**：1.0.x → 1.1.0
- **文件**：`app/__init__.py`, `app/api/trading.py`, `app/engine/agent_bot.py`（+102/-79行）, `app/engine/bot_manager.py`, `app/engine/order_executor.py`, `app/engine/risk_manager.py`, `app/engine/signal_analyzer.py`

---

### [功能] 风险管理器：最大回撤自动恢复机制

- **原因**：最大回撤超限后 risk_score 永久锁定为 8（最高风险等级），机器人无法自动恢复交易，需要手动干预重置。
- **表现**：回撤触发后，机器人虽未崩溃但拒绝所有开仓，等同于永久停机。
- **修复**：新增自动恢复逻辑——检测最近 3 笔交易中是否有 ≥2 笔盈利；若满足则将 `peak_capital` 重置为当前资金（接受亏损，重新开始计算回撤），`risk_score` 恢复正常。
- **参数变更**：drawdown 触发后永久锁定 → 最近 3 笔交易胜 2 自动恢复
- **文件**：`app/engine/risk_manager.py` — `evaluate()` 方法，+14行

---

## 2026-03-10

### [修复] Round 2 bug 修复：Decimal 布尔值、CSV 导出鉴权、loading 状态、leverage null safety

- **修复清单**：
  1. **`trade.py` Decimal 布尔值**：`if trade.pnl:` 在 pnl=0 时求值为 False，导致 `to_dict()` 对盈亏为零的交易返回 `None`。改为 `if trade.pnl is not None:`
  2. **CSV 导出鉴权**：`History.jsx` 使用 `localStorage.getItem('token')` 而非正确的 `'agent_access_token'`，导出请求被 401 拒绝
  3. **`useApi.js` loading 状态**：URL 变更时 loading 不重置为 true，旧数据在新数据到来前仍显示
  4. **leverage null 显示**：`Positions.jsx`/`History.jsx` 渲染 `nullx` → 改为 `-x`
  5. **`Dashboard.jsx`**：从 stats API 获取 `strategy_version`，移除死代码 `DollarSign` import
  6. **`Settings.jsx` Telegram**：已有 Telegram 配置的情况下，保存时不需要重新输入 `bot_token`
  7. **`Agents.jsx`/`Settings.jsx`**：移除未使用的 import
- **文件**：`app/api/trading.py`, `app/models/trade.py`, `frontend/src/hooks/useApi.js`, `frontend/src/pages/agent/Dashboard.jsx`, `frontend/src/pages/agent/History.jsx`, `frontend/src/pages/agent/Positions.jsx`, `frontend/src/pages/agent/Settings.jsx`, `frontend/src/pages/admin/Agents.jsx`

---

### [修复] 10项审计 bug 修复：交易所无关定价、错误处理、UI 竞态

- **修复清单**：
  1. **`admin.py` 定价硬编码**：查看 OPEN 持仓实时价格时，使用 agent 自己配置的 exchange 而非硬编码 Binance（影响 Bitget 用户）
  2. **`admin.py` 错误处理缺失**：update/deactivate/toggle/reset 操作均缺少 try/except + rollback
  3. **`Agents.jsx` fetchTrades 竞态**：快速切换 agent 时旧请求覆盖新结果，用 ref guard 解决
  4. **`Agents.jsx` 密码重置消息**：成功消息在所有行显示而非仅对应行
  5. **`Agents.jsx` null safety**：`exit_price`/`formatDateTime` 缺 null 检查；`key` 用 `tr.id` 代替 index
  6. **`agent_bot.py`**：`fetch_price()` 和 v4.2/v5 analyzer 均补传 exchange 参数
  7. **`signal_analyzer.py` Bitget**：修复 kline interval 格式和 price 验证逻辑
  8. **`gunicorn.conf.py`**：`max_requests` 5000→2000，新增 `graceful_timeout` 防请求丢失
- **文件**：`app/api/admin.py`（+30行），`app/engine/agent_bot.py`, `app/engine/signal_analyzer.py`, `deploy/gunicorn.conf.py`, `frontend/src/pages/admin/Agents.jsx`（+14行）

---

### [功能] 管理员：查看 Agent 交易历史 + 钱包余额 + Bot PnL 分离

- **功能描述**：
  - 管理员可在 Agents 页面直接查看每个 Agent 的开仓/平仓记录（分页）
  - 开仓记录从 exchange 实时获取当前价格，显示未实现盈亏和 ROI
  - Exchange 账户总/可用/占用余额展示
  - Bot 自动交易 PnL 与用户手工操作 PnL 明确区分
  - i18n 新增 en/zh 对应 key
- **文件**：`app/api/admin.py`（+119行），`frontend/src/i18n/en.json`, `frontend/src/i18n/zh.json`, `frontend/src/pages/admin/Agents.jsx`（+127行）

---

### [功能] V6 策略三系统同步 + 8项 bug 修复 + Bitget 多交易所支持

- **原因**：SaaS/paper trader/Report 三系统的 v6 信号逻辑不一致（RSI/MACD 处理顺序、BTC filter、bonus 计算方式），导致无法横向对比回测。
- **V6 信号逻辑统一**（以 Report v6b 为基准）：
  - RSI/trend 冲突惩罚在 bonus 计算**之前**执行（原来在之后）
  - 移除 `coin_own_trend` 对惩罚的豁免逻辑
  - MACD bonus 改为 crossover-based（+10/+6/+3），移除 value-based 计算
  - 移除 SaaS v6 的 BTC trend filter（对齐 5111 paper trader v6b）
- **8项 bug 修复**：
  1. `analyze_signal_v6()` 未传 exchange 参数给 `fetch_klines()`，Bitget 场景崩溃
  2. `_fetch_funding_fee()` Bitget：`abs(total)` 改为 `-total`（方向符号错误）
  3. Telegram/日志中硬编码 "Binance" → 动态 `exchange_name`
  4. `_reconcile_binance_positions` → `_reconcile_exchange_positions`（支持多交易所）
- **Bitget 多交易所支持**：Settings 新增 exchange 选择器，API key 按 exchange 管理，`AgentApiKey` model 新增 `exchange` 列，`order_executor`/`market`/`trading` API 均加入 exchange-conditional 逻辑
- **前端**：全站 i18n（en/zh，各 479行），Settings Bitget UI，Stats 页面改进，Landing 页面更新
- **DB 迁移**：`migrations/versions/001_add_v5_columns.sql`（11列 v5 字段）
- **架构决策**：引入多交易所抽象层，exchange 配置 per-agent 存储
- **文件**：41个文件，+3206行/-842行

---

### [维护] 清理 xmr_monitor 历史目录

- **操作**：删除 `quant-trade-bot/xmr_monitor/` 和 `xmr_monitor/` 下 34 个历史文件（旧版 dashboard、paper trader、监控脚本），共 11596 行删除。XMR monitor 已独立迁移，旧文件造成混淆。

---

## 2026-03-05

### [5111-功能] paper_trader_fixed.py + paper_trader_v41.py（多版本对照组）

- **功能描述**：新建多个 paper trader 版本用于策略对比：
  - `paper_trader_fixed.py`（1142行）：修复版 paper trader，基于原始交易助手改进
  - `paper_trader_v41.py`（1550行）：v4.1 策略独立 paper trader，作为 v6 对照组
- **目的**：为 v6 策略上线前提供基准对比数据，验证新策略是否真正优于旧版
- **文件**：`xmr_monitor/paper_trader_fixed.py`（新增），`xmr_monitor/paper_trader_v41.py`（新增）

---

### [5111-功能] backtest_engine 大幅扩展（多年度批量回测）

- **功能描述**：回测引擎新增 `analyze_signal_v6b()` 函数（v4.2 base + MACD/ADX/BB bonus = max 125），支持多年度（2020-2025）批量回测。新增 `batch_backtest_years.py` 脚本自动化执行。
- **核心改进**：
  - v6b 模式：以 v4.2 评分为基础，bonus 在冲突惩罚之后计算（区别于旧 v6）
  - Report v6 预设：`min_score=70, long_min_score=85, enable_trend_filter=True, long_ma_slope_threshold=0.02`
  - 回测结果确认 v6b 为最优策略：6年净利润 +617K
- **文件**：`xmr_monitor/backtest_engine.py`（+769行），`xmr_monitor/batch_backtest_years.py`（113行新增）

---

### [功能] v0.4: v1 策略 + A/B 测试框架（quant-trade-bot 独立库）

- **功能描述**：新增 v1 原始策略（6年回测最强，净利润 +76,127U）和 A/B 测试框架。
- **A/B 对比**：
  - A 模式：无冲突惩罚
  - B 模式：RSI/趋势冲突时 score×0.85
  - 按日期交替信号模式，收盘后自动切换
- **架构决策**：独立数据库（与 5111 系统完全分离），最大仓位 15，动态杠杆 3-5x
- **文件**：`quant-trade-bot/auto_trader_v1.py`（551行新增），`quant-trade-bot/auto_trader_v1_ab.py`（632行新增），`VERSION.txt`

---

## 2026-03-01

### [修复] Gunicorn preload_app 导致 bot 崩溃

- **原因**：`gunicorn.conf.py` 设置 `preload_app=True`，bot 线程和 DB session 在 master 进程中创建，fork worker 后线程消亡、session 变 stale。
- **表现**：每次 bot restart 后出现 `KeyError` 和 `ResourceClosedError`，bot 无法正常运行，需要手动重启 gunicorn。
- **修复**：将 `preload_app=True` 改为 `preload_app=False`。
- **架构决策**：放弃 preload 模式，每个 worker 独立初始化，避免 fork 后多进程共享资源问题。
- **文件**：`deploy/gunicorn.conf.py`

---

### [修复] Stats API TypeError：Decimal 与 float 混合除法

- **原因**：SQLAlchemy 查询 `gross_loss` 返回 `decimal.Decimal` 类型，而 `profit_factor` 计算对 `float / Decimal` 求值，Python 不支持此混合类型除法。
- **表现**：`GET /api/trading/stats` 返回 500，前端统计页面空白。
- **修复**：`gross_loss` 包裹 `float()` 显式转换后再计算。
- **文件**：`app/api/trading.py` — profit_factor 计算

---

### [修复] Stats API ImportError：`db.case` → `sqlalchemy.case`

- **原因**：SQLAlchemy 2.x 中 `case()` 不再作为 `db` 对象的属性存在，需从 `sqlalchemy` 直接导入。
- **表现**：`from sqlalchemy import case` 成功但调用 `db.case()` 时 AttributeError，Stats API 崩溃。
- **修复**：统一改用 `from sqlalchemy import case`。
- **文件**：`app/api/trading.py`

---

### [功能] 扫描间隔 60s → 20s

- **原因**：60s 扫描间隔导致信号滞后，入场价与分析价差距过大，增加滑点损失。
- **参数变更**：`scan_interval` 60s → 20s
- **文件**：`app/engine/bot_manager.py`

---

### [修复] 幽灵持仓 bug：关仓成功后才更新 DB

- **原因**：`_close_position()` 先将 DB 状态改为 CLOSED，再调用 Binance API 关仓。若 Binance 关仓失败，DB 已标记 CLOSED 但实际持仓仍在 Binance 上，bot 不再追踪（无止损保护）。
- **表现**：HYPE、FARTCOIN、GRASS 三个仓位被标记为 CLOSED，实际在 Binance 上仍开着，无任何止损。
- **修复**：
  - `_close_position()`：先调用 `executor.close_position()`，若返回 None（Binance 失败）则立即 abort，不更新 DB
  - 新增 `_reconcile_exchange_positions()`：bot 启动时查询 Binance 实际持仓，与 DB 比对，不匹配则发 Telegram 告警
- **文件**：`app/engine/agent_bot.py` +57行 — `_close_position()`, `_reconcile_exchange_positions()`

---

### [修复] 重复开仓 bug：DB 级去重 + 线程锁 + 错误指数退避

- **原因**：bot 重启或并发扫描时，内存 dict 中可能已清除的 symbol 在 Binance 上未同步，导致同一 symbol 下两笔相同方向的订单。
- **表现**：同一 symbol 同时存在两个 OPEN 状态的 Trade 记录，Binance 账户上有重复合约持仓。
- **修复**：
  - `_open_position()`：开仓前先查 DB 是否有该 symbol 的 OPEN trade，有则同步内存 dict 并跳过
  - 加 `threading.Lock(blocking=False)` 序列化同一 bot 内的并发开仓请求
  - `run()` 主循环：加连续错误计数，指数退避（60s→120s→240s→300s max）防止 API 错误风暴
- **文件**：`app/engine/agent_bot.py` +54行 — `_open_position()`, `run()`

---

## 2026-02-28

### [修复] gevent/WebSocket 启动 RecursionError + Telegram 配置改进

- **原因**：gunicorn 使用 gevent worker 时，若 `monkey.patch_all()` 未在模块最顶部执行（早于其他 import），Flask-SocketIO 与标准 socket 冲突，产生 `RecursionError`。
- **表现**：gunicorn 启动后 WebSocket 连接失败，日志出现无限递归错误，SocketIO 功能完全不可用。
- **修复**：
  - `wsgi.py`：顶部插入 `import gevent.monkey; gevent.monkey.patch_all()`
  - `wsgi.py`：用 `importlib.import_module('app.api.ws_events')` 导入 ws_events，避免覆盖 `app` 变量
  - `gunicorn.conf.py`：`worker_class` 改为 `'gevent'`（兼容新版 gunicorn）
  - `agent.py`：新增 `GET /agent/telegram` 接口，返回已有 Telegram 配置（chat_id 预填）
  - `Settings.jsx`：Telegram 区域预填已有 chat_id，显示具体错误信息而非通用提示
- **文件**：`wsgi.py`, `deploy/gunicorn.conf.py`, `app/api/agent.py`, `frontend/src/pages/agent/Settings.jsx`

---

## 2026-02-27

### [功能] BTC Strategy 数据管线修复 + 服务器监控 + 运维自动化

- **功能描述**：`btc-strategy/` 数据采集项目稳定性提升，完成 46.5M 条记录 / 1.24GB 采集。
- **主要修复**：
  - 429 限速处理：独立重试计数，不消耗数据重试次数
  - 断点续传：从已有 parquet 文件恢复采集进度（`resume_backfill.py`）
  - 修复 OOM：质量报告/数据派生改为逐文件处理而非全量加载
- **新增功能**：
  - 服务器监控面板（端口 5220）
  - 轻量报告生成器 `gen_report_lite.py`（避免加载全量 parquet 数据）
  - supervisord 自动重启（清算 WebSocket + 服务器监控两个服务）
  - cron 每日 4:30 自动执行数据更新
  - 安装 fail2ban 防 SSH 暴力破解
- **文件**：`btc-strategy/collectors/`, `btc-strategy/quality/`, `btc-strategy/scripts/`, `btc-strategy/storage/`

---

### [功能] P3 优化完成：WebSocket 实时推送 / 移动导航 / API Key 向导 / FAQ

- **P3-7 WebSocket 实时推送**：集成 Flask-SocketIO，替换前端轮询。新建 `app/api/ws_events.py`；`agent_bot.py` 在开仓/平仓/止损等关键事件时 emit socket 事件；`frontend/src/hooks/useSocket.js` hook 封装 socket 连接生命周期。
- **P3-8 移动端底部 Tab 导航栏**：5 项核心功能（Dashboard/Positions/History/Stats/Settings）在小屏幕下切换为底部 Tab Bar，响应式切换。
- **P3-9 API Key 配置步骤向导**：`Settings.jsx` 新增 step-by-step wizard 引导 API Key 配置流程，降低新用户出错率。
- **P3-10 FAQ 页面**：新建 `frontend/src/pages/agent/FAQ.jsx`，4 类 11 项常见问题。
- **文件**：19个文件，+722行/-87行

---

## 2026-02-25

### [5111-功能] Dashboard 回测参数调整 + Paper Trader 优化

- **修改**：
  - Dashboard 回测 UI 重构（+82/-78行），改善报告布局和样式
  - backtest_engine 回测参数扩展（+40行）
  - Paper Trader 持仓管理优化（+56/-56行）
- **文件**：`xmr_monitor/trading_assistant_dashboard.py`（+82/-78行），`xmr_monitor/backtest_engine.py`（+40行），`xmr_monitor/paper_trader.py`（+56行）

---

## 2026-02-26

### [功能] P0/P1/P2 改进计划全部完成（14项，Trading SaaS 1.0 里程碑）

- **P0（核心可用性）**：
  - Bot 崩溃自动恢复：`agent_bot.py` 主循环异常捕获 + 重启逻辑
  - Agent 引导清单 `SetupChecklist.jsx`：新用户开机步骤检查（API Key / Telegram / 策略配置 / 启动 Bot）
  - Empty State 处理：无交易/无持仓时显示友好引导提示
- **P1（关键功能）**：
  - Binance 余额同步：`trading.py` 新增 `/balance` API，实时查询 exchange 账户余额
  - CSV 导出：`History.jsx` 一键下载交易记录
  - 审计日志：新建 `AuditLog.jsx` 页面 + `admin.py` 审计接口，所有重要操作写 AuditLog
  - 计费自动化：`billing_service.py` 完整实现（月费/按量计费逻辑）
  - Bot 活动日志：所有关键操作写入 AuditLog
- **P2（体验优化）**：
  - 自助注册：`Register.jsx` + `auth.py` 新增注册接口
  - 绩效排行榜：admin Dashboard 展示 agents ROI 排名
  - 手机响应式：CSS 媒体查询适配
  - 熔断通知：Telegram 推送风险告警（日亏损/最大回撤触发）
  - 健康检查 + 数据库备份：`scripts/backup-db.sh`
  - **96 项自动化测试**：`tests/` 目录完整测试套件（admin/auth/billing/encryption/risk/signal）
- **架构决策**：wsgi.py 作为应用入口正式引入（114行），标准化 gunicorn 启动方式
- **文件**：35个文件，+2605行/-123行

---

## 2026-02-24

### [5111-功能] Dashboard 日历视图 + 每日交易详情 + 风控修复

- **功能描述**：Dashboard 新增日历统计视图和每日交易详情页面，+825行大更新。
- **主要内容**：
  - 新增 `/api/calendar_stats` 接口：按日期汇总胜率/盈亏
  - 新增 `/api/trades/daily/<date>` 接口：查看指定日期所有交易
  - 日历视图：绿色/红色标注盈亏日，点击查看当日详情
  - 风控修复：持仓 <3 个时不计算集中度和方向偏差风险（避免死循环——2仓必然偏一个方向 → 高 risk_score → 暂停开仓 → 永远只有2仓）
  - Report 表格行可点击跳转到回测模拟器
- **参数变更**：risk_score 持仓集中度/方向偏差：仅 >=3 仓时计算
- **文件**：`xmr_monitor/trading_assistant_dashboard.py`（+825/-140行），`xmr_monitor/paper_trader.py`（+90/-90行）

---

## 2026-02-10

### [5111-功能] 回测 Report v4.3.1 策略 + 多版本对比清理

- **功能描述**：回测报告新增 v4.3.1 策略版本，删除不成功的 v4.4（高盈亏比实验）。
- **修改**：
  - 删除 v4.4 preset（止盈15%/止损8% 实验失败）
  - 新增 v4.3.1 到报告对比（样式/badge/winner）
  - Report UI 优化：表格行 hover 过渡动画
- **文件**：`xmr_monitor/trading_assistant_dashboard.py`（+63/-10行），`xmr_monitor/backtest_engine.py`

---

## 2026-02-09

### [5111-功能] 回测引擎扩展 + Report 策略清理

- **功能描述**：回测引擎扩展信号分析功能（+169行），Dashboard 清理不再使用的策略版本。
- **修改**：
  - backtest_engine 新增回测指标计算
  - Dashboard 删除 v4.4 高盈亏比 preset（回测表现不佳）
  - Report 版本列表精简为 v1/v2/v3/v4.1/v4.2/v4.3
- **文件**：`xmr_monitor/backtest_engine.py`（+169/-41行），`xmr_monitor/trading_assistant_dashboard.py`（+66行）

---

## 2026-02-05

### [5111-修复] Dashboard 小修复

- **修复**：Dashboard 微调（3行修改），修正显示逻辑。
- **文件**：`xmr_monitor/trading_assistant_dashboard.py`（+3/-3行）

---

## 2026-02-04

### [5111-功能] Dashboard 风控展示优化 + 每日交易 API

- **功能描述**：Dashboard 新增每日交易详情 API 和风控展示改进。
- **修改**：
  - 新增 `/api/trades/daily/<date>` 端点：查询指定日期所有平仓交易（symbol/direction/pnl/roi 等）
  - Dashboard 风控面板 UI 优化（+79行）
- **文件**：`xmr_monitor/trading_assistant_dashboard.py`（+79/-24行），`xmr_monitor/paper_trader.py`

---

### [5111-修复] Paper Trader v4.3: BTC 趋势惩罚加强 + 冷却期缩短

- **原因**：实盘数据分析——LONG 方向 32 笔交易累计亏损 -266U，下跌市中做多信号过滤不够严格。
- **修复**：
  - BTC 下跌时 LONG 惩罚加重：个币有独立涨势 0.60x → 0.35x；BTC 强跌 0.25x → 0.15x
  - 新增 BTC MA50 中期趋势判断：BTC 价格低于 MA50 时额外惩罚 LONG（0.35x-0.50x）
  - 冷却期 2h → 30min（评分+趋势过滤已足够保护，不需要长冷却）
  - 仓位上限从方向限制改为总数限制：`max_same_direction=6` → `max_positions=12`
- **参数变更**：冷却 2h → 30min；BTC LONG 惩罚全面加强；持仓逻辑简化
- **文件**：`xmr_monitor/paper_trader.py`（+25/-19行）

---

## 2026-02-03

### [5111-功能] Telegram 信号跟踪器（端口 5112）

- **功能描述**：新建独立 Flask 应用 `telegram_signal_dashboard.py`，手动/自动录入 TG 群交易信号。
- **核心功能**：
  - Telethon 实时监听 Telegram 群消息，自动解析交易信号
  - 信号分类、信号时间记录、持仓监控
  - 止盈止损管理、资金曲线、K 线图表
- **文件**：`quant-trade-bot/telegram_signal_dashboard.py`（+1762行）

---

### [5111-功能] Dashboard 过拟合验证页 + paper trader 持仓扩容

- **功能描述**：Dashboard 新增 `/validation` 页面，支持 Walk-Forward 验证和跨年度一致性分析。回测年份扩展至 2020-2025。
- **参数变更**：paper trader 冷却期 4h → 2h；持仓上限 6 → 10
- **文件**：`xmr_monitor/trading_assistant_dashboard.py`（+761行），`xmr_monitor/paper_trader.py`

---

### [功能] Telegram 信号跟踪器 + 过拟合验证 + paper trader 持仓扩容

- **功能描述**：
  - 新建独立 Flask 应用 `quant-trade-bot/telegram_signal_dashboard.py`（端口 5112）：
    - 手动/自动录入 TG 群交易信号，Telethon 实时监听群消息自动解析
    - 信号分类（赌狗日记），信号时间记录
    - 持仓监控，止盈止损，资金曲线，K 线图
  - `trading_assistant_dashboard.py` 新增过拟合验证页（`/validation`）：
    - Walk-Forward 验证、跨年度一致性分析
    - 回测年份扩展（2020-2025）
- **参数变更**：paper trader 冷却期 4h → 2h；持仓上限 6 → 10
- **文件**：`quant-trade-bot/telegram_signal_dashboard.py`（+1762行），`quant-trade-bot/xmr_monitor/paper_trader.py`, `quant-trade-bot/xmr_monitor/trading_assistant_dashboard.py`（+761行）

---

### [修复] v3.3 策略修复（数据驱动，基于 204 笔实盘交易分析）

- **原因**：对 204 笔交易数据进行系统性分析，发现多个导致亏损的规律。
- **修复清单**：
  1. **评分 80+ 跳过**：46 笔交易仅 7% 胜率，亏损 $587，极端信号 = 接盘陷阱，直接 `return False`
  2. **30 分钟重入冷却期**：新增 `self.symbol_cooldown` dict，平仓后 30 分钟内同币种不开仓
  3. **黑名单**：`DOGE/AVAX/LINK/ATOM/AAVE/FIL`（数据显示胜率 <20%，>5 笔交易验证）
  4. **追踪止损 v4**：盈利 3% 才启动，1.5% 追踪距离
  5. **智能止损放宽**：-1%/30min → -2.5%/60min；2h/0% → 4h/-1%
  6. **仓位分配**：70-79 分（最佳区间 48% 胜率）获最大仓位
- **参数变更**：score 80+ 直接拒绝；追踪止损启动阈值 1% → 3%；距离 3% → 1.5%
- **文件**：`quant-trade-bot/auto_trader_v2.py`（+45/-16行）

---

## 2026-02-02

### [5111-功能] Dashboard v4.2 策略预设 + Report UI 改进

- **功能描述**：Dashboard 新增 v4.2 策略预设（动态杠杆：85+ 评分用 5x），Report 页面 UI 增强。
- **修改**：
  - 新增 `STRATEGY_PRESETS['v4.2']`：min_score=60, long_min_score=70, enable_trend_filter=True
  - Report 表格行支持点击跳转回测模拟器（按币种+年份）
  - backtest_engine 新增 v4.2 回测支持（+41行）
- **文件**：`xmr_monitor/trading_assistant_dashboard.py`（+71行），`xmr_monitor/backtest_engine.py`（+41行）

---

## 2026-02-01

### [5111-功能] Paper Trader v4 Coin Tiers + SHORT bias + Dashboard 大更新

- **功能描述**：Paper Trader 引入币种分层（T1/T2/T3）、SHORT 方向加成、skip coins 黑名单。Dashboard 新增大量 API 和统计功能（+315行）。
- **Paper Trader 改进**：
  - Coin Tiers：T1（1.3x 仓位）/ T2（1.0x）/ T3（0.7x），基于回测盈利数据分层
  - SHORT bias 1.05x：做空信号分数 ×1.05（回测显示 SHORT 盈利更稳定）
  - SKIP_COINS 黑名单（回测持续亏损的币种直接跳过）
  - 评分系统调整：RSI 30 + MA 30 + Volume 20 + PricePos 20 = 100 分
- **Dashboard 改进**：
  - 新增每日统计、历史回顾 API
  - 策略预设管理界面
- **参数变更**：评分权重重新分配；新增 Tier 分层乘数；新增 short_bias
- **文件**：`xmr_monitor/paper_trader.py`（+290/-70行），`xmr_monitor/trading_assistant_dashboard.py`（+315/-20行）

---

### [5111-修复] Paper Trader 回测/Dashboard 改进

- **修复**：Dashboard 统计和回测结果微调（+127行），paper_trader 参数优化（+48行）。
- **文件**：`xmr_monitor/backtest_engine.py`（+10行），`xmr_monitor/paper_trader.py`（+48行），`xmr_monitor/trading_assistant_dashboard.py`（+117行）

---

### [功能] v3.2 BTC 大盘趋势过滤 + 147 币种扩展

- **功能描述**：引入 BTC 大盘趋势过滤，减少逆势操作；监控币种扩展至 147 个。
- **主要内容**：
  - 新增 `get_btc_trend()` 函数（BTC 1h MA 趋势，5 分钟缓存）
  - BTC 下跌时做多信号砍分 25-75%；BTC 上涨时做空信号砍分 25-55%
  - 个币有独立趋势时减轻惩罚
  - 预筛阈值 50 + BTC 过滤后 70 门槛（两阶段过滤）
  - 监控币种 13 → 147（新增主流/L1/L2/DeFi/GameFi/AI/Meme 分类）
  - API timeout 180s 适配大币种列表
  - 30 分钟最低持仓保护（<30min 胜率 0%，紧急亏损 >5% 除外）
- **新增文件**：`quant-trade-bot/web_monitor.py`（795行，Web 监控界面）
- **文件**：`quant-trade-bot/auto_trader_v2.py`（+29/-7行）, `quant-trade-bot/web_monitor.py`（新增）

---

## 2026-01-31

### [5111-功能] 回测引擎首次创建 + Dashboard 回测模拟器/策略报告（+2410行）

- **功能描述**：5111 系统最大规模单次更新。新建 `backtest_engine.py`（575行），Dashboard 新增回测模拟器和策略报告页面（+1808行）。
- **回测引擎**（`xmr_monitor/backtest_engine.py`，全新）：
  - 从 Binance 拉取历史 K 线（1h），模拟 paper_trader 交易逻辑
  - RSI/MA/Volume/PricePosition 评分、BTC 趋势过滤、冲突惩罚
  - 支持多策略版本（v1/v2/v3）回测对比
  - 纯函数设计，不依赖网络/数据库
- **Dashboard 回测模拟器**（`/backtest`）：
  - 选择币种 + 年份，即时回测
  - 展示交易列表、盈亏曲线、月度统计
  - 可调参数：min_score、杠杆、止盈止损
- **Dashboard 策略报告**（`/report`）：
  - 多策略 × 多币种 × 多年份 对比矩阵
  - 自动标注每个币种的最优策略
  - 年度切换（2020-2025）
- **其他**：paper_trader v3.x 参数重构（+47行），Dashboard UI 大幅重构（+486/-486行）
- **文件**：`xmr_monitor/backtest_engine.py`（+575行新建），`xmr_monitor/trading_assistant_dashboard.py`（+1808行），`xmr_monitor/paper_trader.py`

---

### [功能] Release v3.0 稳健策略 — 全面调整修复核心亏损问题

- **原因**：v2.x 激进配置导致连续亏损，需要系统性回调到稳健参数。
- **参数变更**：

| 参数 | v2.x | v3.0 |
|---|---|---|
| 止损 | 1.5% | 3% |
| 止盈 | 2.5% | 5% |
| 追踪止损启动 | 无门槛 | 盈利 1% 后 |
| min_score | 55 | 70 |
| 杠杆 | 5x | 3x |
| 最大持仓 | 10 | 5 |

- **新增机制**：
  - 投票制评分系统（趋势 3 票最高权重）
  - 信号矛盾时惩罚分数（0.4x-1.0x 乘数）
  - 熔断机制 30 分钟自动解锁
- **文件**：`quant-trade-bot/auto_trader_v2.py`（+22/-6行），`src/dashboard/web_monitor.py`（+149行）

---

## 2026-01-30

### [5111-功能] Paper Trader v4 策略重写（+288行）

- **功能描述**：Paper Trader 从 v3.x 升级到 v4 策略架构，最大规模重写。
- **核心改动**：
  - 新增 `_restore_capital()`：从 DB 恢复真实资金（避免重启后资金归零）
  - min_score 70 → 55（新评分系统更严格，55 相当于旧 70）
  - 最低仓位 100U → 50U（允许更小仓位进场）
  - 新增 55-69 分低仓位区间（200U/3x 和 150U/3x）
  - RSI/趋势冲突惩罚放宽：0.7x → 0.85x（数据显示 0.7 太狠）
  - 中风险自动恢复交易（仓位减半），不再永久暂停
- **文件**：`xmr_monitor/paper_trader.py`（+288/-104行），`auto_trader_v2.py`（+48行）

---

### [5111-修复] Paper Trader v4.1: BTC 趋势重罚 + 固定止损保护

- **原因**：v4 LONG 方向 20 笔交易亏损 -88.5U，而 SHORT 9 笔盈利 +65.8U，BTC 下跌时做多惩罚不够重。另外 1-2 小时内大量触发固定止损。
- **修复**：
  - BTC 下跌时 LONG 惩罚全面加重：个币有独立涨势 0.80x → 0.60x；BTC 强跌 0.50x → 0.25x；BTC 弱跌 0.65x → 0.40x
  - 固定止损也受最低持仓时间保护（3h），避免 1-2h 频繁止损
  - 策略标签更新：`v4.1策略: 3x杠杆 | LONG≥70分 | BTC趋势重罚 | 4h冷却 | 最多6仓`
- **参数变更**：冷却期 → 4h；BTC LONG 惩罚加重 50-100%；固定 SL 受 3h 保护
- **文件**：`xmr_monitor/paper_trader.py`（+52/-8行），`auto_trader_v2.py`（+20行）

---

## 2026-01-29

### [5111-修复] Paper Trader 追踪止损参数优化

- **修复**：追踪止损参数微调（+27/-10行），auto_trader_v2 同步优化。
- **文件**：`auto_trader_v2.py`（+22/-8行），`xmr_monitor/paper_trader.py`（+15/-2行）

---

### [功能] Release v0.8 Smart Trader — 追踪止损 + 激进配置

- **功能描述**：追踪止损（价格有利时自动移动止损锁利）、智能止损（主动评估持仓健康度）、1 分钟扫描频率。
- **参数变更**：杠杆 → 5x；单笔仓位 $800；扫描 1min；周目标 $3,000
- **文件**：`quant-trade-bot/auto_trader_v2.py`（+222行），`quant-trade-bot/xmr_monitor/paper_trader.py`

---

### [功能] Release v0.7 Production Ready — auto_trader_v2 正式部署

- **功能描述**：`auto_trader_v2.py` 正式上线运行，supervisor 配置更新，数据完整性验证通过。
- **文件**：`VERSION.txt`

---

### [功能] Release v0.6 Data Framework — 数据库框架升级

- **功能描述**：新增 4 张数据表（`account_snapshots` / `signal_history` / `daily_stats` / `config`），`real_trades` 表新增 `entry_rsi`/`entry_trend`/`atr_pct`/`max_profit`/`max_loss`/`duration_minutes` 字段，paper_trader 新增快照、信号记录、每日统计方法。
- **文件**：`update_trader_for_new_tables.py`（103行），`upgrade_db_schema.py`（150行）

---

### [功能] Release v0.5 ATR Smart Stop — ATR 动态止损

- **功能描述**：新增 `calculate_atr()` 函数，根据波动率动态调整止损距离（波动大宽止损 3%，波动小紧止损 1%），移动止盈距离也根据 ATR 调整。
- **文件**：`add_atr_stoploss.py`（131行）

---

### [功能] Release v0.4 Trailing Stop — 移动止盈策略

- **功能描述**：价格有利时自动移动止损锁利。止损从 5% 收窄到 1.5%，新增 `highest`/`lowest` 价格跟踪字段，回落时自动触发平仓保护已获利润。
- **文件**：`quant-trade-bot/xmr_monitor/paper_trader.py`（+116行）

---

## 2026-01-28

### [5111-功能] auto_trader.py 首次创建 + Paper Trader 大扩展（+1780行）

- **功能描述**：创建独立的 `auto_trader.py`（406行），从 paper_trader 中分离出自动交易逻辑。Paper Trader 和 Dashboard 大规模扩展。
- **auto_trader.py**（全新 406 行）：
  - 独立的自动交易脚本，支持模拟和实盘模式
  - 集成 Binance API，支持期货合约交易
  - 信号评分 + 自动开平仓 + Telegram 通知
- **Paper Trader 扩展**（+463行）：
  - 三段式分批止盈策略升级
  - 移动止损机制完善
  - 持仓管理优化
- **Dashboard 扩展**（+261行）：
  - 新增持仓筛选（全部/做多/做空）
  - 统计面板增强
  - index.html 前端重写（+644行）
- **文件**：`auto_trader.py`（新建 406行），`xmr_monitor/paper_trader.py`（+463行），`xmr_monitor/trading_assistant_dashboard.py`（+261行），`index.html`（+721行）

---

### [功能] Release v0.3 Performance Boost — 前端性能优化

- **功能描述**：前端加载速度提升 50%（API 响应 0.013-0.069s），按优先级依次加载，降低刷新频率（数据 3min，策略 15min），减少 K 线数量（50→30），交易历史分页（首次 20 条）。
- **Bug 修复**：修复 MATIC 持仓卡死问题；统一市场类型为期货（fapi）；手动平仓后正确释放 $300 资金。
- **统计面板**：重新设计 3 区块布局（初始/可用/占用资金 + 费用明细）。
- **文件**：`quant-trade-bot/index.html`（+228行），`src/dashboard/web_monitor.py`（+27行）

---

### [功能] v3.3 激进策略优化 — 信心度阈值降低 + 币种扩展

- **参数变更**：信心度阈值 60% → 50%（+50% 交易机会）；监控币种 13 → 25；最大持仓 6 → 8
- **新增**：持仓筛选功能（全部/做多/做空）
- **文件**：`quant-trade-bot/xmr_monitor/paper_trader.py`（+40行），`quant-trade-bot/xmr_monitor/trading_assistant_dashboard.py`（+178行）

---

## 2026-01-27

### [5111-功能] Paper Trading 系统 v1.0 上线（Dashboard + 交易引擎）

- **功能描述**：5111 Paper Trading 系统首次上线，包含 Web Dashboard（端口 5111）和 Paper Trader 交易引擎。
- **系统配置**：
  - 初始本金 2000U，目标利润 3400U（7天内）
  - 监控币种 7 个：XMR/MEMES/AXS/ROSE/XRP/SOL/DUSK
  - 评分系统：RSI(40) + 趋势(25) + 成交量(20) + 价格位置(15) = 100 分
  - min_score=70，杠杆 5-10x，止损 -5%，止盈 +10%
- **Dashboard 功能**：
  - 实时统计卡片（资金/盈亏/胜率/持仓数）
  - 目标进度条 + 持仓实时图表（24h K线 + 4色价位线）
  - 交易历史记录 + 60s 自动刷新
  - Telegram 通知（开仓/平仓/每日报告）
- **当日快速迭代**（v1.0 → v1.2）：
  - v1.0.1: CoinGecko → Binance API（解决限流），刷新 5s → 60s
  - v1.0.2: 4 色价格线（入场蓝/当前紫/止盈绿/止损红）+ 入场点圆点标记
  - v1.1: 6 种时间周期切换（5min/10min/30min/1h/4h/1d）+ 动态时间格式
  - v1.2: 按需加载图表（页面加载 +80%，内存 -90%）+ 持仓选择器
- **数据库**：SQLite `trading_assistant.db`，assistant 标识 `交易助手`
- **文件**：`xmr_monitor/paper_trader.py`, `xmr_monitor/trading_assistant_dashboard.py` 及 v1.0-v1.2 备份

---

### [功能] 量化交易系统全面优化 v3.2 — 三段式分批止盈 + 移动止损

- **功能描述**：
  - 三段式分批止盈：+5% 平 50%，+8% 再平 30%，剩余 20% 追踪止损
  - 移动止损：盈利 5% 启动，-3% 追踪距离，保护最少 +2% 利润
  - 扫描间隔 5min → 1min（每小时 60 次，原 12 次）
  - 最大持仓 3 → 6
- **文件**：`quant-trade-bot/xmr_monitor/paper_trader.py`（+227行）

---

### [修复] Dashboard 数据硬编码 → 动态加载

- **原因**：顶部统计数据和持仓列表均为硬编码示例数据，用户看到的是假数据。
- **表现**：持仓数量显示 13（硬编码），统计数据不随实盘变化。
- **修复**：新增 `loadStats()` 从 `/api/stats` 动态加载，删除 9 个硬编码持仓，改为从 `/api/positions` 实时获取。切换到期货 API（fapi）保持一致性。复盘图表新增入场价（黄色虚线）、止损价（红色虚线）、止盈价（绿色虚线）。
- **文件**：`quant-trade-bot/index.html`（416行改动），`xmr_monitor/trading_assistant_dashboard.py`

---

### [功能] Dashboard v3.0.1 — 监控列表全面优化

- **功能描述**：添加 10 分钟刷新倒计时，持仓币种显示信心度/杠杆/止盈止损价位，非持仓显示预估盈亏，改进数据获取逻辑（从 DB 读取实际持仓信息）。修复函数命名冲突导致价格获取失败的问题。
- **文件**：`xmr_monitor/trading_assistant_dashboard.py`（+279行）

---

### [功能] Dashboard v1.3 — 三栏布局重设计 + 每日收益追踪系统

- **功能描述**：
  - 三栏布局重设计（监控列表 200px / 图表自适应 / 持仓 280px）
  - 每日收益追踪：新建 `daily_pnl` 数据库表，自动记录每日交易统计
  - 目标进度追踪：大进度条 + 4 统计卡片（目标/当前/日均/预计天数）+ 最近 7 天明细
  - 价格信息区重排：从 1 列 7 行改为 4 列 2 行（节省垂直空间）
- **新增文件**：`xmr_monitor/init_daily_pnl_table.py`, `xmr_monitor/record_daily_pnl.py`
- **文件**：`xmr_monitor/trading_assistant_dashboard.py`（+1027行）

---

### [功能] 增强交易复盘和费用统计

- **功能描述**：
  - 复盘图表新增止损线/止盈线/入场点/出场点四个关键价位标注
  - 数据库新增 `funding_fee` 字段
  - paper_trader 自动计算资金费率（每 8 小时 0.01%，按实际持仓时间比例）
  - 资金统计分三项：初始金额 / 可用金额（总资金 - 占用保证金）/ 占用保证金
  - 总盈亏计算包含交易手续费和资金费率
- **文件**：`xmr_monitor/trading_assistant_dashboard.py`（+380行），`xmr_monitor/paper_trader.py`（+32行）

---

## 系统架构说明

```
Trading SaaS（生产：/opt/trading-saas/，端口 80/5200）
├── Flask + MySQL + Gunicorn（preload_app=False）+ Nginx
├── app/
│   ├── engine/
│   │   ├── agent_bot.py       — 每 Agent 独立线程，20s 扫描一次
│   │   ├── signal_analyzer.py — 信号分析（v4.2 / v5 / v6）
│   │   ├── order_executor.py  — ccxt 下单/平仓/手续费三段降级提取
│   │   ├── risk_manager.py    — 风险评估/回撤自动恢复
│   │   └── bot_manager.py     — 多 Agent 线程管理
│   ├── api/
│   │   ├── admin.py / agent.py / auth.py / trading.py / market.py
│   │   └── ws_events.py       — Flask-SocketIO 实时推送
│   ├── models/
│   │   ├── agent.py / agent_config.py
│   │   ├── trade.py（Trade, DailyStat）
│   │   └── bot_state.py / audit.py / billing.py
│   └── middleware/
│       ├── rate_limiter.py    — Redis-backed + 线程安全内存降级
│       └── error_handler.py   — 500 自动 rollback
├── frontend/（React + Vite，i18n en/zh）
└── deploy/（gunicorn.conf.py，nginx，supervisord）

quant-trade-bot（5111 paper trader：/opt/trading-bot/）
├── auto_trader_v6.py          — v6 策略 paper trader（对齐 SaaS v6）
├── auto_trader_v41.py         — v4.1 对照组
├── xmr_monitor/
│   ├── paper_trader_fixed.py  — 修复版 paper trader
│   ├── paper_trader_v41.py    — v4.1 paper trader
│   └── trading_assistant_dashboard.py — Dashboard（Flask，port 5111）
└── backtest_engine.py         — 多年度批量回测引擎

btc-strategy（/opt/btc-strategy/）
├── collectors/                — 实时数据采集（K 线/资金费率/清算/鲸鱼等）
├── storage/parquet_store.py   — Parquet 存储（46.5M 条/1.24GB）
├── quality/                   — 数据质量验证
└── scripts/
    ├── monitor.py             — 服务器监控面板（port 5220）
    └── gen_report_lite.py     — 轻量报告生成器
```

---

## 当前 V6 策略参数（2026-03-11 最新）

| 参数 | 值 | 说明 |
|---|---|---|
| `strategy_version` | v6.x | v4.2 base + MACD/ADX/BB bonus |
| `max_positions` | 15（默认） | 最大同时持仓数 |
| `max_position_size` | 500U | 单笔最大名义仓位 |
| `max_leverage` | 3x | 最大杠杆 |
| `min_score` | 60 | 最低开仓分数 |
| `long_min_score` | 70 | LONG 方向额外门槛 |
| `cooldown_minutes` | 30 | 平仓后同 symbol 冷却时间（per-symbol） |
| `roi_stop_loss` | -10% | ROI 止损线 |
| `roi_trailing_start` | 6% | 追踪止损启动 ROI |
| `roi_trailing_distance` | 3% | 追踪距离 |
| `daily_loss_limit` | 200U | 日亏损熔断 |
| `max_drawdown_pct` | 20% | 最大回撤限制（超限后自动恢复机制：近 3 笔胜 2） |
| `scan_interval` | 20s | 扫描间隔 |
| `short_bias` | 1.05x | SHORT 方向分数加成 |

---

## V6 信号评分系统

### Base 评分（v4.2 逻辑，满分 100）

| 指标 | 满分 | 触发条件 |
|---|---|---|
| RSI (14) | 30 | RSI<30 或 >70 = 30分；RSI<45 或 >55 = 15分；中性区间 = 5分 |
| 趋势 MA(7,20,50) | 30 | 价格>MA7>MA20>MA50（完美对齐）= 30分 + 2票；MA7>MA20 = 15分 + 1票；其他 = 5分 |
| 成交量 | 20 | 相对均量（20周期）>1.5x = 20分；>1.2x = 15分；>1.0x = 10分；其他 = 5分 |
| 价格位置（50周期高低） | 20 | 底部 <20% = 20分（+LONG票）；顶部 >80% = 20分（+SHORT票）；次极端区间 = 10分 |

### V6 Bonus（最多 +25 分）

| 指标 | 加分 | 条件 |
|---|---|---|
| MACD crossover | +10 | 发生金叉/死叉且与方向一致 |
| MACD momentum | +6 | histogram 增强且方向一致（无 crossover） |
| MACD direction | +3 | 方向一致（histogram 无明显变化） |
| ADX ≥35 | +8 | 强趋势 |
| ADX ≥25 | +5 | 中等趋势 |
| ADX ≥20 | +2 | 弱趋势 |
| BB 极端位置 | +7 | %B < 10%（LONG）或 > 90%（SHORT） |
| BB 较极端位置 | +4 | %B < 25%（LONG）或 > 75%（SHORT） |

### 冲突惩罚（在 Bonus 之前计算）

- RSI 方向与 MA 趋势方向不一致：`total_score × 0.85`

### Coin Tier 仓位乘数（基于 2023-2025 回测数据）

| Tier | 乘数 | 描述 | 代表币种 |
|---|---|---|---|
| T1（26个） | 1.3x | 平均 PnL >600U，连续盈利 | ICP/XMR/DOT/ADA/UNI/ATOM 等 |
| T2（24个） | 1.0x | 平均 PnL 300-600U | ETH/BNB/XRP/HYPE/PYTH 等 |
| T3（17个） | 0.7x | 平均 PnL <300U 但仍盈利 | DOGE/WIF/MATIC/INJ/TRUMP 等 |

### SKIP_COINS（完全跳过，回测持续亏损）

`BERA / IP / LIT / TROY / VIRTUAL / BONK / PEPE / DUSK / FARTCOIN / ANIME`

（共 10 个，回测验证为持续亏损币种）

### 监控列表结构（DEFAULT_WATCHLIST，约 150 个币）

- 顶级流动性（10）：BTC/ETH/SOL/XRP/BNB/DOGE/ADA/AVAX/LINK/DOT
- 主流公链（15）：NEAR/SUI/APT/ATOM/FTM/HBAR/XLM/ETC/LTC/BCH/ALGO/ICP/FIL/XMR/TRX
- Layer2/DeFi（15）：ARB/OP/MATIC/AAVE/UNI/CRV/DYDX/INJ/SEI/STX/RUNE/SNX/COMP/MKR/LDO
- AI/新叙事（15）：TAO/RENDER/FET/WLD/AGIX/OCEAN/ARKM/PENGU/BERA/VIRTUAL/AIXBT/GRASS 等
- 中市值热门（25）、GameFi/存储（15）、DeFi/基础设施（15）、Meme/热点（15）、高波动（25）
