# yelangawei/grab_back 深度代码审查

**日期:** 2026-03-27
**技术栈:** PHP 7.x + ThinkPHP 5.1 + MySQL (阿里云RDS) + Redis + Layui

---

## 发现的 Bug (15个)

### 🔴 严重 (5个)

#### 1. config/database.php — 数据库凭证硬编码
- 明文暴露阿里云RDS主机名/用户名/密码
- 代码: `'password' => Env::get('database.password', '2pKMP!#YlM')`
- **修复:** 从Git删除默认值，仅用.env，密码加入.gitignore

#### 2. Upload.php — 无身份认证的上传接口
- 不继承Base类，仅用3字符api_key("x18")保护
- 任何人暴力破解即可上传/修改视频数据
- **修复:** 使用长随机key+IP白名单

#### 3. Video/Platforms/Tags/Banner modify() — 任意字段修改漏洞 (Mass Assignment)
```php
$data[$post['field']] = $post['value'];
$row->save($data);
```
- 攻击者可指定任意数据库字段修改
- **修复:** 添加字段白名单: `if (!in_array($post['field'], $allowFields)) abort();`

#### 4. Base.php check_auth() — 权限检查缺少 return false
- 路由不在权限列表时隐式返回 null
- **修复:** 末尾显式 `return false;`

#### 5. Report.php — 无认证暴露营收统计接口
- 敏感财务数据仅用简单api_key保护

### 🟡 中等 (7个)

#### 6. Video.php index() — SQL注入风险
```php
$where['_string'] = "instr(CONCAT(',', tags, ','), ','.$param['tag'].',')";
```
- 用户输入直接拼接SQL
- **修复:** 使用参数绑定

#### 7. User.php edit() — 批量赋值漏洞
```php
$list = request()->post();
$result = model('user')->update($list);
```
- 攻击者可修改 vip_end_time/coin 等任意字段
- **修复:** 使用 `request()->only(['username','password','status'])`

#### 8. common.php tree() — $this 在普通函数中使用
```php
function tree($array, $pid = 0, $level = 1) {
    $this->tree($array, $v['id'], $level + 1);  // Fatal Error!
}
```
- 普通函数不能用 $this，调用直接崩溃
- **修复:** 改为 `tree($array, $v['id'], $level + 1);`

#### 9. common.php pswCrypt() — 弱密码哈希
- md5 + DES-based crypt，盐从md5截取（确定性的）
- **修复:** 改用 `password_hash($psw, PASSWORD_BCRYPT)`

#### 10. Tags.php index() — 变量名不匹配导致搜索失效
```php
if(!empty($param['wd'])){
    $where[] = ['name','like','%'.$param['name'].'%'];  // 应是 $param['wd']
}
```
- 搜索功能完全失效

#### 11. Banner.php — $where 未初始化
- `$param['wd']` 为空时 `$where` 变量未定义

#### 12. Video Model saveData() — 缺少新增逻辑
- `$data['id']` 为空时 `$res` 未定义

### 🟢 轻微 (3个)

#### 13. Login.php clearRedis() — 无认证的缓存清除接口
- 任何人访问 /login/clearRedis 即可清空 Redis

#### 14. app.php — 生产环境调试模式开启
- `app_debug => true` 暴露SQL/堆栈信息

#### 15. Login.php — 谷歌验证码被注释掉
- MFA验证逻辑被注释，形同虚设

---

## 代码优化建议

1. **Index.php welcome()** — 仪表盘 12+ 冗余查询，每个带 whereExists 子查询
   - 建议: 用 2 条聚合 SQL 替代，按 agent_code 分组

2. **全局** — 大量重复分页逻辑，每个控制器 index() 相同
   - 建议: 抽取 BaseListController/Trait

3. **全局** — modify() 方法全复制粘贴 (Video/Platforms/Tags/Banner)
   - 建议: Base 控制器实现通用 modify()

4. **Video Model** — getThumbAttr/getVideoUrlAttr 每次查数据库
   - 列表10条 = 20次额外查询 (N+1 问题)
   - 建议: 静态缓存或预加载

---

## 功能缺陷

1. **全局** — 缺少 CSRF 防护
2. **全局** — 无登录频率限制，可暴力破解
3. **全局** — Session 管理不安全，无 regenerate/超时
4. **User.php add_coin()** — 充值无金额校验，可传负数
5. **Admin.php** — 密码无强度校验
6. **全局** — 审计日志不完整，del() 操作未记录
7. **Upload.php** — 无 URL 格式校验

---

## 综合评分: 3.5 / 10

| 维度 | 评分 | 说明 |
|------|------|------|
| 安全性 | 2/10 | 数据库密码泄露+多处注入+Mass Assignment |
| 代码质量 | 4/10 | 大量复制粘贴+变量名错误 |
| 可维护性 | 4/10 | 缺乏抽象，改一处需改多处 |
| 工程化 | 3/10 | 无测试无CI，调试模式开启 |
| 性能 | 5/10 | N+1查询，冗余SQL |

**紧急建议:** 立即修复数据库凭证泄露和任意字段修改漏洞
