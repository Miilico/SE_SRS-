<?php
require_once __DIR__ . "/config.php";

$pageTitle = "登入";
$activeNav = "login.php";
$siteHeaderMainClass = "site-shell py-4";
$siteHeaderBrandHref = "/";
$breadcrumbs = array();
require __DIR__ . "/header.php";
?>
<div class="row justify-content-center">
<div class="col-12 col-sm-10 col-md-7 col-lg-5">
<div class="card border-0 shadow-sm overflow-hidden">
  <div class="card-header bg-white p-4">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small">Welcome back</div>
        <h1 class="h4 mb-0 fw-bold">登入</h1>
      </div>
      <span class="badge text-bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2">
        Scholarship
      </span>
    </div>
  </div>

  <div class="p-4">
    <form method="post" action="login_submit.php" class="vstack gap-3">
      <div>
        <label class="form-label fw-semibold" for="id">使用者 ID <span class="text-danger" aria-label="必填">*</span></label>
        <input class="form-control" id="id" name="id" maxlength="10" required placeholder="例如 A1234567" autocomplete="username">
      </div>

      <div>
        <label class="form-label fw-semibold" for="pwd">密碼 <span class="text-danger" aria-label="必填">*</span></label>
        <input class="form-control" id="pwd" type="password" name="pwd" maxlength="64" required placeholder="請輸入密碼" autocomplete="current-password">
        <div class="text-end mt-2">
          <a href="forgot_password.php" class="small text-decoration-none">忘記使用者ID / 密碼？</a>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100 fw-semibold">
        登入
      </button>

      <div class="text-center mt-3">
        <span class="text-muted small">還沒有帳號？</span>
        <a class="btn btn-outline-primary btn-sm ms-2" href="register.php">註冊</a>
      </div>
    </form>
  </div>
</div>
</div>
</div>

</main>
</body>

</html>
