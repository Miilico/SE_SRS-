<?php
$pageTitle = "管理員總覽";
$activeNav = "admin_dashboard.php";
?>
<?php require __DIR__ . "/header.php"; ?>

<!-- ====== 歡迎卡片 ====== -->
<div class="card">
  <h1 class="admin-page-title">管理員<?= htmlspecialchars($userName) ?>，您好</h1>
  <div class="muted admin-tight-gap">
    您可以在此管理帳號審核、獎助單位與公告內容
  </div>
</div>

<!-- ====== 功能捷徑（KPI / Quick Actions） ====== -->
<div class="grid kpi admin-section-gap">
  <div class="card">
    <h3>帳號審核</h3>
    <p class="muted">審核獎助單位註冊申請</p>
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

</main>
</body>
</html>
