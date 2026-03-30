# yelangawei/aijav_seo 深度代码审查

**日期:** 2026-03-30
**技术栈:** React 19 + TypeScript + React Router 7 SSR + TanStack Query + CryptoJS + Web Workers + i18next

---

## 发现的 Bug (13个)

### 🔴 严重 (4个)

1. **encryption-utils.ts:3-5** — AES密钥硬编码(与example/utils.ts重复文件)
2. **example/utils.ts:197** — saveAsImg DOM元素不清理，removeChild被注释，内存泄漏
3. **worker-pool.ts:80-89** — processPendingTasks shift()后worker响应findIndex找不到task，Promise永久挂起
4. **worker-pool.ts:109** — timeout后activeTasksCount可能变负数

### 🟡 中等 (6个)

5. **useSubtitleProcessor.ts:34** — 全局Worker永不销毁，timeout不removeEventListener
6. **useSubtitleProcessor.ts:244** — eslint-disable隐藏依赖问题
7. **useLoginUser.ts:5** — 用useQuery处理登录(应useMutation)，切换用户返回缓存旧结果
8. **UserContext.tsx:113** — useEffect缺clearUser依赖
9. **useSubtitleTrackManager.ts:74** — 空依赖但引用playerRef，mount时不绑定
10. **axios.ts:102** — JSON.parse失败后不清除无效缓存

### 🟢 轻微 (3个)

11. **ConfigContext.tsx:45** — console.log泄露百度统计token
12. **useVideoPlayerState.ts:37** — videoId!非空断言
13. **useVideoPlayerState.ts:133** — render中console.error副作用

---

## 代码优化 (8个)

1. GlobalImageContext.tsx — getImageByKey未useCallback
2. AuthDialogContext.tsx — contextValue每次新创建 → useMemo
3. example/utils.ts — any类型泛滥
4. useSubscribeActor.ts — optimistic update + invalidate冗余
5. useToggleVideoCollection.ts — setTimeout延迟脆弱
6. imageUtils.ts — errorLog数组无大小限制
7. VideoPlayer.tsx:153 — getSidebarComponent每次rebuild → useMemo
8. useBase64Image.ts — 列表页大量并发fetch → IntersectionObserver

---

## 功能缺陷 (7个)

1. usePurchasePackage.ts — 缺onError，网络错误无反馈
2. worker-pool.ts terminate() — 不清理pending timeout
3. useSubtitleProcessor.ts — 快速切换视频旧字幕覆盖新视频
4. AuthDialogContext.tsx:46 — intendedRoute未防//evil.com注入
5. utils.ts:276 — sessionStorage timestamp逻辑缺陷
6. subtitle-cache.service.ts:256 — IndexedDB事务嵌套
7. 全局 — 缺少Error Boundary

---

## 综合评分: 6.5 / 10

| 维度 | 评分 |
|------|------|
| 安全性 | 5/10 |
| 代码质量 | 7/10 |
| 可维护性 | 7/10 |
| 工程化 | 5/10 |
| 性能 | 6/10 |
