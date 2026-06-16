<?php
require_once __DIR__ . "/../config.php"; // 請確認路徑是否正確
session_start();

// 權限檢查：僅限老師 (Role 2) 或管理員 (Role 3)
if (!isset($_SESSION["user"]) || !in_array((int)$_SESSION["user"]["role"], [2, 3])) {
    die("權限不足");
}

$sid = isset($_GET['sid']) ? trim($_GET['sid']) : '';

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
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>學生資料查詢</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; padding: 20px; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #f8f9fa; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; background: #e2e8f0; }
    </style>
</head>
<body>

<div class="container">
    <?php if (!$student): ?>
        <p>找不到該學生資料 (ID: <?= htmlspecialchars($sid) ?>)，請確認 ID 是否正確且身分為學生。</p>
        <a href="javascript:history.back()">回上一頁</a>
    <?php else: ?>
        <h2>學生詳細資料</h2>
        <table>
            <tr><th>姓名</th><td><?= htmlspecialchars($student['NAME']) ?></td></tr>
            <tr><th>學號/ID</th><td><?= htmlspecialchars($student['ID']) ?></td></tr>
           <tr><th>系所</th><td><?= htmlspecialchars(isset($student['DNAME']) ? $student['DNAME'] : '未填寫') ?></td></tr>
            <tr><th>Email</th><td><?= htmlspecialchars($student['EMAIL']) ?></td></tr>
            <tr><th>電話</th><td><?= htmlspecialchars($student['TEL']) ?></td></tr>
        </table>

        <h3 style="margin-top: 30px;">申請紀錄</h3>
        <?php if (empty($apps)): ?>
            <p>該學生尚無申請紀錄。</p>
        <?php else: ?>
            <table>
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
                        <td><span class="badge"><?= htmlspecialchars($a['RESULT']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <br>
        <a href="javascript:history.back()">返回搜尋</a>
    <?php endif; ?>
</div>

</body>
</html>