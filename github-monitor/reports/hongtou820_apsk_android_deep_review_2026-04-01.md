# hongtou820/apsk_android (User) 深度审查
**日期:** 2026-04-01 | Java + Android SDK 36 + Retrofit2 + Firebase + Biometric

## Bug (10个)
- 🔴 CryptoUtils同样硬编码AES密钥
- 🔴 Retrofit单例非线程安全
- 🔴 Gson TypeAdapter覆盖! CreditListDeserializer被PointListDeserializer覆盖
- 🟡 peekBody/null拼接/日志泄露/无用JSON遍历
- 🟡 onResume recreate()无防重入→可能无限循环(Store版已修复)
- 🟡 硬编码英文"Session Expired"未用strings.xml
- 🟢 60秒超时过长

## 缺陷: minifyEnabled false; cleartext; allowBackup; token明文; 签名验证注释掉; 加密全跳过
## 评分: 3.5/10
