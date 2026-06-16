<?php
// browse_scholarships.php
$dsn = "mysql:host=localhost;dbname=scholarship;charset=utf8";
$user = "root";
$pass = "a1125518";

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ 資料庫連線失敗：" . $e->getMessage());
}

// 查詢尚未開始申請的獎學金
$stmt_not_started = $pdo->query("
    SELECT * FROM scholarship
    WHERE start_date > CURDATE()
    ORDER BY start_date ASC
");
$not_started = $stmt_not_started->fetchAll(PDO::FETCH_ASSOC);

// 查詢申請期限中的獎學金 
$stmt_open = $pdo->query("
    SELECT * FROM scholarship
    WHERE start_date <= CURDATE() AND deadline >= CURDATE()
    ORDER BY deadline ASC
");
$open = $stmt_open->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>瀏覽獎助學金 - 獎助學金系統</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2 class="mb-4 text-dark fw-bold">瀏覽獎助學金</h2>
    <!-- 申請期限中的獎學金 -->
    <div class="card shadow">
        <div class="card-header bg-primary text-white">開放申請中</div>
        <div class="card-body">
            <?php if (empty($open)): ?>
                <p class="text-muted">目前沒有開放申請中的獎學金。</p>
            <?php else: ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>獎助學金名稱</th>
                            <th>截止日期</th>
                            <th>申請條件</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($open as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['NAME']); ?></td>
                                 <!--<td><?php echo htmlspecialchars($s['start_date']); ?></td> -->
                                <td><?php echo htmlspecialchars($s['DEADLINE']); ?></td>
                                <td><?php echo htmlspecialchars($s['CONDI']); ?></td>
                                <td>
                                    <a href="apply.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-success">立即申請</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="my-4"></div> <!-- 這行只是空白間距 -->

    <!-- 尚未開始申請 -->
    <div class="card mb-4 shadow">
        <div class="card-header bg-secondary text-white">尚未開始申請</div>
        <div class="card-body">
            <?php if (empty($not_started)): ?>
                <p class="text-muted">目前沒有尚未開始申請的獎助學金。</p>
            <?php else: ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>獎助學金名稱</th>
                            <th>申請開始日</th>
                            <th>截止日期</th>
                            <th>申請條件</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($not_started as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['NAME']); ?></td>
                                <td><?php echo htmlspecialchars($s['start_date']); ?></td>
                                <td><?php echo htmlspecialchars($s['DEADLINE']); ?></td>
                                <td><?php echo htmlspecialchars($s['CONDI']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<a href="student-dashboard.php" 
   class="btn btn-secondary" 
   style="position: fixed; bottom: 20px; right: 20px; z-index: 1000;">
   返回主頁
</a>

</body>
</html>
