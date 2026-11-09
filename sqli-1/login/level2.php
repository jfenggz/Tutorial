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

    // 漏洞点没有变，只是每个条件被括号包了一层
    $sql = "SELECT id, username, role FROM users WHERE (username = '$username') AND (password = '$password')";

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

page_head('Level 2 · 括号包裹');
?>
<h1>Level 2 · 条件被括号包起来了</h1>
<p class="muted">payload 必须先闭合括号，否则引号闭合了、括号没闭合，SQL 直接语法错误。</p>

<?php hint("万能用户名：<code>admin') -- -</code>　万能密码：<code>') OR 1=1 -- -</code>"); ?>

<?php if ($sql !== ''): ?>
    <?php sql_box($sql, $sqlError); ?>
<?php endif; ?>

<?php if ($user !== null): ?>
    <div class="ok">登录成功：<?= h($user['username']) ?>　角色：<?= h($user['role']) ?></div>
<?php elseif ($message !== ''): ?>
    <div class="bad"><?= h($message) ?></div>
<?php endif; ?>

<?php login_form(BASE_URL . '/login/level2.php', $username, $password); ?>
<?php page_foot(); ?>
