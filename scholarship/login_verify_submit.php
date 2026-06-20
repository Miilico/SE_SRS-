<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/login_helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

$pendingUserId = isset($_SESSION["pending_login_user_id"]) ? $_SESSION["pending_login_user_id"] : "";
$requiresEmail = !empty($_SESSION["pending_login_requires_email"]);
$requiresTotp = !empty($_SESSION["pending_login_requires_totp"]);
$emailCode = isset($_POST["email_code"]) ? trim($_POST["email_code"]) : "";
$totpCode = isset($_POST["totp_code"]) ? trim($_POST["totp_code"]) : "";

if ($pendingUserId === "") {
    site_flash_redirect("login.php", "登入驗證已失效，請重新登入。", "warning");
}

if (!$requiresEmail && !$requiresTotp) {
    login_clear_pending_session();
    site_flash_redirect("login.php", "登入驗證已失效，請重新登入。", "warning");
}

if ($requiresEmail && !preg_match("/^[0-9]{6}$/", $emailCode)) {
    site_flash_redirect("login.php", "請輸入 6 位數驗證碼。", "danger");
}

if ($requiresTotp && !preg_match("/^[0-9]{6}$/", $totpCode)) {
    site_flash_redirect("login.php", "請輸入 6 位數 TOTP 驗證碼。", "danger");
}

if ($requiresEmail && !login_email_verification_available($pdo)) {
    login_clear_pending_session();
    site_flash_redirect("login.php", "登入驗證功能尚未完成資料庫設定，請聯絡管理員。", "danger");
}

$emailSelect = $requiresEmail ? ",
        EMAIL_LOGIN_VERIFY_ENABLED AS email_login_verify_enabled,
        EMAIL_LOGIN_CODE AS email_login_code,
        EMAIL_LOGIN_CODE_EXPIRES_AT AS email_login_code_expires_at,
        CASE
            WHEN EMAIL_LOGIN_CODE_EXPIRES_AT IS NOT NULL
             AND EMAIL_LOGIN_CODE_EXPIRES_AT >= NOW()
            THEN 1
            ELSE 0
        END AS email_login_code_valid" : "";
$totpSelect = $requiresTotp ? ",
        TOTP_LOGIN_VERIFY_ENABLED AS totp_login_verify_enabled,
        TOTP_SECRET AS totp_secret" : "";

if ($requiresTotp && !login_totp_verification_available($pdo)) {
    login_clear_pending_session();
    site_flash_redirect("login.php", "TOTP 登入驗證功能尚未完成資料庫設定，請聯絡管理員。", "danger");
}

$stmt = $pdo->prepare("
    SELECT
        ID AS id,
        NAME AS name,
        ROLE AS role,
        EMAIL AS email,
        STATUS AS status
        " . $emailSelect . "
        " . $totpSelect . "
    FROM users
    WHERE ID = :id
    LIMIT 1
");
$stmt->execute(array(":id" => $pendingUserId));
$u = $stmt->fetch();

if (!$u || $u["status"] !== "active") {
    login_clear_pending_session();
    site_flash_redirect("login.php", "登入驗證已失效，請重新登入。", "warning");
}

if ($requiresEmail) {
    if ((int)$u["email_login_verify_enabled"] !== 1) {
        login_clear_pending_session();
        site_flash_redirect("login.php", "登入驗證已失效，請重新登入。", "warning");
    }

    if ((int)$u["email_login_code_valid"] !== 1 || $u["email_login_code"] === null) {
        login_clear_email_code($pdo, $pendingUserId);
        login_clear_pending_session();
        site_flash_redirect("login.php", "驗證碼已逾期，請重新登入取得新的驗證碼。", "warning");
    }

    if ((string)$u["email_login_code"] !== $emailCode) {
        site_flash_redirect("login.php", "Email 驗證碼錯誤，請重新輸入。", "danger");
    }
}

if ($requiresTotp) {
    if ((int)$u["totp_login_verify_enabled"] !== 1 || empty($u["totp_secret"])) {
        login_clear_pending_session();
        site_flash_redirect("login.php", "TOTP 登入驗證已失效，請重新登入。", "warning");
    }

    if (!login_totp_verify_code($u["totp_secret"], $totpCode)) {
        site_flash_redirect("login.php", "TOTP 驗證碼錯誤，請重新輸入。", "danger");
    }
}

login_clear_email_code($pdo, $pendingUserId);
login_clear_pending_session();

login_store_user_session($pdo, $u);
login_redirect_after_success($u["role"]);
