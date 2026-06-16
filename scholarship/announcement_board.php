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
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>佈告欄 - 最新公告</title>
    <style>
        body { background-color: #f4f7f6; font-family: "Microsoft JhengHei", sans-serif; color: #333; }
        .board-container { width: 850px; margin: 50px auto; padding: 40px; background: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border-radius: 8px; }
        .board-title { font-size: 28px; font-weight: bold; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 30px; text-align: center; }
        
        table { width: 100%; border-collapse: collapse; }
        th { border-bottom: 2px solid #eee; padding: 12px; color: #666; font-weight: bold; }
        td { border-bottom: 1px solid #eee; padding: 15px; }
        
        .post-link { text-decoration: none; color: #222; font-size: 17px; transition: 0.2s; }
        .post-link:hover { color: #007bff; text-decoration: underline; }
        .date-col { color: #888; font-size: 14px; width: 150px; text-align: center; }
        
        .no-data { text-align: center; padding: 50px; color: #999; }
        .nav-link { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; }
    </style>
</head>
<body>

<div class="board-container">
    <div class="board-title">最新公告事項</div>

    <table>
        <thead>
            <tr>
                <th align="left">公告標題</th>
                <th>發佈日期</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($posts) > 0): ?>
                <?php foreach ($posts as $p): ?>
                <tr>
                    <td>
                        <a href="announcement_view.php?id=<?php echo $p['ID']; ?>" class="post-link">
                            <?php echo htmlspecialchars($p['TITLE']); ?>
                        </a>
                    </td>
                    <td class="date-col">
                        <?php echo $p['ADATE']; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="2" class="no-data">目前尚無任何公告</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <a href="index.php" class="nav-link">← 返回首頁</a>
</div>

</body>
</html>