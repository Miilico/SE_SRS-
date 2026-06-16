<?php require_once __DIR__ . "/config.php"; ?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>註冊｜獎助學金系統</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/scholarship/assets/css/auth.css">
</head>

<body>
  <nav class="navbar navbar-expand-lg bg-white border-bottom">
    <div class="container">
      <a class="navbar-brand fw-semibold text-decoration-none" href="login.php">
        <span class="brand-dot"></span>獎助學金系統
      </a>
    </div>
  </nav>

  <main class="auth-wrap">
    <div class="card auth-card">
      <div class="auth-header p-4">
        <div class="text-muted small">Create account</div>
        <h1 class="h4 mb-0 fw-bold">註冊</h1>
      </div>

      <div class="p-4">
        <?php if (!empty($_GET["err"])): ?>
          <div class="alert alert-danger">
            <?= htmlspecialchars($_GET["err"], ENT_QUOTES, "UTF-8") ?>
          </div>
        <?php endif; ?>

        <form method="post" action="register_submit.php" class="vstack gap-3">
          <div>
            <label class="form-label fw-semibold">身分</label>
            <select class="form-select" name="role" required>
              <option value="1">學生</option>
              <option value="2">教授</option>
            </select>
            <div class="form-text">註冊後需管理員審核通過才可登入。</div>
          </div>

          <div>
            <label class="form-label fw-semibold">使用者 ID（學號/教職員編號）</label>
            <input class="form-control" name="id" maxlength="10" required>
          </div>

          <div>
            <label class="form-label fw-semibold">姓名</label>
            <input class="form-control" name="name" maxlength="50" required>
          </div>

          <div>
            <label class="form-label fw-semibold">科系</label>
            <input class="form-control" name="dept" maxlength="50" required>
          </div>

	  <div>
  	  <label class="form-label fw-semibold">Email</label>
 	   <input
    	  class="form-control"
    	  type="email"
    	  name="email"
    	  maxlength="100"
    	  required
  	  >
	  </div>

	  <div>
 	  <label class="form-label fw-semibold">電話</label>
 	  <input
    	  class="form-control"
    	  name="tel"
    	  maxlength="20"
    	  required
  	  >
	  </div>


          <div>
            <label class="form-label fw-semibold">密碼</label>
            <input class="form-control" type="password" name="pwd" maxlength="64" required>
          </div>

          <div>
            <label class="form-label fw-semibold">確認密碼</label>
            <input class="form-control" type="password" name="pwd2" maxlength="64" required>
          </div>

          <button type="submit" class="btn btn-primary w-100 fw-semibold">送出註冊</button>

          <div class="text-center text-muted small">
            已經有帳號？
            <a href="login.php" class="text-decoration-none">回登入</a>
          </div>
        </form>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
