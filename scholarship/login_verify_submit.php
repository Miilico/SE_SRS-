<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/login_helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

$pendingUserId = isset($_SESSION["pending_login_user_id"]) ? $_SESSION["pending_login_user_id"] : "";
$emailCode = isset($_POST["email_code"]) ? trim($_POST["email_code"]) : "";

if ($pendingUserId === "") {
    site_flash_redirect("login.php", "登入驗證已失效，請重新登入。", "warning");
}

if (!preg_match("/^[0-9]{6}$/", $emailCode)) {
    site_flash_redirect("login.php", "請輸入 6 位數驗證碼。", "danger");
}

if (!login_email_verification_available($pdo)) {
    unset($_SESSION["pending_login_user_id"], $_SESSION["pending_login_email"]);
    site_flash_redirect("login.php", "登入驗證功能尚未完成資料庫設定，請聯絡管理員。", "danger");
}

$stmt = $pdo->prepare("
    SELECT
        ID AS id,
        NAME AS name,
        ROLE AS role,
        EMAIL AS email,
        STATUS AS status,
        EMAIL_LOGIN_VERIFY_ENABLED AS email_login_verify_enabled,
        EMAIL_LOGIN_CODE AS email_login_code,
        EMAIL_LOGIN_CODE_EXPIRES_AT AS email_login_code_expires_at,
        CASE
            WHEN EMAIL_LOGIN_CODE_EXPIRES_AT IS NOT NULL
             AND EMAIL_LOGIN_CODE_EXPIRES_AT >= NOW()
            THEN 1
            ELSE 0
        END AS email_login_code_valid
    FROM users
    WHERE ID = :id
    LIMIT 1
");
$stmt->execute(array(":id" => $pendingUserId));
$u = $stmt->fetch();

if (!$u || $u["status"] !== "active" || (int)$u["email_login_verify_enabled"] !== 1) {
    unset($_SESSION["pending_login_user_id"], $_SESSION["pending_login_email"]);
    site_flash_redirect("login.php", "登入驗證已失效，請重新登入。", "warning");
}

if ((int)$u["email_login_code_valid"] !== 1 || $u["email_login_code"] === null) {
    login_clear_email_code($pdo, $pendingUserId);
    unset($_SESSION["pending_login_user_id"], $_SESSION["pending_login_email"]);
    site_flash_redirect("login.php", "驗證碼已逾期，請重新登入取得新的驗證碼。", "warning");
}

if ((string)$u["email_login_code"] !== $emailCode) {
    site_flash_redirect("login.php", "驗證碼錯誤，請重新輸入。", "danger");
}

login_clear_email_code($pdo, $pendingUserId);
unset($_SESSION["pending_login_user_id"], $_SESSION["pending_login_email"]);

login_store_user_session($pdo, $u);
login_redirect_after_success($u["role"]);
