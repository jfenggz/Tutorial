<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config.php';

$db = db();

if (empty($_SESSION['uid'])) {
    page_head('二阶注入 · 修改密码');
    ?>
    <h1>二阶注入 ③ 修改密码</h1>
    <div class="bad">还没有登录。请先用注册的账号登录。</div>
    <p><a href="<?= BASE_URL ?>/second/login.php">去登录 →</a></p>
    <?php
    page_foot();
    exit;
}

// 第一阶仍然安全：按 id 预处理，取出“规范”的用户名
$stmt = $db->prepare('SELECT username FROM users WHERE id = ?');
$stmt->bind_param('i', $_SESSION['uid']);
$stmt->bind_result($storedName);
$stmt->execute();
$found = $stmt->fetch();
$storedName = $storedName === null ? '' : (string) $storedName;
$stmt->close();

if (!$found) {
    $_SESSION = [];
    session_destroy();
    page_head('二阶注入 · 修改密码');
    echo '<div class="bad">当前会话对应的用户已不存在（可能被重置了），请重新登录。</div>';
    echo '<p><a href="' . BASE_URL . '/second/login.php">去登录 →</a></p>';
    page_foot();
    exit;
}

$newPassword = '';
$message     = '';
$error       = '';
$sql         = '';
$affected    = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if ($newPassword === '') {
        $error = '新密码不能为空。';
    } else {
        // 第二阶：从数据库读出来的用户名被直接拼进 SQL
        $sql = "UPDATE users SET password = '$newPassword' WHERE username = '$storedName'";

        $ok = $db->query($sql);
        if ($ok === false) {
            $error = $db->error;
        } else {
            $affected = $db->affected_rows;
            $message  = '执行完成，受影响行数：' . $affected;
        }
    }
}

page_head('二阶注入 · 修改密码');
?>
<h1>二阶注入 ③ 修改密码</h1>
<p class="muted">当前登录：<strong><?= h($_SESSION['username']) ?></strong></p>

<?php hint("如果登录的是 <code>hacker' OR 1=1#</code>，把密码改成 <code>pwned123</code>，然后退出，用 <code>admin / pwned123</code> 登录试试。"); ?>

<div class="sql">
    <div class="label">从数据库取出来的用户名（方括号标出首尾，它现在被当成“可信数据”）</div>
    <pre>[<?= h($storedName) ?>]</pre>
</div>

<?php if ($message !== ''): ?>
    <div class="<?= $affected > 1 ? 'bad' : 'ok' ?>">
        <?= h($message) ?>
        <?php if ($affected > 1): ?>
            <br>一条 UPDATE 改掉了 <?= h((string) $affected) ?> 行——所有账号的密码都变成了同一个值。
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="bad"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($sql !== ''): ?>
    <?php sql_box($sql, $error !== '' ? $error : null); ?>
<?php endif; ?>

<form method="post" action="<?= BASE_URL ?>/second/change.php" class="card">
    <label>新密码 <input name="password" value="" autocomplete="off"></label>
    <button type="submit">修改密码</button>
</form>

<p class="muted">
    <a href="<?= BASE_URL ?>/second/login.php?logout=1">退出登录</a>　
    <a href="<?= BASE_URL ?>/second/reset.php">重置演示数据</a>
</p>

<h2>为什么叫“二阶”</h2>
<ol>
    <li><strong>第一阶</strong>（注册）：<code>INSERT ... VALUES (?, ?, ?)</code>，payload 只是一串字符，安全入库。</li>
    <li><strong>中间态</strong>：数据库里躺着 <code>hacker' OR 1=1#</code>，看起来无害。</li>
    <li><strong>第二阶</strong>（改密码）：代码认为“库里的数据是干净的”，直接拼进 <code>WHERE username = '...'</code>，payload 在这一刻生效。</li>
</ol>

<?php page_foot(); ?>
