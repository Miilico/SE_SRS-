<?php
require_once __DIR__ . "/config.php";

$id = isset($_POST["id"]) ? trim($_POST["id"]) : "";
$roleInput = isset($_POST["role"]) ? trim($_POST["role"]) : "";
$role = (int)$roleInput;
$name = isset($_POST["name"]) ? trim($_POST["name"]) : "";
$dept = isset($_POST["dept"]) ? trim($_POST["dept"]) : "";
$contactPerson = isset($_POST["contact_person"]) ? trim($_POST["contact_person"]) : "";
$orgPhonesRaw = isset($_POST["org_phones"]) ? trim($_POST["org_phones"]) : "";
$email = isset($_POST["email"]) ? trim($_POST["email"]) : "";
$tel = isset($_POST["tel"]) ? trim($_POST["tel"]) : "";
$pwd = isset($_POST["pwd"]) ? $_POST["pwd"] : "";
$pwd2 = isset($_POST["pwd2"]) ? $_POST["pwd2"] : "";

function register_old_input()
{
    return array(
        "role" => isset($_POST["role"]) ? trim($_POST["role"]) : "",
        "id" => isset($_POST["id"]) ? trim($_POST["id"]) : "",
        "name" => isset($_POST["name"]) ? trim($_POST["name"]) : "",
        "dept" => isset($_POST["dept"]) ? trim($_POST["dept"]) : "",
        "contact_person" => isset($_POST["contact_person"]) ? trim($_POST["contact_person"]) : "",
        "org_phones" => isset($_POST["org_phones"]) ? trim($_POST["org_phones"]) : "",
        "email" => isset($_POST["email"]) ? trim($_POST["email"]) : "",
        "tel" => isset($_POST["tel"]) ? trim($_POST["tel"]) : "",
    );
}

function back_err($msg, $field)
{
    $_SESSION["register_old"] = register_old_input();
    $_SESSION["register_error_field"] = $field;
    $_SESSION["register_error_message"] = $msg;
    site_flash_redirect("register.php", $msg, "danger");
}

function first_empty_required_field($id, $roleInput, $name, $email, $tel, $pwd, $pwd2)
{
    if ($id === "") return "id";
    if ($roleInput === "") return "role";
    if ($name === "") return "name";
    if ($email === "") return "email";
    if ($tel === "") return "tel";
    if ($pwd === "") return "pwd";
    if ($pwd2 === "") return "pwd2";
    return "id";
}

$validDepts = array(
    "西洋語文學系",
    "運動健康與休閒學系",
    "東亞語文學系",
    "運動競技學系",
    "建築學系",
    "工藝與創意設計學系",
    "法律學系",
    "政治法律學系",
    "財經法律學系",
    "應用經濟學系",
    "亞太工商管理學系",
    "財務金融學系",
    "資訊管理學系",
    "應用數學系",
    "生命科學系",
    "應用化學系",
    "應用物理學系",
    "電機工程學系",
    "土木與環境工程學系",
    "化材系",
    "資訊工程學系",
);

if ($id === "" || $roleInput === "" || $name === "" || $email === "" || $tel === "" || $pwd === "" || $pwd2 === "") {
    back_err("請完整填寫所有欄位。", first_empty_required_field($id, $roleInput, $name, $email, $tel, $pwd, $pwd2));
}

if (!preg_match('/^[A-Za-z0-9]{1,10}$/', $id)) {
    back_err("使用者 ID 只能是英數字，且長度最多 10 碼。", "id");
}

if (!in_array($role, array(1, 2, 4), true)) {
    back_err("身分不合法。", "role");
}

if (($role === 1 || $role === 2) && $dept === "") {
    back_err("請選擇科系。", "dept");
}

if (($role === 1 || $role === 2) && !in_array($dept, $validDepts, true)) {
    back_err("請選擇有效的科系。", "dept");
}

if ($role === 4 && $contactPerson === "") {
    back_err("請填寫單位聯絡人姓名。", "contact_person");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    back_err("Email 格式不正確。", "email");
}

if (($role === 1 || $role === 2) && !preg_match('/@mail\.nuk\.edu\.tw$/i', $email)) {
    back_err("請使用學校信箱註冊。", "email");
}

if (!preg_match('/^[0-9+\-\s()]{6,10}$/', $tel)) {
    back_err("電話格式不正確。", "tel");
}

$orgPhones = array();
if ($role === 4) {
    $phoneCandidates = array_merge(array($tel), $orgPhonesRaw === "" ? array() : explode(",", $orgPhonesRaw));
    foreach ($phoneCandidates as $phone) {
        $phone = trim($phone);
        if ($phone === "") {
            continue;
        }
        if (!preg_match('/^[0-9+\-\s()]{6,10}$/', $phone)) {
            back_err("單位電話格式不正確。", "org_phones");
        }
        if (!in_array($phone, $orgPhones, true)) {
            $orgPhones[] = $phone;
        }
    }
}

if ($pwd !== $pwd2) {
    back_err("兩次密碼不一致。", "pwd2");
}

if (mb_strlen($pwd, "UTF-8") < 6) {
    back_err("密碼至少 6 碼。", "pwd");
}

$stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ?");
$stmt->execute(array($id));
if ($stmt->fetchColumn()) {
    back_err("此 ID 已存在。", "id");
}

$stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = ?");
$stmt->execute(array($email));
if ($stmt->fetchColumn()) {
    back_err("此 Email 已被使用。", "email");
}

$stmt = $pdo->prepare("SELECT 1 FROM users WHERE name = ?");
$stmt->execute(array($name));
if ($stmt->fetchColumn()) {
    back_err("此姓名或單位名稱已被使用。", "name");
}

$hash = password_hash($pwd, PASSWORD_DEFAULT);
$status = ($role === 4) ? "pending" : "active";

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO users (id, role, name, email, tel, pwd, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute(array($id, $role, $name, $email, $tel, $hash, $status));

    if ($role === 1) {
        $stmt = $pdo->prepare("INSERT INTO students (ID, SID, DNAME) VALUES (?, ?, ?)");
        $stmt->execute(array($id, $id, $dept));
    }

    if ($role === 2) {
        $stmt = $pdo->prepare("INSERT INTO teachers (ID, DNAME) VALUES (?, ?)");
        $stmt->execute(array($id, $dept));
    }

    if ($role === 4) {
        $stmt = $pdo->prepare("INSERT INTO organization (ID, ONAME, CONTACT) VALUES (?, ?, ?)");
        $stmt->execute(array($id, $name, $contactPerson));

        $stmt = $pdo->prepare("INSERT INTO ophone (ID, TEL) VALUES (?, ?)");
        foreach ($orgPhones as $phone) {
            $stmt->execute(array($id, $phone));
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    back_err("註冊失敗，請確認資料是否完整或已被使用。", "id");
}

unset($_SESSION["register_old"], $_SESSION["register_error_field"]);

$msg = ($role === 4)
    ? "註冊成功！請等待管理員審核後再登入。"
    : "註冊成功！請直接登入。";

site_flash_redirect("login.php", $msg, "success");
