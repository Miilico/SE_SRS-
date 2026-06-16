<?php
session_start();
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";

require_role(3);

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
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>獎助單位管理</title>
    <style>
        body { background-color: #f4f7f6; font-family: "Microsoft JhengHei", sans-serif; }
        table { width: 95%; border-collapse: collapse; margin: 20px auto; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: center; }
        th { background-color: #343a40; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .btn { padding: 6px 12px; text-decoration: none; color: white; border-radius: 4px; margin: 2px; display: inline-block; font-size: 14px; }
        .btn-add { background-color: #28a745; padding:10px; text-decoration:none; border-radius:4px;}
        .btn-edit { background-color: #007bff; }
        .btn-del { background-color: #dc3545; }
        .nav-container { text-align: center; margin-top: 30px; }
    </style>
</head>
<body>
    <h2 style="text-align:center;">獎助單位管理系統</h2>
    <div class="nav-container">
        <a href="admin_dashboard.php" style="background:#6c757d; color:white; padding:10px; text-decoration:none; border-radius:4px;">← 回到儀表板</a>
        <a href="org_form.php" class="btn btn-add">＋ 新增獎助單位</a>
    </div>

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
</body>
</html>