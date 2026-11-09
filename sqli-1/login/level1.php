<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config.php';

$db = db();

$username = '';
$password = '';
$sql      = '';
$sqlError = null;
$user     = null;
$message  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? $_POST['username'] : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    // 漏洞点：输入原样拼进 SQL
    $sql = "SELECT id, username, role FROM users WHERE username = '$username' AND password = '$password'";

    $res = $db->query($sql);
    if ($res === false) {
        $sqlError = $db->error;
        $message  = 'SQL 执行出错，错误信息已回显。';
    } else {
        $user = $res->fetch_assoc();
        if ($user === null) {
            $message = '用户名或密码错误。';
        }
    }
}

page_head('Level 1 · 最朴素的拼接');
?>
<h1>Level 1 · 最朴素的字符串拼接</h1>
<p class="muted">登录成功与否只看这条查询有没有返回行。</p>

<?php hint("万能密码：<code>' OR 1=1 -- -</code>　万能用户名：<code>admin' -- -</code>（密码随便填）"); ?>

<?php if ($sql !== ''): ?>
    <?php sql_box($sql, $sqlError); ?>
<?php endif; ?>

<?php if ($user !== null): ?>
    <div class="ok">登录成功：<?= h($user['username']) ?>　角色：<?= h($user['role']) ?>　id=<?= h($user['id']) ?></div>
<?php elseif ($message !== ''): ?>
    <div class="bad"><?= h($message) ?></div>
<?php endif; ?>

<?php login_form(BASE_URL . '/login/level1.php', $username, $password); ?>
<?php page_foot(); ?>
