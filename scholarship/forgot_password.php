<?php
require_once __DIR__ . "/config.php";

$pageTitle = "忘記密碼";
$activeNav = "forgot_password.php";
$siteHeaderMainClass = "auth-wrap";
$siteHeaderStylesheets = array("/scholarship/assets/css/auth.css");
require __DIR__ . "/header.php";
?>
    <div class="card auth-card">
      <div class="auth-header p-4">
        <div class="text-muted small">Password reset</div>
        <h1 class="h4 mb-0 fw-bold">忘記密碼</h1>
      </div>

      <div class="p-4">
        <?php if (!empty($_GET["msg"])): ?>
          <div class="alert alert-info mb-3"><?= htmlspecialchars($_GET["msg"], ENT_QUOTES, "UTF-8") ?></div>
        <?php endif; ?>

        <?php if (!empty($_SESSION["password_reset_dev_link"])): ?>
          <div class="alert alert-warning mb-3">
            <div class="fw-semibold">本機測試連結</div>
            <a href="<?= htmlspecialchars($_SESSION["password_reset_dev_link"], ENT_QUOTES, "UTF-8") ?>">
              <?= htmlspecialchars($_SESSION["password_reset_dev_link"], ENT_QUOTES, "UTF-8") ?>
            </a>
          </div>
          <?php unset($_SESSION["password_reset_dev_link"]); ?>
        <?php endif; ?>

        <form method="post" action="forgot_password_submit.php" class="vstack gap-3">
          <div>
            <label class="form-label fw-semibold" for="email">註冊 Email</label>
            <input class="form-control" id="email" type="email" name="email" maxlength="100" placeholder="example@mail.nuk.edu.tw" required>
            <div class="form-text">若此 Email 有註冊帳號，系統會寄出密碼重設連結。</div>
          </div>

          <button type="submit" class="btn btn-primary w-100 fw-semibold">送出重設連結</button>

          <div class="text-center text-muted small">
            想起密碼了？
            <a href="login.php" class="text-decoration-none">返回登入</a>
          </div>
        </form>
      </div>
    </div>

</main>
</body>
</html>
