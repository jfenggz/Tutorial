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

    // 密码在 PHP 层先 md5，再拼进 SQL
    $hash = md5($password);
    $sql  = "SELECT id, username, role FROM users WHERE username = '$username' AND password = '$hash'";

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

page_head('Level 4 · 密码先 md5');
?>
<h1>Level 4 · 密码在 PHP 里先 md5 再拼接</h1>
<p class="muted">密码框的输入会先变成 32 位十六进制串，再进入 SQL。</p>

<?php hint("用户名框填 <code>' OR 1=1 -- -</code> 有效；同样的 payload 填密码框无效 —— 它已经被 md5 撕碎了。"); ?>

<?php if ($sql !== ''): ?>
    <?php sql_box($sql, $sqlError); ?>
<?php endif; ?>

<?php if ($user !== null): ?>
    <div class="ok">登录成功：<?= h($user['username']) ?>　角色：<?= h($user['role']) ?></div>
<?php elseif ($message !== ''): ?>
    <div class="bad"><?= h($message) ?></div>
<?php endif; ?>

<?php login_form(BASE_URL . '/login/level4.php', $username, $password); ?>
<?php page_foot(); ?>
