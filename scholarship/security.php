<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/file_helpers.php";

require_login();

$target_id = isset($_SESSION["user"]["id"]) ? $_SESSION["user"]["id"] : null;
$emailLoginVerificationAvailable = table_has_column($pdo, "users", "EMAIL_LOGIN_VERIFY_ENABLED");
$totpLoginVerificationAvailable = table_has_column($pdo, "users", "TOTP_LOGIN_VERIFY_ENABLED")
    && table_has_column($pdo, "users", "TOTP_SECRET");

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$user = null;
$sections = [];
$errorMessage = "";

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE ID = ?");
    $stmt->execute([$target_id]);
    $user = $stmt->fetch();

    if ($user) {
        $loginItems = [];

        if ($emailLoginVerificationAvailable) {
            $loginItems["Email 登入驗證碼"] = !empty($user["EMAIL_LOGIN_VERIFY_ENABLED"]) ? "已開啟" : "未開啟";
        } else {
            $loginItems["Email 登入驗證碼"] = "欄位尚未建立";
        }

        if ($totpLoginVerificationAvailable) {
            $loginItems["TOTP 驗證器 App"] = (!empty($user["TOTP_LOGIN_VERIFY_ENABLED"]) && !empty($user["TOTP_SECRET"])) ? "已開啟" : "未開啟";
        } else {
            $loginItems["TOTP 驗證器 App"] = "欄位尚未建立";
        }

        $sections[] = [
            "title" => "登入驗證狀態",
            "items" => $loginItems,
        ];

        $sections[] = [
            "title" => "密碼設定",
            "items" => [
                "帳號密碼" => !empty($user["PWD"]) ? "已設定" : "尚未設定",
            ],
        ];
    } else {
        $errorMessage = "找不到該使用者資料，請重新登入。";
    }
} catch (PDOException $e) {
    $errorMessage = "資料庫錯誤：" . $e->getMessage();
}

$pageTitle = "安全設定狀態";
$activeNav = "profile.php";
$siteHeaderRequireLogin = true;
$siteHeaderMainClass = "site-shell py-5";
$siteHeaderExtraHead = '
    <style>
        .profile-settings-sidebar {
            border-right: 1px solid var(--site-border);
        }

        @media (max-width: 767.98px) {
            .profile-settings-sidebar {
                border-right: 0;
                border-bottom: 1px solid var(--site-border);
                padding-bottom: 16px;
            }
        }
    </style>
';
require __DIR__ . "/header.php";
?>
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <div class="row g-4">
                        <aside class="col-12 col-md-3 profile-settings-sidebar">
                            <div class="fw-bold mb-3">個人設定</div>
                            <nav class="nav nav-pills flex-column gap-2" aria-label="個人設定二級目錄">
                                <a class="nav-link" href="profile.php">資料修改</a>
                                <a class="nav-link active" href="security.php">安全設定</a>
                            </nav>
                        </aside>

                        <div class="col-12 col-md-9">
                            <div class="mb-4">
                                <h1 class="h3 fw-bold mb-1">安全設定狀態</h1>
                                <div class="text-secondary">查看目前登入驗證與密碼設定狀態。</div>
                            </div>

                            <?php if ($errorMessage): ?>
                                <div class="alert alert-danger mb-0"><?php echo h($errorMessage); ?></div>
                            <?php else: ?>
                                <?php foreach ($sections as $section): ?>
                                    <section class="mb-4">
                                        <div class="border-start border-4 border-primary bg-body-tertiary px-3 py-2 fw-bold mb-2">
                                            <?php echo h($section["title"]); ?>
                                        </div>

                                        <div class="list-group list-group-flush border rounded">
                                            <?php foreach ($section["items"] as $label => $value): ?>
                                                <div class="list-group-item">
                                                    <div class="row g-2 align-items-center">
                                                        <div class="col-sm-4 text-secondary fw-semibold"><?php echo h($label); ?></div>
                                                        <div class="col-sm-8"><?php echo h($value !== "" && $value !== null ? $value : "尚未填寫"); ?></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                <?php endforeach; ?>

                                <div class="d-flex flex-column flex-sm-row gap-2 pt-2">
                                    <a href="security_settings.php" class="btn btn-primary">修改</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
