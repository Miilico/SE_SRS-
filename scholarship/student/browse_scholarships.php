<?php
// browse_scholarships.php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../file_helpers.php";

require_role(1);

$studentIds = array_values(array_unique(array_filter(array(
    isset($_SESSION["user"]["stid"]) ? (string)$_SESSION["user"]["stid"] : "",
    isset($_SESSION["user"]["id"]) ? (string)$_SESSION["user"]["id"] : "",
))));
$studentPlaceholders = implode(",", array_fill(0, count($studentIds), "?"));

// 查詢尚未開始申請的獎學金
$activeScholarshipSql = table_has_column($pdo, "scholarship", "is_active")
    ? " AND is_active = 1"
    : "";
$stmt_not_started = $pdo->query("
    SELECT s.* FROM scholarship s
    WHERE start_date > CURDATE()" . $activeScholarshipSql . "
    ORDER BY start_date ASC
");
$not_started = $stmt_not_started->fetchAll(PDO::FETCH_ASSOC);

// 查詢申請期限中的獎學金 
$stmt_open = $pdo->prepare("
    SELECT s.*,
           EXISTS (
               SELECT 1 FROM application a
               WHERE a.SCID = s.id AND a.STID IN (" . $studentPlaceholders . ")
           ) AS has_applied
    FROM scholarship s
    WHERE start_date <= CURDATE() AND deadline >= CURDATE()" . $activeScholarshipSql . "
    ORDER BY deadline ASC
");
$stmt_open->execute($studentIds);
$open = $stmt_open->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "瀏覽獎助學金";
$activeNav = "browse_scholarships.php";
$siteHeaderRequiredRole = 1;
require __DIR__ . "/../header.php";
?>
    <h1 class="h3 mb-4 fw-bold">瀏覽獎助學金</h1>
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
                                    <?php if (!empty($s['has_applied'])): ?>
                                        <span class="badge bg-secondary">已申請</span>
                                    <?php else: ?>
                                        <a href="apply.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-success">立即申請</a>
                                    <?php endif; ?>
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
</main>
</body>
</html>
