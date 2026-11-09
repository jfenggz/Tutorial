<?php
declare(strict_types=1);

/* ============================================================
 * 公共配置与页面骨架（Windows + 小皮面板 / WAMP + PHP 7）
 * 部署位置：C:\phpstudy_pro\WWW\sqli
 * 如果放在别的目录，BASE_URL 会自动按 DOCUMENT_ROOT 推算
 * ============================================================ */

const DB_HOST = 'localhost';   // Windows 下 localhost 也走 TCP
const DB_USER = 'root';        // 小皮面板默认账号，按自己环境改
const DB_PASS = 'root';        // 默认密码，空密码就改成 ''
const DB_NAME = 'sqli_lab';

/** 当前应用在 URL 中的前缀：/sqli 或空字符串 */
function detect_base_url(): string
{
    $doc = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : '';
    $app = realpath(__DIR__);
    if ($doc !== '' && $doc !== false && $app !== false) {
        $doc = str_replace('\\', '/', $doc);
        $app = str_replace('\\', '/', $app);
        if (stripos($app, $doc) === 0) {
            return rtrim(substr($app, strlen($doc)), '/');
        }
    }
    return '';
}

define('BASE_URL', detect_base_url());
define('BASE_DIR', __DIR__);
define('UPLOAD_DIR', __DIR__ . '/upload');

// PHP 7 的 mysqli 默认就是返回 false，这里显式写出来，方便对照 PHP 8 的行为
mysqli_report(MYSQLI_REPORT_OFF);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function db(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }

    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!is_object($conn) || $conn->connect_errno) {
        $err = is_object($conn) ? $conn->connect_error : '无法创建 mysqli 对象';
        http_response_code(500);
        echo '<!doctype html><meta charset="utf-8"><h1>数据库连接失败</h1>';
        echo '<p style="color:#b42318">' . h($err) . '</p>';
        echo '<p>检查 config.php 里的账号密码，或先访问 <a href="' . BASE_URL . '/install.php">install.php</a> 初始化数据库。</p>';
        exit;
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

/** HTML 转义 */
function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 把 Windows 路径转成 SQL 里能直接用的形式：反斜杠改正斜杠，单引号翻倍 */
function sql_path(string $path): string
{
    return str_replace("'", "''", str_replace('\\', '/', $path));
}

function page_head(string $title): void
{
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' · SQLi Lab</title>';
    echo '<link rel="stylesheet" href="' . BASE_URL . '/style.css"></head><body>';
    echo '<header><a class="brand" href="' . BASE_URL . '/index.php">SQLi Lab</a>';
    echo '<nav><a href="' . BASE_URL . '/index.php">首页</a>';
    echo '<a href="' . BASE_URL . '/article.php?id=1">article.php</a>';
    echo '<a href="' . BASE_URL . '/second/register.php">二阶注入</a>';
    echo '<a href="' . BASE_URL . '/probe.php">环境自检</a></nav>';
    echo '</header><main>';
}

function page_foot(): void
{
    echo '</main><footer>仅限本机隔离靶场教学使用 · PHP ' . PHP_VERSION . '</footer></body></html>';
}

/** 把实际执行的 SQL 打印到页面上，这是本靶场最重要的教具 */
function sql_box(string $sql, ?string $error = null): void
{
    echo '<div class="sql"><div class="label">实际执行的 SQL</div><pre>' . h($sql) . '</pre>';
    if ($error !== null && $error !== '') {
        echo '<div class="err">' . h($error) . '</div>';
    }
    echo '</div>';
}

function login_form(string $action, string $username = '', string $password = '', string $button = '登录'): void
{
    echo '<form method="post" action="' . h($action) . '" class="card">';
    echo '<label>用户名 <input name="username" value="' . h($username) . '" autocomplete="off"></label>';
    echo '<label>密码 <input name="password" value="' . h($password) . '" autocomplete="off"></label>';
    echo '<button type="submit">' . h($button) . '</button></form>';
}

function hint(string $text): void
{
    echo '<p class="hint">' . $text . '</p>';
}
