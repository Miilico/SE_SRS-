<?php
session_start();
require_once __DIR__ . "/../config.php";

// 權限檢查：假設 ROLE 4 為獎助單位 (organization)
/*if (!isset($_SESSION['role']) || $_SESSION['role'] != 3) {
    die("權限不足，僅限獎助單位進入");
}*/

// 取得當前要查看的狀態 (對應 SQL 中的 RESULT 欄位)
$status_filter = isset($_GET['status']) ? $_GET['status'] : '審查中';

try {
    // 聯集查詢：取得 application 及其關聯的學生與獎學金資料
   $sql = "SELECT a.APNO, u.NAME, s.SID, a.SCNAME, a.APDATE, a.RESULT, a.IS_POSTED 
        FROM application a
        JOIN students s ON a.STID = s.ID
        JOIN users u ON s.ID = u.ID
        WHERE a.RESULT = ?
        ORDER BY a.APDATE DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status_filter]);
    $apps = $stmt->fetchAll();
} catch (PDOException $e) {
    die("查詢失敗：" . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>申請案審核 - 獎助學金系統</title>
    <style>
        body { background-color: #f4f7f6; font-family: "Microsoft JhengHei", sans-serif; color: #333; }
        .admin-container { width: 950px; margin: 50px auto; padding: 40px; background: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border-radius: 8px; }
        .admin-title { font-size: 26px; font-weight: bold; margin-bottom: 25px; text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; }
        
        .status-tabs { display: flex; justify-content: center; margin-bottom: 20px; border-bottom: 2px solid #eee; }
        .tab { padding: 12px 25px; text-decoration: none; color: #666; font-weight: bold; transition: 0.3s; }
        .tab.active { color: #007bff; border-bottom: 3px solid #007bff; margin-bottom: -2px; }

        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f8f9fa; padding: 12px; border-bottom: 2px solid #eee; }
        td { padding: 15px; border-bottom: 1px solid #eee; text-align: center; }
        
        .btn-action { padding: 6px 15px; text-decoration: none; border-radius: 4px; font-size: 13px; color: white; display: inline-block; margin: 2px; }
        .btn-pass { background-color: #28a745; }
        .btn-fail { background-color: #dc3545; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; color: white; }
        .bg-info { background-color: #17a2b8; }
        .bg-success { background-color: #28a745; }
        .bg-danger { background-color: #dc3545; }
    </style>
</head>
<body>

<div class="admin-container">
    <div class="admin-title">獎助學金申請管理</div>
	
	<div style="text-align:center; margin-bottom:10px">
	<a href="admin_dashboard.php" style="background:#6c757d; 
		color:white; padding:10px; text-decoration:none; border-radius:4px;">← 回到儀表板</a>
    </div>
	
	<div class="status-tabs">
        <a href="?status=審查中" class="tab <?php echo $status_filter === '審查中' ? 'active' : ''; ?>">待審核案件</a>
        <a href="?status=通過" class="tab <?php echo $status_filter === '通過' ? 'active' : ''; ?>">已通過名單</a>
        <a href="?status=不通過" class="tab <?php echo $status_filter === '不通過' ? 'active' : ''; ?>">未通過案件</a>
    </div>
	
	<?php if ($status_filter === '通過' && !empty($apps)): 
    // 1. 將「尚未公告」的學生依照「獎學金名稱」進行分組
    $grouped_winners = [];
    $has_new_winner = false;
    foreach ($apps as $a) {
        if ($a['IS_POSTED'] == 0) {
            $scName = $a['SCNAME']; // 取得獎學金名稱
            if (!isset($grouped_winners[$scName])) {
                $grouped_winners[$scName] = [];
            }
            // 儲存 學生姓名與學號
            $grouped_winners[$scName][] = $a['SID'] . " (" . $a['NAME'] . ")";
            $has_new_winner = true;
        }
    }
    
    // 2. 如果有名單，則組合出含有獎學金名稱的字串
    if ($has_new_winner):
        $content_text = "恭喜以下同學獲得獎學金：\n\n";
        
        foreach ($grouped_winners as $scName => $studentList) {
            $content_text .= "【" . $scName . "】\n"; // 這裡會寫出獎學金名稱
            foreach ($studentList as $student) {
                $content_text .= "- " . $student . "\n";
            }
            $content_text .= "\n";
        }

        $default_title = urlencode("【結果公告】獎學金獲獎名單公告");
        $default_content = urlencode($content_text); // 將組合好的內容傳入
?>
    <div style="margin-top: 20px; margin-bottom: 20px; text-align: center;">
        <a href="post_info.php?title=<?php echo $default_title; ?>&content=<?php echo $default_content; ?>&cat=1" 
           style="background: #28a745; color: white; padding: 12px 25px; text-decoration: none; border-radius: 4px; font-weight: bold;">
           📢 根據「新名單」發布公告 (含獎項名稱)
        </a>
    </div>
    <?php else: ?>
    <div style="margin-top: 20px; color: #888; text-align: center;">
        ✅ 所有通過名單皆已發布公告
    </div>
    <?php endif; ?>
<?php endif; ?>
    <table>
        <thead>
            <tr>
                <th>申請號</th>
                <th>學生姓名 (學號)</th>
                <th>申請獎學金</th>
                <th>申請日期</th>
                <th>目前狀態</th>
                
            </tr>
        </thead>
        <tbody>
            <?php foreach ($apps as $a): ?>
            <tr>
                <td><?php echo htmlspecialchars($a['APNO']); ?></td>
                <td><?php echo htmlspecialchars($a['NAME']); ?> (<?php echo htmlspecialchars($a['SID']); ?>)</td>
                <td><?php echo htmlspecialchars($a['SCNAME']); ?></td>
                <td><?php echo $a['APDATE']; ?></td>
                <td>
                    <span class="badge <?php echo $a['RESULT'] == '通過' ? 'bg-success' : ($a['RESULT'] == '不通過' ? 'bg-danger' : 'bg-info'); ?>">
                        <?php echo htmlspecialchars($a['RESULT']); ?>
                    </span>
                </td>
               
            </tr>
            <?php endforeach; ?>
			
        </tbody>
    </table>
</div>

</body>
</html>