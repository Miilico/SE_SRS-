<?php
require_once __DIR__ . "/../config.php"; // 請確認路徑是否正確
require_once __DIR__ . "/../auth.php";

// ====== 基本保護：必須登入且角色為老師 (2) 或管理員 (3) ======
if (empty($_SESSION["user"]) || !in_array((int)$_SESSION["user"]["role"], [2, 3])) {
    header("Location: /scholarship/login.php");
    exit;
}

$userId   = $_SESSION["user"]["id"];
$userName = $_SESSION["user"]["name"];

// ====== 查詢：曾寫過推薦信的學生清單 (從 recommendations 關聯到 users) ======
$sqlMyStudents = "
    SELECT DISTINCT u.ID, u.NAME, u.EMAIL, s.DNAME
    FROM recommendations r
    JOIN application a ON r.application_id = a.APNO
    JOIN users u ON a.STID = u.ID
    LEFT JOIN students s ON u.ID = s.ID
    WHERE r.teacher_id = :tid
";
$stmt = $pdo->prepare($sqlMyStudents);
$stmt->execute([':tid' => $userId]);
$myStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "老師端總覽";
$activeNav = "tea_dashboard.php";
$siteHeaderRequiredRole = array(2, 3);
require __DIR__ . "/../header.php";
?>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body p-4">
  <h1 class="h3 fw-bold mb-2">老師您好，<?= htmlspecialchars($userName) ?></h1>
  <p class="text-secondary mb-3">您可以直接輸入學號查詢學生詳細資料與申請進度。</p>
  
  <form action="student_view.php" method="GET" class="row g-2 align-items-end">
    <div class="col-md-8 col-lg-5">
      <label class="form-label fw-semibold" for="sid">學生 ID</label>
      <input class="form-control" id="sid" type="text" name="sid" placeholder="例如：A1234567" required>
    </div>
    <div class="col-md-auto">
      <button type="submit" class="btn btn-primary">立即查詢</button>
    </div>
  </form>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
  <div class="card border-0 shadow-sm h-100">
    <div class="card-body p-4">
    <h2 class="h5 fw-bold mb-3">我推薦過的學生</h2>
    <?php if (empty($myStudents)): ?>
        <p class="text-secondary mb-0">目前尚無相關學生紀錄。</p>
    <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>姓名</th>
                    <th>系所</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
               <?php foreach ($myStudents as $s): ?>
    <tr>
        <td><?= htmlspecialchars($s['NAME']) ?></td>
        <td><?= htmlspecialchars(isset($s['DNAME']) ? $s['DNAME'] : '未設定') ?></td>
        <td>
            <a class="btn btn-outline-primary btn-sm" href="student_view.php?sid=<?= urlencode($s['ID']) ?>">查看</a>
        </td>
    </tr>
    <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
    </div>
  </div>
  </div>

  <div class="col-lg-5">
  <div class="card border-0 shadow-sm h-100">
    <div class="card-body p-4">
    <h2 class="h5 fw-bold mb-2">個人帳戶管理</h2>
    <p class="text-secondary">查看您的個人資料設定。</p>
    <a class="btn btn-outline-secondary" href="/scholarship/profile.php">查看個人資料</a>
    </div>
  </div>
  </div>
</div>

</main>
</body>
</html>
