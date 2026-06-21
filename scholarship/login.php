<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/login_helpers.php";

if (isset($_GET["restart_email_login"])) {
    login_clear_pending_session();
}

$pendingLoginUserId = isset($_SESSION["pending_login_user_id"]) ? $_SESSION["pending_login_user_id"] : "";
$pendingLoginEmail = isset($_SESSION["pending_login_email"]) ? $_SESSION["pending_login_email"] : "";
$pendingRequiresEmail = !empty($_SESSION["pending_login_requires_email"]);
$pendingRequiresTotp = !empty($_SESSION["pending_login_requires_totp"]);
if ($pendingLoginUserId !== "" && !$pendingRequiresEmail && !$pendingRequiresTotp) {
    $pendingRequiresEmail = $pendingLoginEmail !== "";
}

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
    <?php if ($pendingLoginUserId !== ""): ?>
    <form method="post" action="login_verify_submit.php" class="vstack gap-3">
      <?php if ($pendingRequiresEmail): ?>
      <div>
        <label class="form-label fw-semibold" for="email_code">Email 登入驗證碼 <span class="text-danger" aria-label="必填">*</span></label>
        <input class="form-control" id="email_code" name="email_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required placeholder="請輸入 6 位數驗證碼" autocomplete="one-time-code">
        <div class="form-text">
          驗證碼已寄至 <?php echo htmlspecialchars($pendingLoginEmail, ENT_QUOTES, "UTF-8"); ?>，10 分鐘內有效。
        </div>
      </div>
      <?php endif; ?>

      <?php if ($pendingRequiresTotp): ?>
      <div>
        <label class="form-label fw-semibold" for="totp_code">TOTP 驗證碼 <span class="text-danger" aria-label="必填">*</span></label>
        <input class="form-control" id="totp_code" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required placeholder="請輸入驗證器中的 6 位數代碼" autocomplete="one-time-code">
        <div class="form-text">
          請開啟您已綁定的驗證器 App，輸入目前顯示的 6 位數代碼。
        </div>
      </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary w-100 fw-semibold">
        驗證並登入
      </button>

      <div class="text-center mt-2">
        <a href="login.php?restart_email_login=1" class="small text-decoration-none">取消登入</a>
      </div>
    </form>
    <?php else: ?>
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
    <?php endif; ?>
  </div>
</div>
</div>
</div>

</main>
</body>

</html>
