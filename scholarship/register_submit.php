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
$contact_person = isset($_POST["contact_person"]) ? trim($_POST["contact_person"]) : "";
$org_phones_raw = isset($_POST["org_phones"]) ? trim($_POST["org_phones"]) : "";
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

// 3) 角色限制：允許學生、教授、獎助單位自註冊
//$role = (int)($_POST["role"] ?? 0);
$role = isset($_POST["role"]) ? (int)$_POST["role"] : 0;


if (!in_array($role, [1, 2, 4], true)) {
  back_err("身分不合法。");
}

if (($role === 1 || $role === 2) && $dept === "") {
  back_err("請填寫科系。");
}

if ($role === 4 && $contact_person === "") {
  back_err("請填寫單位聯絡人姓名。");
}

// 4) email 格式（基本檢查）
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  back_err("Email 格式不正確。");
}

// 學生與教師需使用校內信箱；獎助單位可使用單位信箱。
if (($role === 1 || $role === 2) && !preg_match('/@mail\.nuk\.edu\.tw$/i', $email)) {
  back_err("請使用學校信箱註冊。");
}

// 5) tel 格式（資料庫欄位目前是 varchar(10)，避免寫入時被截斷）
if (!preg_match('/^[0-9+\-\s()]{6,10}$/', $tel)) {
  back_err("電話格式不正確。");
}

$org_phones = [];
if ($role === 4) {
  $phone_candidates = array_merge([$tel], $org_phones_raw === "" ? [] : explode(",", $org_phones_raw));
  foreach ($phone_candidates as $phone) {
    $phone = trim($phone);
    if ($phone === "") {
      continue;
    }
    if (!preg_match('/^[0-9+\-\s()]{6,10}$/', $phone)) {
      back_err("單位電話格式不正確。");
    }
    if (!in_array($phone, $org_phones, true)) {
      $org_phones[] = $phone;
    }
  }
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

$stmt = $pdo->prepare("SELECT 1 FROM users WHERE name = ?");
$stmt->execute([$name]);
if ($stmt->fetchColumn()) {
  back_err("此姓名或單位名稱已被使用。");
}

$hash = password_hash($pwd, PASSWORD_DEFAULT);

// 學生、教師免審核；獎助單位需管理員審核。
$status = ($role === 4) ? "pending" : "active";

try {
  $pdo->beginTransaction();

  // 8) 寫入 users
  $stmt = $pdo->prepare("
    INSERT INTO users (id, role, name, email, tel, pwd, status, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
  ");
  $stmt->execute([$id, $role, $name, $email, $tel, $hash, $status]);

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

  // 11) 如果是獎助單位，建立 organization 與 ophone 表紀錄
  if ($role === 4) {
    $stmt = $pdo->prepare(" INSERT INTO organization (ID, ONAME, CONTACT) VALUES (?, ?, ?) ");
    $stmt->execute([$id, $name, $contact_person]);

    $stmt = $pdo->prepare(" INSERT INTO ophone (ID, TEL) VALUES (?, ?) ");
    foreach ($org_phones as $phone) {
      $stmt->execute([$id, $phone]);
    }
  }

  $pdo->commit();
} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  back_err("註冊失敗，請確認資料是否完整或已被使用。");
}

$msg = ($role === 4)
  ? "註冊成功！請等待管理員審核後再登入。"
  : "註冊成功！請直接登入。";

header("Location: login.php?msg=" . urlencode($msg));
exit;
?>
