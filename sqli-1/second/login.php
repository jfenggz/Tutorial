<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config.php';

$db = db();

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . BASE_URL . '/second/login.php');
    exit;
}

$username = '';
$password = '';
$error    = '';
$message  = '';
$logged   = isset($_SESSION['uid']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? $_POST['username'] : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    // 登录同样是预处理，输入里带引号也能正常匹配到库里的那条记录
    $sql  = 'SELECT id, username, role FROM users WHERE username = ? AND password = ?';
    $stmt = $db->prepare($sql);

    if ($stmt === false) {
        $error = $db->error;
    } else {
        $stmt->bind_param('ss', $username, $password);
        $stmt->bind_result($id, $uname, $role);
        $stmt->execute();

        if ($stmt->fetch()) {
            $_SESSION['uid']      = $id;
            $_SESSION['username'] = $uname;
            $_SESSION['role']     = $role;
            $logged  = true;
            $message = '登录成功，当前账号：' . $uname . '（' . $role . '）';
        } else {
            $error = '用户名或密码不对。';
        }
        $stmt->close();
    }
}

page_head('二阶注入 · 登录');
?>
<h1>二阶注入 ② 登录</h1>
<p class="muted">
    这一步仍然是<strong>第一阶</strong>，用的还是预处理。
    真正的问题出在“后续某个页面把库里读出来的用户名当成可信数据，又拼回了 SQL”。
</p>

<?php hint("用户名要原样输入：<code>hacker' OR 1=1#</code>，密码是注册时填的那个。"); ?>

<?php if ($message !== ''): ?>
    <div class="ok"><?= h($message) ?></div>
    <p><a href="<?= BASE_URL ?>/second/change.php">进入“修改密码” →</a></p>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="bad"><?= h($error) ?></div>
<?php endif; ?>

<div class="sql">
    <div class="label">登录用的 SQL 模板</div>
    <pre>SELECT id, username, role FROM users WHERE username = ? AND password = ?</pre>
</div>

<?php login_form(BASE_URL . '/second/login.php', $username, $password); ?>

<?php if ($logged): ?>
    <p class="muted">当前会话：<?= h($_SESSION['username'] ?? '') ?>　<a href="<?= BASE_URL ?>/second/login.php?logout=1">退出登录</a></p>
<?php endif; ?>

<?php page_foot(); ?>
