# yelangawei/18toon 深度代码审查
**日期:** 2026-03-30 | **技术栈:** ThinkPHP 5.1 + MySQL + Redis + Elasticsearch

## Bug (8个)
- 🔴 明文密码存储
- 🔴 事务try-catch被注释掉(失败不回滚)
- 🔴 is_kl两个分支密钥路径一样(复制粘贴错误)
- 🔴 使用未定义变量$user
- 🔴 支付公私钥硬编码
- 🟡 金额校验精度丢失((int)$amount*100)
- 🟡 扣量用魔法数字id=13
- 🟡 N+1查询(12条=24次COUNT)

## 优化: 两套支付回调→统一; homepage循环查询; 死代码
## 缺陷: 订阅不减计数; 翻译回调无认证; 验证码session不生效; VIP不免费阅读
## 评分: 4/10
