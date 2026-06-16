<?php
$adminHeaderBootstrapOnly = true;
require __DIR__ . "/header.php";
unset($adminHeaderBootstrapOnly);

// 權限檢查：假設 ROLE 4 為獎助單位 (organization)
/*if (!isset($_SESSION['role']) || $_SESSION['role'] != 3) {
    die("權限不足，僅限獎助單位進入");
}*/

$status_options = [
    '審查中' => '待審核案件',
    '通過' => '已通過名單',
    '不通過' => '未通過案件',
];
$status_filter_options = array_merge(['all' => '全部'], $status_options);

// 取得當前要查看的狀態 (對應 SQL 中的 RESULT 欄位)
$status_filter = isset($_GET['status']) && isset($status_filter_options[$_GET['status']]) ? $_GET['status'] : '審查中';

try {
    $countSql = "
        SELECT RESULT, COUNT(*) AS total
        FROM application
        WHERE RESULT IN ('審查中', '通過', '不通過')
        GROUP BY RESULT
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute();
    $status_counts = array_fill_keys(array_keys($status_options), 0);
    foreach ($countStmt->fetchAll() as $row) {
        if (isset($status_counts[$row['RESULT']])) {
            $status_counts[$row['RESULT']] = (int)$row['total'];
        }
    }

    // 聯集查詢：取得 application 及其關聯的學生與獎學金資料
   $sql = "SELECT a.APNO, u.NAME, s.SID, a.SCNAME, a.APDATE, a.RESULT, a.IS_POSTED
        FROM application a
        JOIN students s ON a.STID = s.ID
        JOIN users u ON s.ID = u.ID
        WHERE a.RESULT IN ('審查中', '通過', '不通過')
        ORDER BY a.APDATE DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $apps = $stmt->fetchAll();
} catch (PDOException $e) {
    die("查詢失敗：" . $e->getMessage());
}
$pageTitle = "獎助學金申請管理";
$activeNav = "app_management.php";
?>
<?php require __DIR__ . "/header.php"; ?>

<div class="admin-container">
    <div class="admin-page-head">
        <div>
            <h1 class="admin-page-title">獎助學金申請管理</h1>
            <div class="admin-page-subtitle">依審查狀態查看申請案與發布錄取公告。</div>
        </div>
    </div>
	
	<div class="filters app-status-filters" aria-label="申請狀態篩選">
        <?php foreach ($status_filter_options as $status => $label): ?>
            <button
                type="button"
                class="filter-btn <?php echo $status_filter === $status ? 'active' : ''; ?>"
                data-status="<?php echo htmlspecialchars($status); ?>"
            >
                <span><?php echo htmlspecialchars($label); ?></span>
                <span class="filter-count"><?php echo $status === 'all' ? count($apps) : $status_counts[$status]; ?></span>
            </button>
        <?php endforeach; ?>
    </div>
	
	<?php if (!empty($apps)):
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
    <div class="admin-actions app-filter-panel" data-panel-status="通過">
        <a href="post_info.php?title=<?php echo $default_title; ?>&content=<?php echo $default_content; ?>&cat=1" 
           class="btn btn-success">
           根據「新名單」發布公告 (含獎項名稱)
        </a>
    </div>
    <?php else: ?>
    <div class="admin-note app-filter-panel" data-panel-status="通過">
        ✅ 所有通過名單皆已發布公告
    </div>
    <?php endif; ?>
<?php endif; ?>
    <div class="admin-table-wrap">
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
            <tr data-status="<?php echo htmlspecialchars($a['RESULT']); ?>">
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
</div>

<script>
document.querySelectorAll(".app-status-filters .filter-btn").forEach(function(button) {
  button.addEventListener("click", function() {
    var status = button.getAttribute("data-status");

    document.querySelectorAll(".app-status-filters .filter-btn").forEach(function(item) {
      item.classList.toggle("active", item === button);
    });

    document.querySelectorAll("tbody tr[data-status]").forEach(function(row) {
      row.style.display = (status === "all" || row.getAttribute("data-status") === status) ? "" : "none";
    });

    document.querySelectorAll(".app-filter-panel[data-panel-status]").forEach(function(panel) {
      panel.style.display = panel.getAttribute("data-panel-status") === status ? "" : "none";
    });
  });
});

var activeFilter = document.querySelector(".app-status-filters .filter-btn.active");
if (activeFilter) {
  activeFilter.click();
}
</script>

</main>
</body>
</html>
