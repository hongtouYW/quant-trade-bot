# hongtou820/apsk_h5 深度代码审查
**日期:** 2026-04-01 | Next.js 15 + React 19 + Redux Toolkit + Zustand + crypto-js

## Bug (11个)
- 🔴 AES密钥硬编码 `SECRET_KEY = "6zyEmxbE9L"`
- 🔴 OTP成功用toast.error显示(红色错误提示)
- 🔴 PIN绑定完全没调后端API! (TODO未实现已发布)
- 🟡 UIProvider引用未定义toast/t→白屏
- 🟡 register shopCode条件用错变量
- 🟡 字符串模板bug `${"${withdraw}"}` 输出字面量
- 🟡 toast.error第二参数类型错误
- 🟢 重复setCookie/var声明/图片策略/命名typo

## 优化: 830行50%注释代码; Redux+Zustand混用; 密码规则不统一
## 缺陷: PIN页空壳; 密码强度验证被注释; 充值轮询被注释; 备份文件残留
## 评分: 5/10
