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

// 很典型的“土过滤”：把空格、or、and、-- 全删掉，以为万事大吉
function naive_filter(string $s): string
{
    return str_ireplace([' ', "\t", "\n", "\r", 'or', 'and', '--'], '', $s);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = naive_filter(is_string($_POST['username'] ?? null) ? $_POST['username'] : '');
    $password = naive_filter(is_string($_POST['password'] ?? null) ? $_POST['password'] : '');

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

page_head('Level 3 · 过滤空格与关键字');
?>
<h1>Level 3 · 过滤了空格、or / and、--</h1>
<p class="muted">过滤规则：<code>str_ireplace([' ', "\t", "\n", "\r", 'or', 'and', '--'], '', $input)</code></p>

<?php hint("绕过：用 <code>||</code> 代替 or，用 <code>#</code> 代替 --，整条 payload 不需要空格 → <code>'||1=1#</code>"); ?>

<?php if ($sql !== ''): ?>
    <?php sql_box($sql, $sqlError); ?>
<?php endif; ?>

<?php if ($user !== null): ?>
    <div class="ok">登录成功：<?= h($user['username']) ?>　角色：<?= h($user['role']) ?></div>
<?php elseif ($message !== ''): ?>
    <div class="bad"><?= h($message) ?></div>
<?php endif; ?>

<?php login_form(BASE_URL . '/login/level3.php', $username, $password); ?>
<?php page_foot(); ?>
