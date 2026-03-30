# yelangawei/newav-backend 深度代码审查

**日期:** 2026-03-30
**技术栈:** ThinkPHP 5.x + MySQL + Redis

---

## 发现的 Bug (16个)

### 🔴 严重 (7个)
1. **config/database.php** — 数据库凭证硬编码(RDS地址/密码明文)
2. **Base.php:104** — Cookie认证绕过(cookie admin_id无签名，设1=超管)
3. **Video.php:90** — SQL注入(tag参数直接拼接SQL)
4. **Ms.php:473** — SQL注入(whereRaw直接嵌变量)
5. **Video/Vip/Rating/Coin modify()** — 任意字段更新(无白名单)
6. **Api.php** — 公开API无认证(可修改全站域名)
7. **Video Model:83** — CDN鉴权密钥硬编码

### 🟡 中等 (7个)
8. **User.php:75** — 批量赋值漏洞
9. **User.php:80** — 密码裸MD5无盐
10. **common.php:22** — pswCrypt用DES crypt+65536种salt
11. **Base.php:78** — check_auth()缺return false
12. **Video.php:106** — FIND_IN_SET注入风险
13. **config/app.php** — 生产debug=true
14. **config/site.php** — API密钥仅3字符

### 🟢 轻微 (2个)
15. **Video.php:304** — 错误泄露ffmpeg命令
16. **common.php:48** — tree()函数$this致Fatal Error

---

## 代码优化
1. Video Model N+1查询(50条=150+额外查询)
2. CRUD代码大量重复 → BaseCrudController
3. Redis清除方法6个一样 → clearRedisKeys($prefix)
4. burnSubtitleProxy 130+行 → 拆分Service
5. 全局无CSRF防护
6. 禁用SSL验证
7. clearRedis无认证可公开调用

## 功能缺陷
1. 超管id==1硬编码
2. 登录无防暴力破解
3. 操作日志不完整
4. 全局输入过滤器未配置

## 综合评分: 3 / 10
