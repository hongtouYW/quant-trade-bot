# hongtou820/apsk_store_android 深度审查
**日期:** 2026-04-01 | Java + Android SDK 36 + Retrofit2 + Firebase

## Bug (8个)
- 🔴 EncryptionInterceptor:81 NPE崩溃(body未null检查,Store版遗漏)
- 🔴 CryptoUtils AES密钥硬编码(三APP共享同一密钥)
- 🔴 ApiClient Retrofit单例非线程安全
- 🟡 peekBody(Long.MAX_VALUE)内存炸弹
- 🟡 生产日志泄露解密响应(密码/余额/token)
- 🟡 HTTP日志BODY级别
- 🟢 SingletonUtil非线程安全 / IP检查双重否定

## 缺陷: minifyEnabled false; cleartext允许; allowBackup; token明文存储; 签名验证注释掉; 加密全跳过
## 评分: 4/10
