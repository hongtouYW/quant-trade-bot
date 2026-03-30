# yelangawei/newav-frontend 深度代码审查

**日期:** 2026-03-30
**技术栈:** React 19 + TypeScript + React Router v7 SSR + TanStack Query

---

## 发现的 Bug (10个)

### 🔴 严重 (3个)

#### 1. worker-pool.ts:80-89 — WorkerPool activeTasksCount 竞态
- timeout handler 和 handleWorkerMessage 都减 activeTasksCount，可能减到负数
- 导致后续所有任务调度崩溃
- **修复:** timeout应先检查task是否存在，分离pending/active队列

#### 2. useSubtitleTrackManager.ts:74 — useEffect空依赖+playerRef
- 依赖数组为空`[]`，但访问`playerRef.current`
- mount时player可能还没ready，事件监听器永远不会绑定
- **修复:** 监听player ready事件或用state追踪就绪状态

#### 3. useSubtitleProcessor.ts:34 — 全局Worker永不清理，内存泄漏
- 模块级`processorWorker`永远不会terminate
- timeout分支不移除handleMessage监听器，积累僵尸监听器
- **修复:** 增加引用计数，最后一个消费者卸载时terminate

### 🟡 中等 (5个)

#### 4. encryption-utils.ts:3-5 — AES密钥/IV/签名Key明文硬编码
- 打包进JS bundle，任何用户可解密API通信
- **修复:** 环境变量+构建时注入

#### 5. useVideoPlayerState.ts:32 — videoId!非空断言
- videoId类型是string|undefined，非空断言是类型安全隐患
- **修复:** 改为`videoId ?? ""`

#### 6. useVideoPlayerState.ts:124 — useCallback依赖过度
```ts
setCommentsExpanded(!commentsExpanded) // 依赖[commentsExpanded]
```
- 每次变化创建新函数，useCallback失去优化意义
- **修复:** `setCommentsExpanded(prev => !prev)`，依赖改`[]`

#### 7. axios.ts:116 — Token过期静默reject无UI响应
- 检测到过期只reject，不通知用户重新登录
- **修复:** reject前触发tokenChanged事件

#### 8. comment-area.tsx:83 — Number(videoId!)产生NaN
- videoId为undefined时发送NaN给后端
- **修复:** `if (!videoId) return`

### 🟢 轻微 (2个)

#### 9. UserContext.tsx:113 — useEffect缺少clearUser依赖
#### 10. useToggleVideoCollection.ts:35 — setTimeout+invalidate竞态
- 300ms手动更新+350ms refetch可能UI闪烁
- **修复:** 用React Query optimistic update模式

---

## 代码优化建议

1. **GlobalImageContext.tsx** — getImageByKey未memoize+线性搜索 → useMemo创建Map
2. **useSEO.ts** — useEffect依赖是对象字面量，每次render重设SEO tags → 解构依赖
3. **worker-pool.ts:149** — poolSize参数只首次生效 → 增加变化检测
4. **axios.ts:80** — API baseURL硬编码 → import.meta.env.VITE_API_BASE_URL
5. **useSubtitleProcessor.ts:248** — eslint-disable隐藏依赖问题

---

## 功能缺陷

1. **useVideoPlayerState.ts:103** — handlePurchaseClick只有注释无实现，用户点购买无反应
2. **AuthDialogContext.tsx:20** — 开发环境context缺失返回no-op，掩盖bug
3. **useMyCollectedVideos.ts** — 缺少分页，大量收藏一次性加载
4. **usePlayLog.ts** — 无认证检查
5. **popup-banner.tsx:38** — setTimeout cleanup被丢弃，组件卸载timer不清理
6. **axios.ts:146** — skipEncryption字段从未使用，配置形同虚设

---

## 综合评分: 6 / 10

| 维度 | 评分 | 说明 |
|------|------|------|
| 安全性 | 5/10 | 密钥明文+token处理不完整 |
| 代码质量 | 7/10 | 架构规范，但有竞态和泄漏 |
| 可维护性 | 7/10 | 分层清晰，局部复杂度高 |
| 工程化 | 5/10 | 无测试无CI |
| 性能 | 5/10 | Worker泄漏+不必要re-render |
