# yelangawei/ins_front 深度代码审查
**日期:** 2026-03-30 | **技术栈:** React 18 + TypeScript + Vite + CryptoJS

## Bug (7个)
- 🔴 加密密钥全部硬编码前端(AES+IV+sign+图片解密)
- 🟡 事件监听器泄漏(每次play添加keydown不移除)
- 🟡 useEffect依赖userVip对象→可能无限循环
- 🟡 DPlayer销毁时序问题
- 🟡 解密失败无错误处理(JSON.parse抛异常)
- 🟢 volume范围错误(判断>10但实际0-1)

## 优化: VideoInfo 1000行→拆分; 200行注释代码; 路由重复
## 缺陷: 后端返回明文密码到前端; 右键禁用不清理; 购买无防抖
## 评分: 4/10
