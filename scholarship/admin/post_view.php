<?php
$adminHeaderBootstrapOnly = true;
require __DIR__ . "/header.php";
unset($adminHeaderBootstrapOnly);

// 取得公告 ID
$id = isset($_GET['id']) ? $_GET['id'] : die("未指定公告");

try {
    $stmt = $pdo->prepare("SELECT * FROM announcement WHERE ID = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    
    if (!$post) {
        die("找不到該公告");
    }
} catch (PDOException $e) {
    die("查詢失敗：" . $e->getMessage());
}
$pageTitle = $post['title'];
$activeNav = "post_management.php";
?>
<?php require __DIR__ . "/header.php"; ?>

    <div class="post-container">
        <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>
        <div class="post-meta">
            發佈日期：<?php echo $post['ADATE']; ?> <?php echo $post['ATIME']; ?> | 
            管理員：<?php echo htmlspecialchars($post['AID']); ?>
        </div>
        <div class="post-content"><?php echo htmlspecialchars($post['CONTENT']); ?></div>
        
        <div class="admin-actions admin-actions-bottom">
            <a href="post_management.php">← 返回列表</a>
        </div>
    </div>
</main>
</body>
</html>
