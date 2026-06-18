<?php
require_once __DIR__ . "/config.php";

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


session_regenerate_id(true);

// ✅ session 統一用小寫 key（之後判斷也比較不會混亂）
$_SESSION["user"] = [
  "id" => $u["id"],
  "name" => $u["name"],
  "role" => (int)$u["role"],
  "status" => $u["status"]
];

// 如果是學生，查 students 表，存對應的 ID 
if ((int)$u["role"] === 1) { 
  $stmt = $pdo->prepare("SELECT ID FROM students WHERE SID = :sid"); 
  $stmt->execute([":sid" => $u["id"]]); // SID = 學號 (等於 users.id) 
  $student = $stmt->fetch(PDO::FETCH_ASSOC); 
  if ($student) { 
    $_SESSION["user"]["stid"] = $student["ID"]; // 學生表主鍵 ID 
  } //else { // 沒找到學生資料，視情況要不要丟錯或提示 $_SESSION["user"]["stid"] = null; } }
}
// 依角色導向
if ((int)$u["role"] === 3) {
  header("Location: /scholarship/admin/admin_dashboard.php");
} else {
  header("Location: /scholarship/index.php");
}
exit;

?>
