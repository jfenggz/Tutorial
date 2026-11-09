<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$db = db();

/**
 * 跑一条单值查询，返回 [值, 错误]
 */
function one(mysqli $db, string $sql): array
{
    $res = $db->query($sql);
    if ($res === false) {
        return [null, $db->error];
    }
    $row = $res->fetch_row();
    return [$row === null ? null : $row[0], null];
}

$info = [];
$info['PHP 版本']            = PHP_VERSION;
$info['MySQL 版本']          = $db->server_info;
$info['当前数据库账号']      = one($db, 'SELECT CURRENT_USER()')[0];
$info['secure_file_priv']    = one($db, 'SELECT @@secure_file_priv')[0];
$info['datadir']             = one($db, 'SELECT @@datadir')[0];
$info['max_allowed_packet']  = one($db, 'SELECT @@max_allowed_packet')[0];
$info['站点目录']            = sql_path(BASE_DIR);
$info['写文件目标目录']      = sql_path(UPLOAD_DIR);

$sfp = $info['secure_file_priv'];
$info['secure_file_priv'] = $sfp === null
    ? 'NULL（文件导入导出已禁用）'
    : ($sfp === '' ? '空字符串（不限制目录）' : $sfp);

$info['目标目录状态'] = is_dir(UPLOAD_DIR)
    ? (is_writable(UPLOAD_DIR) ? '存在，PHP 可写' : '存在，PHP 不可写')
    : '目录不存在';

// FILE 权限
$grants  = [];
$hasFile = false;
$res     = $db->query('SHOW GRANTS FOR CURRENT_USER()');
if ($res !== false) {
    while ($row = $res->fetch_row()) {
        $grants[] = $row[0];
        if (stripos($row[0], 'FILE') !== false || stripos($row[0], 'ALL PRIVILEGES') !== false) {
            $hasFile = true;
        }
    }
}

// 实测写：让 mysqld 往 upload 目录落一个文件
$probeFile  = UPLOAD_DIR . '/probe_' . bin2hex(random_bytes(4)) . '.txt';
$writeTest  = $db->query("SELECT 'probe-ok' INTO OUTFILE '" . sql_path($probeFile) . "'");
$writeError = $writeTest === false ? $db->error : '';
if ($writeTest !== false) {
    @unlink($probeFile);
}

// 实测读：Windows 系统文件 + 站点自己的配置
[$winLen, $winErr] = one($db, "SELECT CHAR_LENGTH(LOAD_FILE('C:/Windows/win.ini'))");
$configPath = sql_path(__DIR__ . '/config.php');
[$cfgLen, $cfgErr] = one($db, "SELECT CHAR_LENGTH(LOAD_FILE('" . $configPath . "'))");

page_head('环境自检');
?>
<h1>环境自检</h1>
<p class="muted">这一页不做注入，只是把注入时要用到的变量提前查出来。<code>article.php</code> 里能用 UNION 查到的，这里基本都能看到。</p>

<table>
    <tr><th style="width:34%">项目</th><th>值</th></tr>
    <?php foreach ($info as $k => $v): ?>
        <tr>
            <td><?= h($k) ?></td>
            <td><code><?= h($v === null ? 'NULL' : $v) ?></code></td>
        </tr>
    <?php endforeach; ?>
    <tr>
        <td>FILE 权限</td>
        <td><code><?= $hasFile ? '有（LOAD_FILE / INTO OUTFILE 可用）' : '无（文件读写会失败）' ?></code></td>
    </tr>
    <tr>
        <td>写文件实测</td>
        <td><code><?= $writeError === '' ? '成功（upload 目录可被 mysqld 写入）' : '失败：' . h($writeError) ?></code></td>
    </tr>
    <tr>
        <td>读 <code>C:/Windows/win.ini</code></td>
        <td><code><?= $winLen === null ? '失败（NULL）：路径不对、无权限或被 secure_file_priv 限制' : '成功，' . h((string) $winLen) . ' 字节' ?></code></td>
    </tr>
    <tr>
        <td>读站点 <code>config.php</code></td>
        <td><code><?= $cfgLen === null ? '失败（NULL）：路径不对、无权限或被 secure_file_priv 限制' : '成功，' . h((string) $cfgLen) . ' 字节' ?></code></td>
    </tr>
</table>

<h2>Windows 上最容易卡住的几件事</h2>
<ul>
    <li><code>secure_file_priv</code> 是 <code>NULL</code> → 文件导入导出整体禁用。改 <code>my.ini</code> 的 <code>[mysqld]</code> 段：<code>secure_file_priv=""</code>，再重启 MySQL。</li>
    <li>是一个目录 → 只能读写这个目录，写不到网站目录。</li>
    <li>路径里不要用单个反斜杠。SQL 字符串里 <code>\</code> 是转义符，统一写正斜杠：<code>C:/phpstudy_pro/WWW/sqli/upload/cmd.php</code>。</li>
    <li>写文件报 <code>File exists</code> → INTO OUTFILE 不覆盖，换个文件名。</li>
    <li>读文件返回 NULL → 文件不存在、没 FILE 权限、被 <code>secure_file_priv</code> 限制，或文件超过 <code>max_allowed_packet</code>。</li>
    <li>写成功但访问 403 → 看文件权限；写成功却显示源码 → Apache 没把 PHP 模块挂上。</li>
</ul>

<h2>当前账号的授权</h2>
<pre><?= h(implode("\n", $grants)) ?></pre>

<?php page_foot(); ?>
