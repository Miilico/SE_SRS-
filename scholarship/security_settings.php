<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/file_helpers.php";
require_once __DIR__ . "/login_helpers.php";

require_login();

$target_id = isset($_SESSION["user"]["id"]) ? $_SESSION["user"]["id"] : null;
$message = "";
$messageType = "success";
$emailLoginVerificationAvailable = table_has_column($pdo, "users", "EMAIL_LOGIN_VERIFY_ENABLED")
    && table_has_column($pdo, "users", "EMAIL_LOGIN_CODE")
    && table_has_column($pdo, "users", "EMAIL_LOGIN_CODE_EXPIRES_AT");
$totpLoginVerificationAvailable = table_has_column($pdo, "users", "TOTP_LOGIN_VERIFY_ENABLED")
    && table_has_column($pdo, "users", "TOTP_SECRET");
$totpSetupSecret = "";
$totpSetupUri = "";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE ID = ?");
        $stmt->execute([$target_id]);
        $account = $stmt->fetch();

        if (!$account) {
            throw new Exception("找不到使用者資料，請重新登入。");
        }

        $emailLoginVerifyEnabled = ($emailLoginVerificationAvailable && isset($_POST["EMAIL_LOGIN_VERIFY_ENABLED"])) ? 1 : 0;
        $totpLoginVerifyEnabled = ($totpLoginVerificationAvailable && isset($_POST["TOTP_LOGIN_VERIFY_ENABLED"])) ? 1 : 0;
        $totpSetupCode = isset($_POST["TOTP_SETUP_CODE"]) ? trim($_POST["TOTP_SETUP_CODE"]) : "";
        $totpSecretToSave = $totpLoginVerificationAvailable ? (isset($account["TOTP_SECRET"]) ? $account["TOTP_SECRET"] : null) : null;
        $currentPassword = isset($_POST["CURRENT_PWD"]) ? $_POST["CURRENT_PWD"] : "";
        $newPassword = isset($_POST["NEW_PWD"]) ? $_POST["NEW_PWD"] : "";
        $confirmPassword = isset($_POST["CONFIRM_PWD"]) ? $_POST["CONFIRM_PWD"] : "";
        $changePassword = ($currentPassword !== "" || $newPassword !== "" || $confirmPassword !== "");
        $passwordHash = null;

        if ($emailLoginVerifyEnabled && !filter_var($account["EMAIL"], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("開啟 Email 登入驗證碼前，請先到個人資料填寫有效的 Email。");
        }

        if ($totpLoginVerifyEnabled) {
            $existingTotpEnabled = !empty($account["TOTP_LOGIN_VERIFY_ENABLED"]) && !empty($account["TOTP_SECRET"]);

            if (!$existingTotpEnabled) {
                $pendingSecret = isset($_SESSION["security_totp_setup_secret"]) ? $_SESSION["security_totp_setup_secret"] : "";
                if ($pendingSecret === "") {
                    throw new Exception("TOTP 設定金鑰已失效，請重新整理頁面後再試。");
                }

                if (!login_totp_verify_code($pendingSecret, $totpSetupCode)) {
                    throw new Exception("TOTP 驗證碼不正確，請確認驗證器 App 的時間同步後再試。");
                }

                $totpSecretToSave = $pendingSecret;
            }
        } else {
            $totpSecretToSave = null;
        }

        if ($changePassword) {
            if ($currentPassword === "" || $newPassword === "" || $confirmPassword === "") {
                throw new Exception("如需修改密碼，請完整填寫目前密碼、新密碼與確認新密碼。");
            }

            if (!password_verify($currentPassword, $account["PWD"])) {
                throw new Exception("目前密碼不正確。");
            }

            if ($newPassword !== $confirmPassword) {
                throw new Exception("新密碼與確認新密碼不一致。");
            }

            if (mb_strlen($newPassword) < 6) {
                throw new Exception("新密碼至少 6 碼。");
            }

            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        $setParts = [];
        $params = [];

        if ($emailLoginVerificationAvailable) {
            $setParts[] = "EMAIL_LOGIN_VERIFY_ENABLED = ?";
            $setParts[] = "EMAIL_LOGIN_CODE = NULL";
            $setParts[] = "EMAIL_LOGIN_CODE_EXPIRES_AT = NULL";
            $params[] = $emailLoginVerifyEnabled;
        }

        if ($totpLoginVerificationAvailable) {
            $setParts[] = "TOTP_LOGIN_VERIFY_ENABLED = ?";
            $setParts[] = "TOTP_SECRET = ?";
            $params[] = $totpLoginVerifyEnabled;
            $params[] = $totpSecretToSave;
        }

        if ($changePassword) {
            $setParts[] = "PWD = ?";
            $params[] = $passwordHash;
        }

        if (empty($setParts)) {
            throw new Exception("目前沒有可更新的安全設定。");
        }

        $params[] = $target_id;
        $stmt = $pdo->prepare("UPDATE users SET " . implode(", ", $setParts) . " WHERE ID = ?");
        $stmt->execute($params);

        if ($totpLoginVerificationAvailable) {
            unset($_SESSION["security_totp_setup_secret"]);
        }

        $message = "安全設定已更新。";
        $messageType = "success";
    } catch (Exception $e) {
        $message = "更新失敗：" . $e->getMessage();
        $messageType = "danger";
    }
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE ID = ?");
    $stmt->execute([$target_id]);
    $user = $stmt->fetch();

    if (!$user) {
        header("Location: login.php");
        exit;
    }

    if ($totpLoginVerificationAvailable && (empty($user["TOTP_LOGIN_VERIFY_ENABLED"]) || empty($user["TOTP_SECRET"]))) {
        $_SESSION["security_totp_setup_secret"] = login_totp_generate_secret();
        $totpSetupSecret = $_SESSION["security_totp_setup_secret"];
        $totpSetupUri = login_totp_otpauth_uri($user["ID"], $totpSetupSecret);
    }
} catch (PDOException $e) {
    http_response_code(500);
    exit("資料讀取錯誤");
}

$pageTitle = "安全設定";
$activeNav = "profile.php";
$siteHeaderRequireLogin = true;
$siteHeaderMainClass = "site-shell py-5";
$siteHeaderExtraHead = '
    <script src="/scholarship/assets/js/qrcode.min.js"></script>
    <style>
        .totp-qr-card {
            max-width: 220px;
        }

        .totp-qr-box {
            width: 180px;
            height: 180px;
            margin: 0 auto;
        }

        .totp-qr-box canvas,
        .totp-qr-box img {
            width: 180px;
            height: 180px;
        }
    </style>
';
require __DIR__ . "/header.php";
?>
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
                        <div>
                            <h1 class="h3 fw-bold mb-1">安全設定</h1>
                            <div class="text-secondary">管理登入驗證方式與帳號密碼。</div>
                        </div>
                        <a href="security.php" class="btn btn-outline-secondary align-self-start">返回安全設定</a>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo h($messageType); ?>" role="alert">
                            <?php echo h($message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <section class="mb-4">
                            <div class="border-start border-4 border-primary bg-body-tertiary px-3 py-2 fw-bold mb-3">
                                登入驗證設定
                            </div>

                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                                    <div>
                                        <div class="fw-bold">Email 登入驗證碼</div>
                                        <div class="text-secondary small">目前 Email：<?php echo h($user["EMAIL"] !== "" && $user["EMAIL"] !== null ? $user["EMAIL"] : "尚未填寫"); ?></div>
                                    </div>
                                </div>

                                <?php if ($emailLoginVerificationAvailable): ?>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="EMAIL_LOGIN_VERIFY_ENABLED" name="EMAIL_LOGIN_VERIFY_ENABLED" value="1" <?php echo !empty($user["EMAIL_LOGIN_VERIFY_ENABLED"]) ? "checked" : ""; ?>>
                                        <label class="form-check-label fw-semibold" for="EMAIL_LOGIN_VERIFY_ENABLED">開啟 Email 登入驗證碼</label>
                                        <div class="form-text">開啟後，每次密碼正確後都需輸入寄到 Email 的 6 位數驗證碼。</div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-0">
                                        Email 登入驗證碼欄位尚未建立，請先執行資料庫 SQL。
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="border rounded p-3">
                                <div class="fw-bold mb-2">TOTP 驗證器 App</div>

                                <?php if ($totpLoginVerificationAvailable): ?>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" role="switch" id="TOTP_LOGIN_VERIFY_ENABLED" name="TOTP_LOGIN_VERIFY_ENABLED" value="1" <?php echo !empty($user["TOTP_LOGIN_VERIFY_ENABLED"]) ? "checked" : ""; ?>>
                                        <label class="form-check-label fw-semibold" for="TOTP_LOGIN_VERIFY_ENABLED">開啟 TOTP 驗證器 App</label>
                                        <div class="form-text">可使用 Google Authenticator、Microsoft Authenticator、1Password 等支援 TOTP 的 App。</div>
                                    </div>

                                    <?php if (empty($user["TOTP_LOGIN_VERIFY_ENABLED"]) || empty($user["TOTP_SECRET"])): ?>
                                        <div class="bg-body-tertiary border rounded p-3 mt-3">
                                            <div class="fw-semibold mb-2">首次啟用 TOTP</div>
                                            <div class="small text-secondary mb-3">使用驗證器 App 掃描 QR code，或手動輸入設定金鑰；加入帳號後輸入 App 顯示的 6 位數代碼再儲存。</div>

                                            <div class="row g-3 align-items-start">
                                                <div class="col-12 col-md-auto">
                                                    <div class="totp-qr-card bg-white border rounded p-3 text-center">
                                                        <div id="TOTP_SETUP_QR" class="totp-qr-box" data-totp-uri="<?php echo h($totpSetupUri); ?>"></div>
                                                        <div id="TOTP_QR_FALLBACK" class="small text-secondary mt-2 d-none">QR code 載入失敗，請改用設定金鑰。</div>
                                                    </div>
                                                </div>

                                                <div class="col-12 col-md">
                                                    <div class="mb-2">
                                                        <label class="form-label small text-secondary mb-1" for="TOTP_SETUP_SECRET">設定金鑰</label>
                                                        <input class="form-control font-monospace" id="TOTP_SETUP_SECRET" type="text" value="<?php echo h(login_totp_format_secret($totpSetupSecret)); ?>" readonly>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small text-secondary mb-1" for="TOTP_SETUP_URI">otpauth URI</label>
                                                        <input class="form-control font-monospace" id="TOTP_SETUP_URI" type="text" value="<?php echo h($totpSetupUri); ?>" readonly>
                                                    </div>
                                                    <div>
                                                        <label class="form-label fw-semibold" for="TOTP_SETUP_CODE">TOTP 驗證碼</label>
                                                        <input class="form-control" id="TOTP_SETUP_CODE" name="TOTP_SETUP_CODE" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="勾選 TOTP 時請輸入 6 位數代碼" autocomplete="one-time-code">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="form-text">取消勾選並儲存後會清除目前綁定的 TOTP 金鑰。</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-0">
                                        TOTP 登入驗證欄位尚未建立，請先執行資料庫 SQL。
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="mb-4">
                            <div class="border-start border-4 border-primary bg-body-tertiary px-3 py-2 fw-bold mb-3">
                                修改密碼
                            </div>
                            <div class="text-secondary small mb-3"><span class="text-danger" aria-label="條件式必填">*</span> 如需修改密碼，目前密碼、新密碼與確認新密碼皆需填寫。</div>

                            <div class="mb-3">
                                <label for="CURRENT_PWD" class="form-label fw-semibold">目前密碼</label>
                                <input type="password" id="CURRENT_PWD" name="CURRENT_PWD" class="form-control" autocomplete="current-password" placeholder="不修改密碼可留空">
                            </div>

                            <div class="mb-3">
                                <label for="NEW_PWD" class="form-label fw-semibold">新密碼</label>
                                <input type="password" id="NEW_PWD" name="NEW_PWD" class="form-control" minlength="6" autocomplete="new-password" placeholder="至少 6 碼">
                            </div>

                            <div class="mb-0">
                                <label for="CONFIRM_PWD" class="form-label fw-semibold">確認新密碼</label>
                                <input type="password" id="CONFIRM_PWD" name="CONFIRM_PWD" class="form-control" minlength="6" autocomplete="new-password" placeholder="再次輸入新密碼">
                            </div>
                        </section>

                        <div class="d-flex flex-column flex-sm-row gap-2 pt-2">
                            <button type="submit" class="btn btn-primary">儲存安全設定</button>
                            <a href="security.php" class="btn btn-outline-secondary">取消</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function() {
            var qrBox = document.getElementById("TOTP_SETUP_QR");
            if (!qrBox) {
                return;
            }

            var fallback = document.getElementById("TOTP_QR_FALLBACK");
            var uri = qrBox.getAttribute("data-totp-uri") || "";
            if (!uri || typeof QRCode === "undefined") {
                if (fallback) {
                    fallback.classList.remove("d-none");
                }
                return;
            }

            try {
                new QRCode(qrBox, {
                    text: uri,
                    width: 180,
                    height: 180,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            } catch (error) {
                if (fallback) {
                    fallback.classList.remove("d-none");
                }
            }
        })();
    </script>
</main>
</body>
</html>
