<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$db = db();

// 数字型注入点：$id 没有任何引号包裹，也没有过滤
$id  = isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '1';
$sql = "SELECT id, title, content FROM articles WHERE id = $id";

$res = $db->query($sql);

page_head('文章详情 · 注入点');
?>
<h1>文章详情</h1>
<p class="muted">
    单引号在这里没用，因为 <code>$id</code> 是数字型、没有引号包裹。
    固定 3 列：<code>id, title, content</code>。
</p>

<?php hint("先测列数：<code>?id=1 ORDER BY 4</code> 报错、<code>ORDER BY 3</code> 正常 → 3 列。再用 <code>?id=-1 UNION SELECT 1,2,3</code> 找显示位。"); ?>

<?php sql_box($sql, $res === false ? $db->error : null); ?>

<?php if ($res === false): ?>
    <div class="bad">查询失败。错误信息本身就是注入的回显渠道。</div>
<?php elseif ($res->num_rows === 0): ?>
    <p class="muted">没有匹配的文章。</p>
<?php else: ?>
    <p class="muted">返回 <?= $res->num_rows ?> 行</p>
    <?php while ($row = $res->fetch_assoc()): ?>
        <article>
            <h2>#<?= h($row['id']) ?>　<?= h($row['title']) ?></h2>
            <pre class="content"><?= h($row['content']) ?></pre>
        </article>
    <?php endwhile; ?>
<?php endif; ?>

<?php page_foot(); ?>
