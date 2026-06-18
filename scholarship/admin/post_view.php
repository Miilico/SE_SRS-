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

    <div class="row justify-content-center">
    <div class="col-12 col-lg-9">
    <article class="card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5">
        <h1 class="h3 fw-bold border-bottom pb-3 mb-3"><?php echo htmlspecialchars($post['title']); ?></h1>
        <div class="text-secondary small mb-4">
            發佈日期：<?php echo $post['ADATE']; ?> <?php echo $post['ATIME']; ?> | 
            管理員：<?php echo htmlspecialchars($post['AID']); ?>
        </div>
        <div class="fs-6 lh-lg mb-4"><?php echo nl2br(htmlspecialchars($post['CONTENT'])); ?></div>

        <?php if (!empty($files)): ?>
            <div class="mt-4">
                <strong>附件：</strong>
                <ul class="mt-2">
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
        
        </div>
    </article>
    </div>
    </div>
</main>
</body>
</html>
