<?php
session_start();
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";

require_role(3); // 3 = admin

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
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>公告管理</title>
    <style>
        table { width: 95%; border-collapse: collapse; margin: 20px auto; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        th { background-color: #f2f2f2; }
        .btn { padding: 5px 10px; text-decoration: none; color: white; border-radius: 4px; }
        .btn-edit { background-color: #007bff; }
        .btn-del { background-color: #dc3545; }
    </style>
</head>
<body>
    <h2 style="text-align:center;">公告管理中心</h2>
    <div style="text-align:center; margin-bottom: 20px;">
	<a href="admin_dashboard.php" style="background:#6c757d; 
		color:white; padding:10px; text-decoration:none; border-radius:4px;">← 回到儀表板</a>
        <a href="post_info.php" style="background:green; color:white; padding:10px; text-decoration:none; border-radius:4px;">＋ 發佈新公告</a>
    </div>

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
                <td align="left">
					<a href="post_view.php?id=<?php echo $p['id']; ?>" style="text-decoration: none; color: #333; font-weight: bold;">
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
</body>
</html>