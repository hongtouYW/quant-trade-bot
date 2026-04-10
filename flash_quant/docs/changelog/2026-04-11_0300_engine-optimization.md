# 改动记录: engine优化 + scanner改进 + WS增强

## 基本信息
- **时间**: 2026-04-11 03:00 (MYT)
- **改动者**: Claude
- **改动类型**: 🐛 Bug修复 + 🔄 优化

---

## 改动内容

### Bug 修复
1. **信号重复写DB** — 同一根K线每30s重复触发信号
   - 原因: scanner 没有去重, 每次扫描都对同一根未收盘K线产生信号
   - 修复: 加 `_last_signal_ts` 字典, 同一symbol+同一kline_timestamp只处理一次
   - 文件: `scanner/tier1_scalper.py`

2. **risk拒绝时双写** — scanner先写executed, risk拒绝后又写blocked = 每次2条
   - 修复: risk拒绝时UPDATE已有记录, 不INSERT新记录

3. **trades页500错误** — 模板引用mock字段 (`raw_pnl`, `hold_hours`, `open_fee`)
   - 原因: 模板还是mock版本, DB数据没有这些字段
   - 修复: 简化trades.html为14列, 只用DB实际字段
   - 文件: `web/templates/trades.html`, `web/templates/history.html`

4. **vol_24h=0导致全部币被Tier D过滤** — REST数据没拉取, volume全是0
   - 修复: vol_24h=0时传None跳过黑名单检查
   - 文件: `scanner/tier1_scalper.py`

5. **CVD过滤器卡死所有信号** — aggTrade未订阅, CVD永远warmup
   - 修复: Phase 1 CVD数据不足时默认通过 (cvd_skip_phase1)
   - 文件: `scanner/tier1_scalper.py`

### 优化
6. **websockets v16重连兼容** — `no running event loop` 错误
   - 修复: 重连sleep加RuntimeError捕获
   - 文件: `ws/binance_ws.py`

### 数据清理
- 清理了修复前产生的60条重复信号 + 10条重复trades

---

## 影响范围
- ✅ 信号不再重复 (同一K线只1条)
- ✅ /trades页面正常加载
- ✅ Tier D黑名单在REST数据缺失时不误杀
- ✅ CVD不再阻塞所有信号

---

**状态**: ✅ 已完成
