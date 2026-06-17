<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/password_reset_helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: forgot_password.php");
    exit;
}

$token = isset($_POST["token"]) ? trim($_POST["token"]) : "";
$pwd = isset($_POST["pwd"]) ? $_POST["pwd"] : "";
$pwd2 = isset($_POST["pwd2"]) ? $_POST["pwd2"] : "";

function reset_back($token, $message)
{
    header("Location: reset_password.php?token=" . urlencode($token) . "&err=" . urlencode($message));
    exit;
}

$reset = $token === "" ? null : find_valid_password_reset($pdo, $token);
if (!$reset) {
    header("Location: forgot_password.php?msg=" . urlencode("重設連結無效、已使用或已逾期，請重新申請。"));
    exit;
}

if ($pwd === "" || $pwd2 === "") {
    reset_back($token, "請輸入新密碼與確認密碼。");
}

if (mb_strlen($pwd, "UTF-8") < 6) {
    reset_back($token, "密碼至少 6 碼。");
}

if ($pwd !== $pwd2) {
    reset_back($token, "兩次密碼不一致。");
}

$hash = password_hash($pwd, PASSWORD_DEFAULT);

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("UPDATE users SET PWD = ? WHERE ID = ?");
    $stmt->execute(array($hash, $reset["user_id"]));

    $stmt = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
    $stmt->execute(array($reset["id"]));

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    reset_back($token, "密碼更新失敗，請稍後再試。");
}

header("Location: login.php?msg=" . urlencode("密碼已更新，請使用新密碼登入。"));
exit;
