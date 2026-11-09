<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config.php';

$db = db();

if (empty($_SESSION['uid'])) {
    page_head('二阶注入 · 正确写法');
    echo '<div class="bad">还没有登录。</div>';
    echo '<p><a href="' . BASE_URL . '/second/login.php">去登录 →</a></p>';
    page_foot();
    exit;
}

$stmt = $db->prepare('SELECT username FROM users WHERE id = ?');
$stmt->bind_param('i', $_SESSION['uid']);
$stmt->bind_result($storedName);
$stmt->execute();
$found = $stmt->fetch();
$storedName = $storedName === null ? '' : (string) $storedName;
$stmt->close();

$message  = '';
$error    = '';
$affected = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $found) {
    $newPassword = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if ($newPassword === '') {
        $error = '新密码不能为空。';
    } else {
        // 修复点：库里的数据同样不可信，照样走占位符
        $sql  = 'UPDATE users SET password = ? WHERE username = ?';
        $stmt = $db->prepare($sql);

        if ($stmt === false) {
            $error = $db->error;
        } else {
            $stmt->bind_param('ss', $newPassword, $storedName);
            if ($stmt->execute()) {
                $affected = $stmt->affected_rows;
                $message  = '执行完成，受影响行数：' . $affected;
            } else {
                $error = $stmt->error;
            }
            $stmt->close();
        }
    }
}

page_head('二阶注入 · 正确写法');
?>
<h1>对照：二阶注入的修复点在拼接处</h1>
<p class="muted">
    入口的预处理救不了第二阶。真正要改的是“把库里的值拼进 SQL”这一行——
    它也必须用占位符，或者至少重新转义。
</p>

<?php if ($message !== ''): ?>
    <div class="ok"><?= h($message) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="bad"><?= h($error) ?></div>
<?php endif; ?>

<div class="sql">
    <div class="label">执行用的 SQL 模板</div>
    <pre>UPDATE users SET password = ? WHERE username = ?</pre>
    <div class="label" style="border-top:1px solid #e2e7ee">绑定的值</div>
    <pre>password = <?= h(var_export(isset($newPassword) ? $newPassword : '', true)) ?>

username = <?= h(var_export($storedName, true)) ?></pre>
</div>

<form method="post" action="<?= BASE_URL ?>/second/safe.php" class="card">
    <label>新密码 <input name="password" value="" autocomplete="off"></label>
    <button type="submit">修改密码</button>
</form>

<p class="muted">
    用 <code>hacker' OR 1=1#</code> 登录后在这里改密码，受影响行数只会是 1——
    用户名被完整地当成一个字符串去比较。<br>
    对比：<a href="<?= BASE_URL ?>/second/change.php">漏洞版修改密码</a>　
    <a href="<?= BASE_URL ?>/second/reset.php">重置演示数据</a>
</p>

<?php page_foot(); ?>
