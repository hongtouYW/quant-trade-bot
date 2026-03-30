# yelangawei/newav-api 深度代码审查

**日期:** 2026-03-30
**技术栈:** ThinkPHP 5.1 + MySQL + Redis (视频平台API)

---

## 发现的 Bug (18个)

### 🔴 严重 (7个)
1. **User.php:85** — 明文密码存储(ori_password明文存DB+明文比对)
2. **Notify.php:22** — 支付回调无签名验证(可伪造支付成功)
3. **Notify.php:14** — Notify继承Controller非Base(绕过签名校验)
4. **config/lock.php** — AES/token/sign密钥全部硬编码
5. **Vip.php:257** — 支付商户partnerId+Md5key硬编码
6. **Test.php:16** — 任何人可清空Redis(/test/clearRedis无认证)
7. **Video.php:184** — getUid()不检查token过期

### 🟡 中等 (7个)
8. collect()死代码 9. 事务内return未rollback 10. CDN密钥硬编码
11. SQL原生拼接 12. addLog()同步调ipinfo.io阻塞
13. doCallback()并发竞态 14. returnUrl重复赋值(调试残留)

### 🟢 轻微 (4个)
15-18. token校验不完整、逻辑写法混乱、重复代码、邀请码碰撞

---

## 代码优化 (10个)
1. Video Model极严重N+1(20条/页=160+SQL)
2-10. 重复分支、CORS全放开、debug=true、SSL禁用、分页硬编码、伪造评分、$this致命错误、catch吞异常

## 功能缺陷 (7个)
1. Comment控制器全空方法 2. 路由极少 3-4. 积分/金币并发超扣
5. 金额int比对丢精度 6. 无XSS过滤 7. 每次更新token造成行锁

## 综合评分: 3.5 / 10
