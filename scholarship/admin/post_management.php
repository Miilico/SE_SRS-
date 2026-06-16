<?php
$adminHeaderBootstrapOnly = true;
require __DIR__ . "/header.php";
unset($adminHeaderBootstrapOnly);

/*if (!isset($_SESSION['role']) || $_SESSION['role'] != 3) {
    die("權限不足");
}*/

try {
    // 假設欄位名稱為 ID
    $sql = "SELECT * FROM announcement ORDER BY ID DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $posts = $stmt->fetchAll();
} catch (PDOException $e) {
    die("查詢失敗：" . $e->getMessage());
}
$pageTitle = "公告管理";
$activeNav = "post_management.php";
?>
<?php require __DIR__ . "/header.php"; ?>

    <div class="admin-page-head">
        <div>
            <h1 class="admin-page-title">公告管理</h1>
            <div class="admin-page-subtitle">新增、編輯或刪除系統公告與獎學金審查結果。</div>
        </div>
    </div>

    <div class="admin-actions">
        <a href="post_info.php" class="btn btn-success">＋ 發佈新公告</a>
    </div>

    <div class="admin-card admin-table-wrap">
    <table>
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
					<a href="post_view.php?id=<?php echo $p['id']; ?>" class="announcement-title-link">
						<?php echo htmlspecialchars($p['title']); ?>
					</a>
				</td>
				
                <td>
                    <a href="post_info.php?id=<?php echo $p['id']; ?>" class="btn btn-edit">修改</a>
                    <a href="post_process.php?action=delete&id=<?php echo $p['id']; ?>" 
						class="btn btn-del" 
						onclick="return confirm('確定要刪除這則公告嗎？')">刪除</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</main>
</body>
</html>
