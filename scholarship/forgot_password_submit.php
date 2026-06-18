<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/password_reset_helpers.php";
require_once __DIR__ . "/mail_helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: forgot_password.php");
    exit;
}

$email = isset($_POST["email"]) ? trim($_POST["email"]) : "";
$genericMsg = "如果此 Email 有註冊帳號，密碼重設連結已送出。";

if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    site_flash_redirect("forgot_password.php", $genericMsg, "info");
}

ensure_password_resets_table($pdo);

$stmt = $pdo->prepare("
    SELECT ID, NAME, EMAIL
    FROM users
    WHERE EMAIL = ?
    LIMIT 1
");
$stmt->execute(array($email));
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $token = create_password_reset($pdo, $user["ID"]);
    $resetUrl = password_reset_url($token);
    $safeName = htmlspecialchars($user["NAME"], ENT_QUOTES, "UTF-8");
    $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, "UTF-8");

    $sent = scholarship_send_mail(
        $user["EMAIL"],
        $user["NAME"],
        "獎助學金系統密碼重設",
        scholarship_mail_html(array(
            "{$safeName} 您好：",
            "",
            "請點選以下連結重設您的密碼，連結 30 分鐘內有效：",
            "<a href=\"{$safeUrl}\">{$safeUrl}</a>",
            "",
            "如果您沒有申請重設密碼，請忽略此信。"
        ))
    );

    if (!$sent) {
        $_SESSION["password_reset_dev_link"] = $resetUrl;
    }
}

site_flash_redirect("forgot_password.php", $genericMsg, "info");
