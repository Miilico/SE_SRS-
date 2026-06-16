<?php
require_once __DIR__ . "/config.php";

$pageTitle = "登入";
$activeNav = "login.php";
$siteHeaderMainClass = "auth-wrap";
$siteHeaderStylesheets = array("/scholarship/assets/css/auth.css");
require __DIR__ . "/header.php";
?>
    <div class="card auth-card">
      <div class="auth-header p-4">
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
        <?php if (!empty($_GET["msg"])): ?>
          <div class="alert alert-warning mb-3">
            <?= htmlspecialchars($_GET["msg"], ENT_QUOTES, "UTF-8") ?>
          </div>
        <?php endif; ?>
        <form method="post" action="login_submit.php" class="vstack gap-3">
          <div>
            <label class="form-label fw-semibold">使用者 ID</label>
            <input class="form-control" name="id" maxlength="10" required placeholder="例如：S123456789" autocomplete="username">
          </div>

          <div>
            <label class="form-label fw-semibold">密碼</label>
            <input class="form-control" type="password" name="pwd" maxlength="64" required placeholder="請輸入密碼" autocomplete="current-password">
          </div>

          <button type="submit" class="btn btn-primary w-100 fw-semibold">
            登入
          </button>

          <div class="text-center text-muted small mt-1">
            若帳號/密碼有問題，請洽系辦或管理員
          </div>

	  <div class="text-center mt-3">
  	  <span class="text-muted small">還沒有帳號？</span>
 	   <a class="btn btn-outline-primary btn-sm ms-2" href="register.php">註冊</a>
	  </div>
        </form>
      </div>
    </div>

</main>
</body>
</html>
