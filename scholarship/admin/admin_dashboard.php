<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";

require_role(3);


$userName = $_SESSION["user"]["name"];
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>管理員後台</title>
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
  <div><strong>獎助學金系統｜管理後台</strong></div>

  <div class="tabs">
    <a class="active" href="/scholarship/admin/admin_dashboard.php">總覽</a>
    <a href="/scholarship/admin/admin_users_pending.php">帳號審核</a>
    <a href="/scholarship/admin/org_management.php">獎助單位管理</a>
    <a href="/scholarship/admin/post_management.php">公告管理</a>
    <a href="/scholarship/admin/app_management.php">獎助學金申請管理</a>
    <a href="/scholarship/profile.php">個人檔案</a>
  </div>

  <div>
    <?= htmlspecialchars($userName) ?>｜
    <a href="/scholarship/logout.php">登出</a>
  </div>
</div>

<!-- ====== 歡迎卡片 ====== -->
<div class="card">
  <h2 style="margin:0;">管理員您好，<?= htmlspecialchars($userName) ?> 👋</h2>
  <div class="muted" style="margin-top:6px;">
    您可以在此管理帳號審核、獎助單位與公告內容
  </div>
</div>

<!-- ====== 功能捷徑（KPI / Quick Actions） ====== -->
<div class="grid kpi" style="margin-top:14px;">
  <div class="card">
    <h3>帳號審核</h3>
    <p class="muted">審核學生 / 教授註冊申請</p>
    <a class="btn" href="/scholarship/admin/admin_users_pending.php">前往</a>
  </div>

  <div class="card">
    <h3>獎助單位管理</h3>
    <p class="muted">新增、編輯獎助學金單位</p>
    <a class="btn" href="/scholarship/admin/org_management.php">前往</a>
  </div>

  <div class="card">
    <h3>公告管理</h3>
    <p class="muted">發布與管理系統公告</p>
    <a class="btn" href="/scholarship/admin/post_management.php">前往</a>
  </div>

    <div class="card">
    <h3>獎助學金申請管理</h3>
    <p class="muted">查看獎助學金申請情形、發布與管理獎助學金申請結果公告</p>
    <a class="btn" href="/scholarship/admin/app_management.php">前往</a>
  </div>
</div>

</body>
</html>
