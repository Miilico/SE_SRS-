<?php
session_start();
require_once __DIR__ . "/../config.php";


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
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($post['title']); ?></title>
    <style>
        .post-container { width: 700px; margin: 50px auto; padding: 30px; border: 1px solid #ddd; background: #fff; line-height: 1.6; }
        .post-title { font-size: 28px; font-weight: bold; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; }
        .post-meta { color: #666; font-size: 14px; margin-bottom: 20px; }
        .post-content { font-size: 18px; white-space: pre-wrap; } /* 保留換行符號 */
        .back-btn { display: inline-block; margin-top: 30px; text-decoration: none; color: blue; }
    </style>
</head>
<body>
    <div class="post-container">
        <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>
        <div class="post-meta">
            發佈日期：<?php echo $post['ADATE']; ?> <?php echo $post['ATIME']; ?> | 
            管理員：<?php echo htmlspecialchars($post['AID']); ?>
        </div>
        <div class="post-content"><?php echo htmlspecialchars($post['CONTENT']); ?></div>
        
        <a href="post_management.php" class="back-btn">← 返回列表</a>
    </div>
</body>
</html>