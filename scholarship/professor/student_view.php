<?php
require_once __DIR__ . "/../config.php"; // 請確認路徑是否正確
require_once __DIR__ . "/../auth.php";

// 權限檢查：僅限老師 (Role 2) 或管理員 (Role 3)
if (!isset($_SESSION["user"]) || !in_array((int)$_SESSION["user"]["role"], [2, 3])) {
    die("權限不足");
}

$sid = isset($_GET['sid']) ? trim($_GET['sid']) : '';
$student = null;
$apps = [];

if ($sid) {
    // 1. 查詢學生基本與學籍資料
    $sqlUser = "SELECT u.ID, u.NAME, u.EMAIL, u.TEL, s.DNAME 
                FROM users u 
                LEFT JOIN students s ON u.ID = s.ID 
                WHERE u.ID = :id AND u.ROLE = 1";
    $stmt = $pdo->prepare($sqlUser);
    $stmt->execute([':id' => $sid]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        // 2. 查詢該學生的獎助學金申請紀錄
        $sqlApp = "SELECT APNO, SCNAME, APDATE, AMOUNT, RESULT 
                   FROM application 
                   WHERE STID = :id 
                   ORDER BY APDATE DESC";
        $stmtApp = $pdo->prepare($sqlApp);
        $stmtApp->execute([':id' => $sid]);
        $apps = $stmtApp->fetchAll(PDO::FETCH_ASSOC);
    }
}
$pageTitle = "學生資料查詢";
$activeNav = "tea_dashboard.php";
$siteHeaderRequiredRole = array(2, 3);
$siteHeaderMaxWidth = "900px";
require __DIR__ . "/../header.php";
?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4 p-md-5">
    <?php if (!$student): ?>
        <div class="alert alert-warning">
            找不到該學生資料 (ID: <?= htmlspecialchars($sid) ?>)，請確認 ID 是否正確且身分為學生。
        </div>
        <a class="btn btn-outline-secondary" href="javascript:history.back()">回上一頁</a>
    <?php else: ?>
        <h1 class="h3 fw-bold mb-4">學生詳細資料</h1>
        <div class="table-responsive mb-4">
        <table class="table table-bordered align-middle">
            <tbody>
            <tr><th class="table-light" style="width: 180px;">姓名</th><td><?= htmlspecialchars($student['NAME']) ?></td></tr>
            <tr><th class="table-light">學號/ID</th><td><?= htmlspecialchars($student['ID']) ?></td></tr>
            <tr><th class="table-light">系所</th><td><?= htmlspecialchars(isset($student['DNAME']) ? $student['DNAME'] : '未填寫') ?></td></tr>
            <tr><th class="table-light">Email</th><td><?= htmlspecialchars($student['EMAIL']) ?></td></tr>
            <tr><th class="table-light">電話</th><td><?= htmlspecialchars($student['TEL']) ?></td></tr>
            </tbody>
        </table>
        </div>

        <h2 class="h5 fw-bold mb-3">申請紀錄</h2>
        <?php if (empty($apps)): ?>
            <p class="text-secondary">該學生尚無申請紀錄。</p>
        <?php else: ?>
            <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>日期</th>
                        <th>獎助學金名稱</th>
                        <th>金額</th>
                        <th>狀態</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($apps as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['APDATE']) ?></td>
                        <td><?= htmlspecialchars($a['SCNAME']) ?></td>
                        <td>NT$ <?= number_format($a['AMOUNT']) ?></td>
                        <td><span class="badge rounded-pill text-bg-light border"><?= htmlspecialchars($a['RESULT']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
        <a class="btn btn-outline-secondary mt-3" href="javascript:history.back()">返回搜尋</a>
    <?php endif; ?>
    </div>
</div>

</main>
</body>
</html>
