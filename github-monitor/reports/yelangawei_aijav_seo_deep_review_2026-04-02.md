# yelangawei/aijav_seo v1.14.1 复审

**日期:** 2026-04-02 | **上次:** 2026-03-30 (6.5/10)

---

## 上次 8 个问题修复情况

### ✅ 已修复 (3个)
1. **worker-pool.ts 任务丢失** → 文件已删除，改用独立Worker
2. **useLoginUser 误用useQuery** → 已改useMutation
3. **axios.ts 硬编码URL** → 已改用 VITE_API_BASE_URL

### 🗑️ 删文件回避 (2个)
4. **useSubtitleProcessor Worker泄漏** → 文件删除，改用纯函数
5. **worker-pool.ts** → 整个文件删除

### ❌ 未修复 (3个)
6. **encryption-utils.ts AES密钥硬编码** → 仍在
7. **utils.ts:197 saveAsImg DOM泄漏** → removeChild仍被注释
8. **comment-area.tsx Number(videoId!) NaN** → 仍用非空断言

---

## 新发现的 Bug (8个)

### 🔴 严重 (2个)
1. **AuthLogin.tsx:15** — URL明文传递密码，QR码含ori_password
2. **entry.client.tsx:7** — 生产环境禁用console.error/warn，完全无法排查错误

### 🟡 中等 (4个)
3. encryption-utils.ts 密钥硬编码(老问题)
4. useSEO.ts BASE_URL可能undefined → sitemap URL无效
5. sitemap-actresses 无限循环请求风险(无lastPage上限)
6. sitemap-videos 只生成中文URL，缺英文版

### 🟢 轻微 (2个)
7. ConfigContext console.log泄露百度token
8. useVideoPlayerState videoId!非空断言

---

## 代码优化
1. 删除encryption-utils.ts(与utils.ts重复且更危险)
2. 重命名example/目录(核心工具不应在example)
3. 静态sitemap只有首页，缺categories/ranking/latest
4. Google Fonts onLoad在SSR不执行
5. useVideoAccess重复逻辑
6. 大量any类型

## SEO功能缺陷
1. Sitemap缺lastmod标签
2. Publishers只取一页5000条
3. categories/ranking/latest/search未SSR
4. robots.txt路径不匹配SSR路由(/:lang/watch/)
5. html lang="zh"硬编码，英文页面lang错误

---

## 综合评分: 6.0 / 10 (上次6.5↓)

**进步:** SSR/SEO迁移有价值(sitemap+SSR loader+hreflang)
**退步:** 新增URL明文密码漏洞 + console全禁用 + 3个老问题未修
**SEO:** 框架搭好但不完整
