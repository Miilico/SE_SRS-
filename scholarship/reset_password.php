<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/password_reset_helpers.php";

$token = isset($_GET["token"]) ? trim($_GET["token"]) : "";
$reset = $token === "" ? null : find_valid_password_reset($pdo, $token);

$pageTitle = "重設密碼";
$activeNav = "reset_password.php";
$siteHeaderMainClass = "auth-wrap";
$siteHeaderStylesheets = array("/scholarship/assets/css/auth.css");
require __DIR__ . "/header.php";
?>
<div class="card auth-card">
  <div class="auth-header p-4">
    <div class="text-muted small">Password reset</div>
    <h1 class="h4 mb-0 fw-bold">重設密碼</h1>
  </div>

  <div class="p-4">
    <?php if (!empty($_GET["err"])): ?>
      <div class="alert alert-danger mb-3"><?= htmlspecialchars($_GET["err"], ENT_QUOTES, "UTF-8") ?></div>
    <?php endif; ?>

    <?php if (!$reset): ?>
      <div class="alert alert-warning">重設連結無效、已使用或已逾期，請重新申請。</div>
      <a href="forgot_password.php" class="btn btn-primary w-100">重新申請重設密碼</a>
    <?php else: ?>
      <form method="post" action="reset_password_submit.php" class="vstack gap-3">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, "UTF-8") ?>">
        <div>
          <label class="form-label fw-semibold">帳號</label>
          <input class="form-control" value="<?= htmlspecialchars($reset["ID"], ENT_QUOTES, "UTF-8") ?>" disabled>
        </div>

        <div>
          <label class="form-label fw-semibold" for="pwd">新密碼</label>
          <input class="form-control" id="pwd" type="password" name="pwd" maxlength="64" required>
          <div class="form-text">密碼至少 6 碼。</div>
        </div>

        <div>
          <label class="form-label fw-semibold" for="pwd2">確認新密碼</label>
          <input class="form-control" id="pwd2" type="password" name="pwd2" maxlength="64" required>
        </div>

        <button type="submit" class="btn btn-primary w-100 fw-semibold">更新密碼</button>
      </form>
    <?php endif; ?>
  </div>
</div>

</main>
</body>

</html>