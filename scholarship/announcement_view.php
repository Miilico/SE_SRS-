<?php
session_start();
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/file_helpers.php";

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
$activeNav = "announcement_board.php";
$siteHeaderMaxWidth = "860px";
require __DIR__ . "/header.php";
?>
    <article class="card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5">
        <h1 class="h3 fw-bold border-bottom pb-3 mb-3"><?php echo htmlspecialchars($post['title']); ?></h1>
        <div class="text-secondary small mb-4">
            發佈日期：<?php echo $post['ADATE']; ?> <?php echo $post['ATIME']; ?> | 
            管理員：<?php echo htmlspecialchars($post['AID']); ?>
        </div>
        <div class="fs-5 lh-lg" style="white-space: pre-wrap;"><?php echo htmlspecialchars($post['CONTENT']); ?></div>

        <?php if (!empty($files)): ?>
            <div class="mt-4">
                <div class="fw-bold mb-2">附件</div>
                <ul class="list-group">
                    <?php foreach ($files as $file): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?php echo htmlspecialchars($file["original_name"]); ?></span>
                            <a href="/scholarship/file_view.php?id=<?php echo urlencode($file["id"]); ?>">
                                下載
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <a href="announcement_board.php" class="btn btn-outline-secondary mt-4">返回列表</a>
        </div>
    </article>
</main>
</body>
</html>
