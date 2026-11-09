<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config.php';

$db = db();

$username = '';
$password = '';
$user     = null;
$message  = '';
$sqlError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? $_POST['username'] : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    // 正确写法：SQL 结构固定，数据只通过占位符传入
    $sql  = 'SELECT id, username, role FROM users WHERE username = ? AND password = ?';
    $stmt = $db->prepare($sql);

    if ($stmt === false) {
        $sqlError = $db->error;
    } else {
        $stmt->bind_param('ss', $username, $password);
        $stmt->bind_result($uid, $uname, $urole);
        if ($stmt->execute()) {
            $user = $stmt->fetch() ? ['id' => $uid, 'username' => $uname, 'role' => $urole] : null;
            if ($user === null) {
                $message = '用户名或密码错误。';
            }
        } else {
            $sqlError = $stmt->error;
        }
    }
}

page_head('正确写法 · 预处理');
?>
<h1>对照：同样的 payload，预处理为什么打不穿</h1>
<p class="muted">占位符 <code>?</code> 只能填“值”，不能改变 SQL 的结构。整段 <code>' OR 1=1 -- -</code> 会被当成一个普通字符串去和 username 比较。</p>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <div class="sql">
        <div class="label">SQL 模板（结构固定）</div>
        <pre>SELECT id, username, role FROM users WHERE username = ? AND password = ?</pre>
        <div class="label" style="border-top:1px solid #e2e7ee">绑定进去的值</div>
        <pre>username = <?= h(var_export($username, true)) ?>

password = <?= h(var_export($password, true)) ?></pre>
    </div>
<?php endif; ?>

<?php if ($sqlError !== null): ?>
    <div class="bad"><?= h($sqlError) ?></div>
<?php elseif ($user !== null): ?>
    <div class="ok">登录成功：<?= h($user['username']) ?></div>
<?php elseif ($message !== ''): ?>
    <div class="bad"><?= h($message) ?></div>
<?php endif; ?>

<?php login_form(BASE_URL . '/login/safe.php', $username, $password); ?>

<h2>真正的生产写法还差两步</h2>
<ul>
    <li>密码不要明文存：注册用 <code>password_hash()</code>，登录用 <code>password_verify()</code>，SQL 里只按 username 取一行。</li>
    <li>即使全部用预处理，<code>ORDER BY ?</code>、表名、列名仍然不能绑定，必须走白名单。</li>
</ul>
<?php page_foot(); ?>
