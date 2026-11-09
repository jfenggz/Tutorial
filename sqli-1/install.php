<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

/*
 * 初始化数据库 + 环境自检
 * 直接浏览器访问 http://localhost/sqli/install.php
 * 注意：会 DROP 掉旧的 sqli_lab 库
 */

$messages = [];
$fatal    = '';
$checks   = [];
$conn     = @new mysqli(DB_HOST, DB_USER, DB_PASS);

if (!is_object($conn) || $conn->connect_errno) {
    $err   = is_object($conn) ? $conn->connect_error : '无法创建 mysqli 对象';
    $fatal = '连不上 MySQL（' . $err . '）。请确认小皮面板里 MySQL 已启动，并检查 config.php 的账号密码。';
} else {
    $sqlFile = __DIR__ . '/sql/init.sql';
    if (!is_file($sqlFile)) {
        $fatal = '找不到 sql/init.sql，请确认整个 sqli 目录都复制到了网站根目录。';
    } else {
        $conn->set_charset('utf8mb4');
        $sql = file_get_contents($sqlFile);
        if ($conn->multi_query($sql)) {
            while ($conn->more_results()) {
                $conn->next_result();
            }
            $messages[] = '数据库 sqli_lab 与演示数据已重新导入。';
        } else {
            $fatal = '导入失败（第 ' . $conn->errno . ' 号错误）：' . $conn->error;
        }
    }
}

if ($fatal === '' && $conn instanceof mysqli) {
    $conn->select_db(DB_NAME);

    $upload = UPLOAD_DIR;
    if (!is_dir($upload)) {
        @mkdir($upload, 0777, true);
    }

    // 实测：让 mysqld 往 upload 目录写一个文件
    $probeName = 'install_probe_' . bin2hex(random_bytes(4)) . '.txt';
    $probePath = sql_path($upload . '/' . $probeName);
    $writeOk   = @$conn->query("SELECT 'write-ok' INTO OUTFILE '" . $probePath . "'");
    $writeErr  = $writeOk === false ? $conn->error : '';
    if ($writeOk !== false) {
        @unlink($upload . '/' . $probeName);
    }

    $grants  = [];
    $hasFile = false;
    $res     = $conn->query('SHOW GRANTS FOR CURRENT_USER()');
    if ($res !== false) {
        while ($row = $res->fetch_row()) {
            $grants[] = $row[0];
            if (stripos($row[0], 'FILE') !== false || stripos($row[0], 'ALL PRIVILEGES') !== false) {
                $hasFile = true;
            }
        }
    }

    $sfp = $conn->query('SELECT @@secure_file_priv');
    $sfpVal = ($sfp !== false && ($r = $sfp->fetch_row()) !== null) ? $r[0] : null;

    $readLen = null;
    $readErr = '';
    $r = $conn->query("SELECT CHAR_LENGTH(LOAD_FILE('C:/Windows/win.ini'))");
    if ($r === false) {
        $readErr = $conn->error;
    } else {
        $row = $r->fetch_row();
        $readLen = $row === null ? null : $row[0];
    }

    $userCount = 0;
    $r = $conn->query('SELECT COUNT(*) FROM users');
    if ($r !== false && ($row = $r->fetch_row()) !== null) {
        $userCount = (int) $row[0];
    }

    $checks = [
        'PHP 版本'                => PHP_VERSION . (version_compare(PHP_VERSION, '7.1.0', '>=') ? '（可用）' : '（需要 7.1+）'),
        'MySQL 版本'              => $conn->server_info,
        '当前数据库账号'          => DB_USER . '@' . DB_HOST,
        'users 表行数'            => (string) $userCount,
        'secure_file_priv'        => $sfpVal === null ? 'NULL（文件导入导出被禁用）' : ($sfpVal === '' ? '空（不限制目录）' : $sfpVal),
        'FILE 权限'               => $hasFile ? '有' : '无',
        'upload 目录'             => sql_path($upload) . (is_dir($upload) ? '（存在）' : '（创建失败）'),
        '写文件实测'              => $writeOk === false ? '失败：' . $writeErr : '成功',
        '读 C:/Windows/win.ini'   => $readLen === null ? '失败（NULL）：路径不对、无权限或被 secure_file_priv 限制' : '成功，' . $readLen . ' 字节',
    ];

    @file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s'));
}

page_head('安装与自检');
?>
<h1>安装与自检</h1>

<?php if ($fatal !== ''): ?>
    <div class="bad"><?= h($fatal) ?></div>
    <p class="muted">检查顺序：小皮面板 MySQL 是否启动 → config.php 的 root 密码 → 是否整个 sqli 目录都在网站根目录下。</p>
<?php else: ?>
    <?php foreach ($messages as $m): ?>
        <div class="ok"><?= h($m) ?></div>
    <?php endforeach; ?>

    <table>
        <tr><th style="width:36%">检查项</th><th>结果</th></tr>
        <?php foreach ($checks as $k => $v): ?>
            <tr><td><?= h($k) ?></td><td><code><?= h($v) ?></code></td></tr>
        <?php endforeach; ?>
    </table>

    <?php if ($writeOk === false): ?>
        <div class="bad">
            写文件失败。Windows 上按顺序排查：<br>
            ① MySQL 的 <code>my.ini</code> 里 <code>[mysqld]</code> 下是否写了 <code>secure_file_priv=""</code>，改完在小皮面板重启 MySQL；<br>
            ② 路径是否用了正斜杠 <code>C:/phpstudy_pro/WWW/...</code>；<br>
            ③ 目标文件是否已存在（INTO OUTFILE 不覆盖）。
        </div>
    <?php endif; ?>

    <?php if (!$hasFile): ?>
        <div class="bad">当前账号没有 FILE 权限，<code>LOAD_FILE</code> 和 <code>INTO OUTFILE</code> 都会失败。用 root 账号，或执行 <code>GRANT FILE ON *.* TO '账号'@'localhost';</code></div>
    <?php endif; ?>

    <p><a href="<?= BASE_URL ?>/index.php">进入靶场 →</a>　<a href="<?= BASE_URL ?>/probe.php">环境自检 →</a></p>
<?php endif; ?>

<?php page_foot(); ?>
