<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config.php';

$db = db();

$done = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 清掉演示过程中注册的账号（种子账号 id 固定为 1~3）
    if (!$db->query('DELETE FROM users WHERE id > 3')) {
        $error = $db->error;
    } else {
        $db->query('ALTER TABLE users AUTO_INCREMENT = 4');

        // 恢复三个种子账号的密码和角色
        $seeds = [
            ['admin', 'admin888', 'admin'],
            ['alice', 'alice123', 'user'],
            ['bob',   'bob@2026', 'user'],
        ];

        $stmt = $db->prepare('UPDATE users SET password = ?, role = ? WHERE username = ?');
        foreach ($seeds as $s) {
            $stmt->bind_param('sss', $s[1], $s[2], $s[0]);
            if (!$stmt->execute()) {
                $error = $stmt->error;
                break;
            }
        }
        $stmt->close();

        $_SESSION = [];
        session_destroy();
        $done = true;
    }
}

page_head('重置演示数据');
?>
<h1>重置演示数据</h1>
<p class="muted">删除 id &gt; 3 的测试账号，并把 admin / alice / bob 的密码恢复成初始值。</p>

<?php if ($done): ?>
    <div class="ok">已重置。admin / admin888，alice / alice123，bob / bob@2026。</div>
    <p><a href="<?= BASE_URL ?>/second/register.php">重新开始二阶注入 →</a></p>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="bad"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= BASE_URL ?>/second/reset.php" class="card">
    <button type="submit">确认重置</button>
</form>

<h2>当前账号</h2>
<pre><?php
$res = $db->query('SELECT id, username, password, role FROM users ORDER BY id');
while ($res !== false && ($row = $res->fetch_assoc())) {
    echo h($row['id'] . '  ' . $row['username'] . '  ' . $row['password'] . '  ' . $row['role']) . "\n";
}
?></pre>

<p><a href="<?= BASE_URL ?>/index.php">返回首页</a></p>
<?php page_foot(); ?>
