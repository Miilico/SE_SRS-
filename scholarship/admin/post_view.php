<?php
$adminHeaderBootstrapOnly = true;
require __DIR__ . "/../header.php";
unset($adminHeaderBootstrapOnly);
require_once __DIR__ . "/../file_helpers.php";

// 取得公告 ID
$id = isset($_GET['id']) ? $_GET['id'] : die("未指定公告");

try {
    $stmt = $pdo->prepare("SELECT * FROM announcement WHERE ID = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    
    if (!$post) {
        die("找不到該公告");
    }
    $files = fetch_uploaded_files($pdo, 1, "announcement_id", $post["id"]);
} catch (PDOException $e) {
    die("查詢失敗：" . $e->getMessage());
}
$pageTitle = $post['title'];
$activeNav = "post_management.php";
?>
<?php require __DIR__ . "/../header.php"; ?>

    <div class="post-container">
        <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>
        <div class="post-meta">
            發佈日期：<?php echo $post['ADATE']; ?> <?php echo $post['ATIME']; ?> | 
            管理員：<?php echo htmlspecialchars($post['AID']); ?>
        </div>
        <div class="post-content"><?php echo htmlspecialchars($post['CONTENT']); ?></div>

        <?php if (!empty($files)): ?>
            <div class="admin-section-gap">
                <strong>附件：</strong>
                <ul>
                    <?php foreach ($files as $file): ?>
                        <li>
                            <a href="/scholarship/file_view.php?id=<?php echo urlencode($file["id"]); ?>">
                                <?php echo htmlspecialchars($file["original_name"]); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="admin-actions admin-actions-bottom">
            <a href="post_management.php">← 返回列表</a>
        </div>
    </div>
</main>
</body>
</html>
