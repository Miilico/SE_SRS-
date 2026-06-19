<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
date_default_timezone_set('Asia/Taipei');
require_once "db.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/scholarship_access.php";

organization_require_scholarship_manager();

$isAdmin = organization_is_admin();
$provider_id = organization_current_user_id();

// 取得選擇的 scholarship_id
$selected_id = isset($_GET['scholarship_id']) ? $_GET['scholarship_id'] : 'all';

if ($isAdmin) {
    $providerName = organization_provider_display_expr();

    if ($selected_id === 'all') {
        $sql = "SELECT s.id, s.NAME, s.DEADLINE, s.CONDI, s.AMOUNT, s.start_date, s.is_active,
                       s.provider_id, {$providerName} AS provider_name
                FROM scholarship s
                JOIN users u ON u.ID = s.provider_id AND u.ROLE = 4
                LEFT JOIN organization o ON o.ID = s.provider_id
                ORDER BY s.id DESC";
        $stmt = $pdo->query($sql);
    } else {
        $sql = "SELECT s.id, s.NAME, s.DEADLINE, s.CONDI, s.AMOUNT, s.start_date, s.is_active,
                       s.provider_id, {$providerName} AS provider_name
                FROM scholarship s
                JOIN users u ON u.ID = s.provider_id AND u.ROLE = 4
                LEFT JOIN organization o ON o.ID = s.provider_id
                WHERE s.id = ?
                ORDER BY s.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($selected_id));
    }
} else {
    if ($selected_id === 'all') {
        // 🔽 這裡加上了 is_active
        $sql = "SELECT id, NAME, DEADLINE, CONDI, AMOUNT, start_date, is_active, provider_id
                FROM scholarship 
                WHERE provider_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($provider_id));
    } else {
        // 🔽 這裡加上了 is_active
        $sql = "SELECT id, NAME, DEADLINE, CONDI, AMOUNT, start_date, is_active, provider_id
                FROM scholarship 
                WHERE provider_id = ? AND id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($provider_id, $selected_id));
    }
}
$scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);



// 取得所有獎助學金供選單使用
if ($isAdmin) {
    $providerName = organization_provider_display_expr();
    $stmt = $pdo->query("
        SELECT s.id, s.NAME, s.provider_id, {$providerName} AS provider_name
        FROM scholarship s
        JOIN users u ON u.ID = s.provider_id AND u.ROLE = 4
        LEFT JOIN organization o ON o.ID = s.provider_id
        ORDER BY provider_name ASC, s.NAME ASC, s.id ASC
    ");
} else {
    $stmt = $pdo->prepare("SELECT id, NAME, provider_id FROM scholarship WHERE provider_id = ?");
    $stmt->execute(array($provider_id));
}
$allOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = $isAdmin ? "獎助學金管理" : "我的獎助學金";
$activeNav = "my_scholarships.php";
$siteHeaderRequiredRole = array(3, 4);
require __DIR__ . "/../header.php";
?>
<?php if (isset($_GET['broadcast_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <strong>✅ 廣播發送完成！</strong> 成功寄出了 <?= intval($_GET['count']) ?> 封通知信給符合條件的學生。
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<h1 class="h3 mb-4 fw-bold"><?php echo $isAdmin ? "所有獎助單位的獎助學金清單" : "我提供的獎助學金清單"; ?></h1>

<!-- 選擇獎助學金 -->
<form method="get" action="" class="mb-4 d-flex gap-2 align-items-center">
    <?php if (!$isAdmin): ?>
        <input type="hidden" name="provider_id" value="<?php echo htmlspecialchars($provider_id); ?>">
    <?php endif; ?>
    <label for="scholarship_id" class="form-label mb-0">選擇獎助學金：</label>
    <select name="scholarship_id" id="scholarship_id" class="form-select w-auto" onchange="this.form.submit()">
        <option value="all" <?php echo ($selected_id === 'all') ? 'selected' : ''; ?>>總覽</option>
        <?php foreach ($allOptions as $opt): ?>
            <option value="<?php echo $opt['id']; ?>" <?php echo ($selected_id == $opt['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($isAdmin ? ($opt['provider_name'] . " - " . $opt['NAME']) : $opt['NAME']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
    if ($selected_id !== 'all' && !empty($selected_id)) {
        $deleteUrl = "delete_scholarship.php?scholarship_id=" . urlencode($selected_id);
        echo "<a href=\"$deleteUrl\" class=\"btn btn-danger\" data-confirm=\"確定要刪除此獎學金嗎？此操作無法復原。\">刪除</a>";
    }
    ?>
    <noscript><button type="submit" class="btn btn-primary">瀏覽</button></noscript>
</form>

<?php if (count($scholarships) === 0): ?>
    <div class="alert alert-warning">目前沒有獎學金。</div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 g-4">
        <?php foreach ($scholarships as $s): ?>
    <?php
    $today = date('Y-m-d');
    
    // 狀態判斷邏輯
    if (isset($s['is_active']) && $s['is_active'] == 0) {
        $status = "已強制關閉";
    } elseif ($s['start_date'] > $today) {
        $status = "尚未開始";
    } elseif ($s['start_date'] <= $today && $s['DEADLINE'] >= $today) {
        $status = "開放中";
    } else {
        $status = "已截止";
    }
    ?>
    <div class="col">
        <div class="card shadow-sm h-100 <?= (isset($s['is_active']) && $s['is_active'] == 0) ? 'bg-light' : '' ?> d-flex flex-column">
            <div class="card-body d-flex flex-column">
                
                <div class="mb-3">
                    <h5 class="card-title fw-bold text-primary mb-2"><?php echo htmlspecialchars($s['NAME']); ?></h5>
                    <div>
                        <?php if ($status === '已強制關閉'): ?>
                            <span class="badge bg-danger text-white">手動關閉</span>
                        <?php elseif ($status === '尚未開始'): ?>
                            <span class="badge bg-warning text-dark">尚未開始</span>
                        <?php elseif ($status === '開放中'): ?>
                            <span class="badge bg-success">開放中</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">已截止</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mb-3 text-secondary small">
                    <?php if ($isAdmin): ?>
                        <p class="mb-1"><strong>發布單位：</strong> <?php echo htmlspecialchars($s['provider_name']); ?></p>
                    <?php endif; ?>
                    <p class="mb-1"><strong>開始日期：</strong> <?php echo htmlspecialchars($s['start_date']); ?></p>
                    <p class="mb-1"><strong>截止日期：</strong> <?php echo htmlspecialchars($s['DEADLINE']); ?></p>
                    <p class="mb-1"><strong>金額：</strong> <span class="text-danger fw-semibold">$<?php echo htmlspecialchars($s['AMOUNT']); ?></span></p>
                    <p class="mb-0 text-truncate" title="<?php echo htmlspecialchars($s['CONDI']); ?>">
                        <strong>申請條件：</strong> <?php echo htmlspecialchars($s['CONDI']); ?>
                    </p>
                </div>
                
                <div class="mt-auto border-top pt-3">
                    <div class="row g-2">
                        <div class="col-6">
                            <a href="edit_scholarship.php?id=<?php echo $s['id']; ?>" class="btn btn-outline-secondary btn-sm w-100">
                                ✏️ 編輯
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="view_applicants.php?provider_id=<?php echo urlencode($s['provider_id']); ?>&scholarship_id=<?php echo $s['id']; ?>" class="btn btn-outline-primary btn-sm w-100">
                                📄 申請資料
                            </a>
                        </div>
                        
                        <div class="col-6">
                            <a href="broadcast_scholarship.php?id=<?php echo $s['id']; ?>" class="btn btn-info btn-sm w-100 text-white shadow-sm">
                                📣 廣播通知
                            </a>
                        </div>
                        <div class="col-6">
                            <form action="change_scholarship_status.php" method="post" class="m-0 w-100">
                                <input type="hidden" name="scholarship_id" value="<?= $s['id'] ?>">
                                <?php if (!isset($s['is_active']) || $s['is_active'] == 1): ?>
                                    <input type="hidden" name="target_status" value="0">
                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('確定要強制關閉此表單嗎？學生將無法繼續申請。');">
                                        🛑 強制關閉
                                    </button>
                                <?php else: ?>
                                    <input type="hidden" name="target_status" value="1">
                                    <button type="submit" class="btn btn-sm btn-success w-100" onclick="return confirm('確定要重新開啟此表單嗎？');">
                                        🟢 重新開啟
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                        <?php if ($isAdmin): ?>
                            <div class="col-6">
                                <a href="delete_scholarship.php?scholarship_id=<?php echo urlencode($s['id']); ?>" class="btn btn-outline-danger btn-sm w-100" data-confirm="確定要刪除此獎學金嗎？此操作無法復原。">
                                    🗑️ 刪除
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
<?php endforeach; ?>
    </div>
<?php endif; ?>

</main>
</body>

</html>
