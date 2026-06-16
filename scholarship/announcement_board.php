<?php
session_start();
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

try {
    // 取得所有公告並按日期與編號排序
    $sql = "SELECT ID, TITLE, ADATE, ATIME FROM announcement ORDER BY ADATE DESC, ID DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $posts = $stmt->fetchAll();
} catch (PDOException $e) {
    die("查詢失敗：" . $e->getMessage());
}
        
$pageTitle = "最新公告事項";
$activeNav = "announcement_board.php";
require __DIR__ . "/header.php";
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">最新公告事項</h1>
        <div class="text-secondary">查看系統公告與獎助學金審查結果。</div>
    </div>
    <a href="/scholarship/index.php" class="btn btn-outline-secondary">返回首頁</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>公告標題</th>
                <th class="text-md-center">發佈日期</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($posts) > 0): ?>
                <?php foreach ($posts as $p): ?>
                <tr>
                    <td>
                        <a href="announcement_view.php?id=<?php echo $p['ID']; ?>" class="fw-semibold text-decoration-none">
                            <?php echo htmlspecialchars($p['TITLE']); ?>
                        </a>
                    </td>
                    <td class="text-secondary text-md-center">
                        <?php echo $p['ADATE']; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="2" class="text-center text-secondary py-5">目前尚無任何公告</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
        </div>
    </div>
</div>

</main>
</body>
</html>
