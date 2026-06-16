<?php
require_once __DIR__ . "/../config.php"; // 請確認路徑是否正確
session_start();

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
?>

<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>獎助學金系統-老師端</title>
  <style>
    body{ font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans TC",sans-serif; margin:24px; background:#f6f7fb }
    .card{ background:#fff; border-radius:14px; padding:20px; box-shadow:0 1px 8px rgba(0,0,0,.06); margin-bottom:14px; }
    .grid{display:grid;gap:14px}
    .kpi{grid-template-columns:repeat(2, 1fr)}
    .topbar{ display:flex; align-items:center; justify-content:space-between; margin-bottom:14px }
    .tabs a{ margin-right:14px; text-decoration:none; color:#111 }
    .tabs a.active{ font-weight:700; color:#2563eb }
    .muted{color:#667085}
    .btn{ display:inline-block; padding:8px 14px; border-radius:8px; background:#2563eb; color:#fff; text-decoration:none; font-size:14px; border:none; cursor:pointer; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { text-align: left; padding: 12px; border-bottom: 1px solid #eee; }
    th { color: #667085; font-weight: 500; }
    input[type="text"] { padding: 8px; border: 1px solid #ddd; border-radius: 6px; width: 250px; }
  </style>
</head>
<body>

<div class="topbar">
  <div><strong>獎助學金系統｜老師端</strong></div>
  <div class="tabs">
    <a class="active" href="#">總覽</a>
    <a href="/scholarship/profile.php">個人檔案</a>
    <a href="/scholarship/ticket_list.php">回報問題</a>
  </div>
  <div>
    <?= htmlspecialchars($userName) ?> 老師｜
    <a href="/scholarship/logout.php">登出</a>
  </div>
</div>

<div class="card">
  <h2 style="margin:0;"> 老師您好，<?= htmlspecialchars($userName) ?> 👋</h2>
  <p class="muted">您可以直接輸入學號查詢學生詳細資料與申請進度。</p>
  
  <form action="student_view.php" method="GET" style="margin-top:15px;">
    <input type="text" name="sid" placeholder="輸入學生 ID (如: A1234567)" required>
    <button type="submit" class="btn">立即查詢</button>
  </form>
</div>

<div class="grid kpi">
  <div class="card">
    <h3>我推薦過的學生</h3>
    <?php if (empty($myStudents)): ?>
        <p class="muted">目前尚無相關學生紀錄。</p>
    <?php else: ?>
        <table>
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
            <a href="student_view.php?sid=<?= urlencode($s['ID']) ?>" style="color:#2563eb;">查看</a>
        </td>
    </tr>
    <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>個人帳戶管理</h3>
    <p class="muted">查看您的個人資料設定。</p>
    <br>
    <a class="btn" href="/scholarship/profile.php" style="background:#f1f5f9; color:#475569;">查看個人資料</a>
  </div>
</div>

</body>
</html>
