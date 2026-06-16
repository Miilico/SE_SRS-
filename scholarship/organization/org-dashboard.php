<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";

require_role(4); // 4 = 獎助單位

// ====== 從 session 取值（✅ 全部用小寫） ======
$stId     = $_SESSION["user"]["id"];
$userName = $_SESSION["user"]["name"];

$pageTitle = "獎助單位總覽";
$activeNav = "org-dashboard.php";
$siteHeaderRequiredRole = 4;
require __DIR__ . "/../header.php";
?>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body p-4">
  <h1 class="h3 fw-bold mb-1">獎助單位您好，<?= htmlspecialchars($userName) ?></h1>
  <div class="text-secondary">
    您可以在此瀏覽您提供的獎助學金、新增獎助學金
  </div>
  </div>
</div>

<div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3">
  <div class="col">
  <div class="card border-0 shadow-sm h-100">
    <div class="card-body p-4">
    <h2 class="h5 fw-bold">我的獎助學金</h2>
    <p class="text-secondary">瀏覽我提供的獎助學金資料</p>
    <a class="btn btn-primary" href="/scholarship/organization/my_scholarships.php">前往</a>
    </div>
  </div>
  </div>

  <div class="col">
  <div class="card border-0 shadow-sm h-100">
    <div class="card-body p-4">
    <h2 class="h5 fw-bold">申請資料</h2>
    <p class="text-secondary">瀏覽各獎助學金的申請資料</p>
    <a class="btn btn-primary" href="/scholarship/organization/view_applicants.php">前往</a>
    </div>
  </div>
  </div>

  <div class="col">
  <div class="card border-0 shadow-sm h-100">
    <div class="card-body p-4">
    <h2 class="h5 fw-bold">新增獎助學金</h2>
    <p class="text-secondary">新增獎助學金，供學生申請</p>
    <a class="btn btn-primary" href="/scholarship/organization/add_scholarship.php">前往</a>
    </div>
  </div>
  </div>

  <div class="col">
  <div class="card border-0 shadow-sm h-100">
    <div class="card-body p-4">
    <h2 class="h5 fw-bold">個人檔案</h2>
    <p class="text-secondary">瀏覽、修改個人檔案</p>
    <a class="btn btn-primary" href="/scholarship/profile.php">前往</a>
    </div>
  </div>
  </div>
</div>

</main>
</body>
</html>
