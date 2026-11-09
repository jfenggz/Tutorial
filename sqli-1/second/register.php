<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config.php';

$db = db();

$username = '';
$password = '';
$error    = '';
$message  = '';
$stored   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? $_POST['username'] : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if ($username === '' || $password === '') {
        $error = '用户名和密码都要填。';
    } else {
        // 第一次入库：预处理，payload 在这里只是普通字符串
        $sql  = 'INSERT INTO users (username, password, role) VALUES (?, ?, ?)';
        $stmt = $db->prepare($sql);
        $role = 'user';

        if ($stmt === false) {
            $error = $db->error;
        } else {
            $stmt->bind_param('sss', $username, $password, $role);
            if ($stmt->execute()) {
                $newId = $stmt->insert_id;

                // 从库里把刚写入的值读出来，看看它到底存成了什么
                $q = $db->prepare('SELECT username FROM users WHERE id = ?');
                $q->bind_param('i', $newId);
                $q->bind_result($storedName);
                $q->execute();
                $q->fetch();
                $stored = $storedName;
                $q->close();

                $message = '注册成功。账号已写入数据库，id = ' . $newId . '。';
            } else {
                $error = $db->errno === 1062 ? '这个用户名已经被注册了。' : $stmt->error;
            }
            $stmt->close();
        }
    }
}

page_head('二阶注入 · 注册');
?>
<h1>二阶注入 ① 注册</h1>
<p class="muted">
    这一步是<strong>第一阶</strong>：用户输入被预处理语句写入数据库，没有任何拼接。
    特殊字符会原封不动地存进去——包括单引号。
</p>

<?php hint("注册一个用户名为 <code>hacker' OR 1=1#</code> 的账号，密码随意。它能正常注册，也能正常登录。"); ?>

<?php if ($message !== ''): ?>
    <div class="ok"><?= h($message) ?></div>
    <?php if ($stored !== null): ?>
        <div class="sql">
            <div class="label">数据库里实际存的值（用方括号标出首尾）</div>
            <pre>[<?= h($stored) ?>]</pre>
        </div>
        <p class="muted">注意：单引号是完整存进去的，没有被转义成 <code>\'</code>。危险不在这里，在下一步。</p>
    <?php endif; ?>
    <p><a href="<?= BASE_URL ?>/second/login.php">用这个账号登录 →</a></p>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="bad"><?= h($error) ?></div>
<?php endif; ?>

<div class="sql">
    <div class="label">入库用的 SQL 模板（结构固定）</div>
    <pre>INSERT INTO users (username, password, role) VALUES (?, ?, ?)</pre>
</div>

<?php login_form(BASE_URL . '/second/register.php', $username, $password, '注册'); ?>

<h2>现有的演示账号</h2>
<pre><?php
$res = $db->query('SELECT id, username, role FROM users ORDER BY id');
while ($res !== false && ($row = $res->fetch_assoc())) {
    echo h($row['id'] . '  ' . $row['username'] . '  ' . $row['role']) . "\n";
}
?></pre>

<?php page_foot(); ?>
