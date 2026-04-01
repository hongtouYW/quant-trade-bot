# hongtou820/apsk_manager_android 深度审查
**日期:** 2026-04-01 | Java + Android SDK 36 + Retrofit2 + Firebase + CameraX

## Bug (7个)
- 🔴 CryptoUtils同样硬编码AES密钥
- 🔴 Retrofit单例非线程安全
- 🟡 optString null拼接→IV变"RWf23muavYnull"→解密出垃圾
- 🟡 peekBody(Long.MAX_VALUE); 日志泄露; HTTP BODY级别
- 🟢 同URL两个Deserializer重载

## 缺陷: token refresh无并发控制; deeplink exported未验证; 与Store 80%代码重复
## 评分: 4/10
