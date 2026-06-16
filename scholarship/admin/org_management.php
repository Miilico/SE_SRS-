<?php
$adminHeaderBootstrapOnly = true;
require __DIR__ . "/header.php";
unset($adminHeaderBootstrapOnly);

// 權限檢查：僅限管理員 (ROLE = 3)
/*if (!isset($_SESSION['role']) || $_SESSION['role'] != 3) {
    die("權限不足，請重新登入。");
}*/

try {
    // 修正 SQL：使用 GROUP_CONCAT 取得多筆電話，並正確對應 CONTACT 為聯絡人
    $sql = "SELECT u.*, o.ONAME, o.CONTACT, 
                   GROUP_CONCAT(p.TEL SEPARATOR ', ') as PHONES
            FROM users u 
            LEFT JOIN organization o ON u.ID = o.ID 
            LEFT JOIN ophone p ON u.ID = p.ID
            WHERE u.ROLE = 4
            GROUP BY u.ID";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $orgs = $stmt->fetchAll();
} catch (PDOException $e) {
    die("查詢失敗：" . $e->getMessage());
}
$pageTitle = "獎助單位管理";
$activeNav = "org_management.php";
?>
<?php require __DIR__ . "/header.php"; ?>

    <div class="admin-page-head">
        <div>
            <h1 class="admin-page-title">獎助單位管理</h1>
            <div class="admin-page-subtitle">管理獎助學金單位帳號、聯絡人與電話資料。</div>
        </div>
    </div>

    <div class="admin-actions">
        <a href="org_form.php" class="btn btn-add">＋ 新增獎助單位</a>
    </div>

    <div class="admin-card admin-table-wrap">
    <table>
        <thead>
            <tr>
                <th>帳號 ID</th>
                <th>單位名稱 (ONAME)</th>
                <th>聯絡人 (CONTACT)</th>
                <th>單位電話 (ophone)</th>
                <th>Email</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orgs as $org): ?>
            <tr>
                <td><?php echo htmlspecialchars($org['ID']); ?></td>
                <td><?php echo htmlspecialchars($org['ONAME'] ?: $org['NAME']); ?></td>
                <td><?php echo htmlspecialchars($org['CONTACT'] ?: '未填'); ?></td>
                <td><?php echo htmlspecialchars($org['PHONES'] ?: '無電話'); ?></td>
                <td><?php echo htmlspecialchars($org['EMAIL']); ?></td>
                <td>
                    <a href="org_form.php?id=<?php echo urlencode($org['ID']); ?>" class="btn btn-edit">修改</a>
                    <a href="org_process.php?action=delete&id=<?php echo urlencode($org['ID']); ?>" 
                       class="btn btn-del" onclick="return confirm('警告：將一併刪除聯絡人與電話資料，確定嗎？')">刪除</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</main>
</body>
</html>
