<?php
require_once __DIR__ . "/config.php";
/*
$id    = trim($_POST["id"] ?? "");          // 學號/教職員編號
$role  = trim($_POST["role"] ?? "");        // student / professor
$name  = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");
$tel   = trim($_POST["tel"] ?? "");
$pwd   = $_POST["pwd"] ?? "";
$pwd2  = $_POST["pwd2"] ?? "";
*/
$id    = isset($_POST["id"])    ? trim($_POST["id"])    : "";   // 學號/教職員編號
$role  = isset($_POST["role"])  ? trim($_POST["role"])  : "";   // student / professor
$name  = isset($_POST["name"])  ? trim($_POST["name"])  : "";
$dept = isset($_POST["dept"]) ? trim($_POST["dept"]) : ""; // 新增科系欄位
$email = isset($_POST["email"]) ? trim($_POST["email"]) : "";
$tel   = isset($_POST["tel"])   ? trim($_POST["tel"])   : "";
$pwd   = isset($_POST["pwd"])   ? $_POST["pwd"]         : "";
$pwd2  = isset($_POST["pwd2"])  ? $_POST["pwd2"]        : "";

/*
function back_err(string $msg): void {
  header("Location: register.php?err=" . urlencode($msg));
  exit;
}
*/
function back_err($msg) {
    header("Location: register.php?err=" . urlencode($msg));
    exit;
}


// 1) 必填檢查
if ($id === "" || $role === "" || $name === "" || $email === "" || $tel === "" || $pwd === "" || $pwd2 === "") {
  back_err("請完整填寫所有欄位。");
}

// 2) id 格式（英數 1~10）
if (!preg_match('/^[A-Za-z0-9]{1,10}$/', $id)) {
  back_err("使用者 ID 只能是英數，且長度最多 10。");
}

// 3) 角色限制：只允許 student/professor 自註冊
//$role = (int)($_POST["role"] ?? 0);
$role = isset($_POST["role"]) ? (int)$_POST["role"] : 0;


if (!in_array($role, [1, 2], true)) {
  back_err("身分不合法。");
}

// 4) email 格式（基本檢查）
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  back_err("Email 格式不正確。");
}

// 校內信箱驗證
if (!preg_match('/@mail\.nuk\.edu\.tw$/i', $email)) {
  back_err("請使用學校信箱註冊。");
}

// 5) tel 格式（給寬鬆一點：數字、+、-、空白、括號）
if (!preg_match('/^[0-9+\-\s()]{6,20}$/', $tel)) {
  back_err("電話格式不正確。");
}

// 6) 密碼一致與長度
if ($pwd !== $pwd2) {
  back_err("兩次密碼不一致。");
}
if (mb_strlen($pwd) < 6) {
  back_err("密碼至少 6 碼。");
}

// 7) 檢查 id 是否重複
$stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ?");
$stmt->execute([$id]);
if ($stmt->fetchColumn()) {
  back_err("此 ID 已存在。");
}

//email 唯一的話
$stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetchColumn()) {
  back_err("此 Email 已被使用。");
}

$hash = password_hash($pwd, PASSWORD_DEFAULT);

// 8) 寫入 users（pending）
$stmt = $pdo->prepare("
  INSERT INTO users (id, role, name, email, tel, pwd, status, created_at)
  VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
");
$stmt->execute([$id, $role, $name, $email, $tel, $hash]);

// 9) 如果是學生，建立 students 表紀錄 
if ($role === 1) { 
 $stmt = $pdo->prepare(" INSERT INTO students (ID, SID, DNAME) VALUES (?, ?, ?) "); 
 // ID = users.id (外鍵), SID = 學號 (這裡預設等於 user id), DNAME = 科系 
 $stmt->execute([$id, $id, $dept]);
}

// 10) 如果是老師，建立 teachers 表紀錄 
if ($role === 2) { 
 $stmt = $pdo->prepare(" INSERT INTO teachers (ID, DNAME) VALUES (?, ?) "); 
 // ID = users.id (外鍵), DNAME = 科系 
 $stmt->execute([$id, $dept]);
}

header("Location: login.php?msg=" . urlencode("註冊成功！請等待管理員審核後再登入。"));
exit;
?>