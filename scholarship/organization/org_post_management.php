<?php
$adminHeaderBootstrapOnly = true;
require __DIR__ . "/../header.php";
unset($adminHeaderBootstrapOnly);

require_role(4);

try {
    $provider_id = $_SESSION['user']['id'];
    $sql = "SELECT * FROM announcement WHERE AID = ? ORDER BY ID DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$provider_id]);
    $posts = $stmt->fetchAll();
} catch (PDOException $e) {
    die("查詢失敗：" . $e->getMessage());
}
$pageTitle = "公告管理";
$activeNav = "org_post_management.php";
?>
<?php require __DIR__ . "/../header.php"; ?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">公告管理</h1>
        <div class="text-secondary">新增、編輯或刪除系統公告與獎學金審查結果。</div>
    </div>
    <a href="org_post_info.php" class="btn btn-success">＋ 發佈新公告</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>編號</th>
                <th>類別</th>
                <th>發佈日期</th>
                <th>標題</th>
                <th>操作</th>

            </tr>
        </thead>
        <tbody>
            <?php foreach ($posts as $p): ?>
                <tr>
                    <td><?php echo $p['id']; ?></td>
                    <td>
                        <?php
                        if (isset($p['CATEGORY']) && $p['CATEGORY'] == 1) {
                            echo '<span>獎學金</span>';
                        } else {
                            echo '<span>一般</span>';
                        }
                        ?>
                    </td>
                    <td><?php echo $p['ADATE']; ?></td>
                    <td>
                        <a href="org_post_view.php?id=<?php echo $p['id']; ?>" class="fw-bold text-body text-decoration-none">
                            <?php echo htmlspecialchars($p['title']); ?>
                        </a>
                    </td>

                    <td class="text-nowrap">
                        <a href="org_post_info.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary">修改</a>
                        <a href="org_post_process.php?action=delete&id=<?php echo $p['id']; ?>"
                            class="btn btn-sm btn-outline-danger"
                            data-confirm="確定要刪除這則公告嗎？此操作無法復原。">刪除</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
</main>
</body>

</html>
