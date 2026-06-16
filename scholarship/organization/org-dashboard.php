<?php
  require_once __DIR__ . "/../config.php";

  //require_role(4); // 4 = 獎助單位

// ====== 基本保護：必須登入 ======
if (empty($_SESSION["user"]) || empty($_SESSION["user"]["id"])) {
  header("Location: /scholarship/login.php");
  exit;
}

// ====== 從 session 取值（✅ 全部用小寫） ======
$stId     = $_SESSION["user"]["id"];
$userName = $_SESSION["user"]["name"];

?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>獎助學金系統-獎助單位端</title>
  <style>
    body{
      font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans TC",sans-serif;
      margin:24px;
      background:#f6f7fb
    }
    .card{
      background:#fff;
      border-radius:14px;
      padding:16px;
      box-shadow:0 1px 8px rgba(0,0,0,.06)
    }
    .grid{display:grid;gap:14px}
    .kpi{grid-template-columns:repeat(3,minmax(0,1fr))}
    .topbar{
      display:flex;
      align-items:center;
      justify-content:space-between;
      margin-bottom:14px
    }
    .tabs a{
      margin-right:14px;
      text-decoration:none;
      color:#111
    }
    .tabs a.active{
      font-weight:700;
      color:#2563eb
    }
    .muted{color:#667085}
    .btn{
      display:inline-block;
      padding:8px 14px;
      border-radius:999px;
      background:#2563eb;
      color:#fff;
      text-decoration:none;
      font-size:14px
    }
  </style>
</head>
<body>

<!-- ====== Topbar（跟學生頁一致） ====== -->
<div class="topbar">
  <div><strong>獎助學金系統｜獎助單位端</strong></div>

  <div class="tabs">
    <a class="active" href="/scholarship/organization/org-dashboard.php">總覽</a>
    <a href="/scholarship/organization/my_scholarships.php">瀏覽我提供的獎助學金</a>
    <a href="/scholarship/organization/view_applicants.php">瀏覽申請資料</a>
    <a href="/scholarship/organization/add_scholarship.php">新增獎助學金</a>
    <a href="/scholarship/profile.php">個人檔案</a>
  </div>

  <div>
    <?= htmlspecialchars($userName) ?>｜
    <a href="/scholarship/logout.php">登出</a>
  </div>
</div>

<!-- ====== 歡迎卡片 ====== -->
<div class="card">
  <h2 style="margin:0;">獎助單位您好，<?= htmlspecialchars($userName) ?> 👋</h2>
  <div class="muted" style="margin-top:6px;">
    您可以在此瀏覽您提供的獎助學金、新增獎助學金
  </div>
</div>

<!-- ====== 功能捷徑（KPI / Quick Actions） ====== -->
<div class="grid kpi" style="margin-top:14px;">
  <div class="card">
    <h3>瀏覽我提供的獎助學金</h3>
    <p class="muted">瀏覽我提供的獎助學金資料</p>
    <a class="btn" href="/scholarship/organization/my_scholarships.php">前往</a>
  </div>

  <div class="card">
    <h3>瀏覽申請資料</h3>
    <p class="muted">瀏覽各獎助學金的申請資料</p>
    <a class="btn" href="/scholarship/organization/view_applicants.php">前往</a>
  </div>

  <div class="card">
    <h3>新增獎助學金</h3>
    <p class="muted">新增獎助學金，供學生申請</p>
    <a class="btn" href="/scholarship/organization/add_scholarship.php">前往</a>
  </div>

  <div class="card">
    <h3>個人檔案</h3>
    <p class="muted">瀏覽、修改個人檔案</p>
    <a class="btn" href="/scholarship/profile.php">前往</a>
  </div>
</div>

</body>
</html>
