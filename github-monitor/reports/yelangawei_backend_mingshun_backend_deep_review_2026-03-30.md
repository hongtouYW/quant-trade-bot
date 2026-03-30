# yelangawei/backend_mingshun [backend] 深度审查
**日期:** 2026-03-30 | Laravel 10 + PHP 8.1 + MySQL + Redis + Sanctum

## Bug (7个)
- 🔴 明文密码存储(plain_password字段，专门加migration)
- 🔴 API认证条件逻辑反转(&&应为||，任意一个参数存在即放行)
- 🔴 5个API端点在认证中间件外(photo/videoImport等)
- 🟡 timestamp无过期验证(replay attack)
- 🟡 SSL禁用; IP可伪造
- 🟢 死代码

## 优化: TokenLogs N+1; VideoApi N+1; 原生curl→Laravel Http
## 缺陷: changeStatus无权限; video()无输入验证; hash弱比较
## 评分: 4/10
