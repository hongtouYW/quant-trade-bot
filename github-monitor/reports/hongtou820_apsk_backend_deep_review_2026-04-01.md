# hongtou820/apsk_backend 深度代码审查

**日期:** 2026-04-01
**技术栈:** Laravel 12 + PHP 8.2 + MySQL + Sanctum + Firebase + 多支付网关

---

## 发现的 Bug (12个)

### 🔴 严重 (5个)
1. **api.php:67** — 动态表名拼接无白名单，攻击者可访问任意表
2. **payment_helpers.php:19** — 余额更新竞态条件，非事务read-then-write
3. **api.php:189** — 未认证端点可修改手机绑定(bind/phone/random在auth外)
4. **api.php:171** — 游戏日志同步完全公开无认证
5. **ShopController.php:71** — 密码可逆加密存储(Crypt非Hash)，APP_KEY泄露=全暴露

### 🟡 中等 (5个)
6. 登录后自动重置密码但不返回新密码
7. payment_helpers default分支$response未定义
8. database_helpers $log_api可能未定义
9. Log::info参数类型错误
10. 返回类型不一致(array vs null)

### 🟢 轻微 (2个)
11. rand()生成密码非密码学安全
12. GeoLocation用HTTP非HTTPS

---

## 代码优化
1. Auth检查每个方法重复→中间件 2. 28个helpers全局加载 3. 无Rate Limiting
4. 错误信息泄露$e->getMessage() 5. N+1查询 6. debug路由残留 7. ID生成并发重复

## 功能缺陷
1. 支付回调无签名验证中间件 2. IDOR漏洞(传任意ID访问他人数据)
3. logout后日志失败 4. Refresh Token永不过期(2038) 5. 无CSRF

## 综合评分: 4 / 10
