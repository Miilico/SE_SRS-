<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/login_helpers.php";

ini_set('display_errors', 1);
error_reporting(E_ALL);

//$id  = trim($_POST["id"] ?? "");
$id = isset($_POST["id"]) ? trim($_POST["id"]) : "";
//$pwd = $_POST["pwd"] ?? "";
//$pwd = isset($_POST["pwd"]) ? trim($_POST["pwd"]) : "";
$pwd = isset($_POST["pwd"]) ? $_POST["pwd"] : "";

if ($id === "" || $pwd === "") {
  site_flash_redirect("login.php", "請輸入帳號與密碼", "danger");
} 

/**
 * 重點：用 alias 把欄位統一成小寫 key
 * 不管你的資料表是 ID / PWD / STATUS 或 id / pwd / status 都能正常取值
 */
$sql = "
  SELECT 
    ID AS id,
    NAME AS name,
    ROLE AS role,
    PWD AS pwd,
    EMAIL AS email,
    STATUS AS status
  FROM users 
  WHERE ID = :id
  LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([":id" => $id]);
$u = $stmt->fetch();



if (!$u) {
  site_flash_redirect("login.php", "帳號不存在", "danger");
}

// ✅ 密碼用 password_verify（因為你 PWD 裡是 $2y$... hash）
if (!password_verify($pwd, $u["pwd"])) {
  site_flash_redirect("login.php", "密碼錯誤", "danger");
}

// ✅ 檢查狀態
if ((!isset($u["status"]) || $u["status"] !== "active")) {
    site_flash_redirect("login.php", "此帳號尚未啟用（請等待管理員審核）", "warning");
}

if (login_email_verification_is_enabled($pdo, $u["id"])) {
  if (!login_send_email_code($pdo, $u)) {
    site_flash_redirect("login.php", "登入驗證碼寄送失敗，請確認 Email 或稍後再試。", "danger");
  }

  unset($_SESSION["user"]);
  session_regenerate_id(true);
  $_SESSION["pending_login_user_id"] = $u["id"];
  $_SESSION["pending_login_email"] = login_mask_email($u["email"]);
  site_flash_redirect("login.php", "驗證碼已寄出，請於 10 分鐘內輸入。", "info");
}

login_store_user_session($pdo, $u);
login_redirect_after_success($u["role"]);

?>
