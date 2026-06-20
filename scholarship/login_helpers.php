<?php

require_once __DIR__ . "/mail_helpers.php";

function login_email_verification_available($pdo)
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME IN ('EMAIL_LOGIN_VERIFY_ENABLED', 'EMAIL_LOGIN_CODE', 'EMAIL_LOGIN_CODE_EXPIRES_AT')
    ");
    $stmt->execute();
    return (int)$stmt->fetchColumn() === 3;
}

function login_email_verification_is_enabled($pdo, $userId)
{
    if (!login_email_verification_available($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT EMAIL_LOGIN_VERIFY_ENABLED FROM users WHERE ID = ? LIMIT 1");
    $stmt->execute(array($userId));
    return (int)$stmt->fetchColumn() === 1;
}

function login_store_user_session($pdo, $u)
{
    session_regenerate_id(true);

    $_SESSION["user"] = array(
        "id" => $u["id"],
        "name" => $u["name"],
        "role" => (int)$u["role"],
        "status" => $u["status"],
    );

    if ((int)$u["role"] === 1) {
        $stmt = $pdo->prepare("SELECT ID FROM students WHERE SID = :sid");
        $stmt->execute(array(":sid" => $u["id"]));
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($student) {
            $_SESSION["user"]["stid"] = $student["ID"];
        }
    }
}

function login_redirect_after_success($role)
{
    if ((int)$role === 3) {
        header("Location: /scholarship/admin/admin_dashboard.php");
    } else {
        header("Location: /scholarship/index.php");
    }
    exit;
}

function login_clear_email_code($pdo, $userId)
{
    if (!login_email_verification_available($pdo)) {
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE users
        SET EMAIL_LOGIN_CODE = NULL,
            EMAIL_LOGIN_CODE_EXPIRES_AT = NULL
        WHERE ID = ?
    ");
    $stmt->execute(array($userId));
}

function login_send_email_code($pdo, $u)
{
    if (empty($u["email"]) || !filter_var($u["email"], FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $code = random_int(100000, 999999);

    $stmt = $pdo->prepare("
        UPDATE users
        SET EMAIL_LOGIN_CODE = :code,
            EMAIL_LOGIN_CODE_EXPIRES_AT = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
        WHERE ID = :id
    ");
    $stmt->execute(array(
        ":code" => $code,
        ":id" => $u["id"],
    ));

    $safeCode = htmlspecialchars((string)$code, ENT_QUOTES, "UTF-8");
    $body = scholarship_mail_html(array(
        "<p>您好，" . htmlspecialchars($u["name"], ENT_QUOTES, "UTF-8") . "：</p>",
        "<p>您的登入驗證碼是：</p>",
        "<p style=\"font-size:24px;font-weight:bold;letter-spacing:4px;\">" . $safeCode . "</p>",
        "<p>此驗證碼 10 分鐘內有效。若您沒有嘗試登入，請忽略此信件。</p>",
        "<p>系統自動發送，請勿直接回覆本信件。</p>",
    ));

    $sent = scholarship_send_mail($u["email"], $u["name"], "高大獎助學金系統登入驗證碼", $body);
    if (!$sent) {
        login_clear_email_code($pdo, $u["id"]);
    }

    return $sent;
}

function login_mask_email($email)
{
    $email = (string)$email;
    $parts = explode("@", $email, 2);
    if (count($parts) !== 2) {
        return $email;
    }

    $name = $parts[0];
    $domain = $parts[1];
    $visible = substr($name, 0, 2);
    return $visible . str_repeat("*", max(2, strlen($name) - 2)) . "@" . $domain;
}
