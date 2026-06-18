<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../file_helpers.php";

require_role(3);
ensure_teachers_table($pdo);

function account_redirect($message, $type = "success") {
    site_flash_redirect("account_management.php", $message, $type);
}

function account_clean_phone_list($raw) {
    $phones = [];
    foreach (explode(",", (string)$raw) as $phone) {
        $phone = trim($phone);
        if ($phone !== "" && !in_array($phone, $phones, true)) {
            $phones[] = $phone;
        }
    }
    return $phones;
}

function account_validate_phone($phone, $label) {
    if ($phone !== "" && mb_strlen($phone) > 10) {
        throw new Exception($label . "長度不可超過 10 字元。");
    }
}

if (isset($_GET["action"]) && $_GET["action"] === "delete") {
    $id = isset($_GET["id"]) ? trim($_GET["id"]) : "";
    if ($id === "") {
        account_redirect("缺少帳號 ID", "danger");
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE ID = ? AND ROLE <> 3");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            account_redirect("刪除完成");
        }
        account_redirect("找不到可刪除的帳號", "warning");
    } catch (PDOException $e) {
        die("刪除失敗：" . $e->getMessage());
    }
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    account_redirect("不支援的操作", "danger");
}

$mode = isset($_POST["mode"]) ? trim($_POST["mode"]) : "";
$id = isset($_POST["id"]) ? trim($_POST["id"]) : "";
$role = isset($_POST["role"]) ? (int)$_POST["role"] : 0;
$name = isset($_POST["name"]) ? trim($_POST["name"]) : "";
$pwd = isset($_POST["pwd"]) ? trim($_POST["pwd"]) : "";
$tel = isset($_POST["tel"]) ? trim($_POST["tel"]) : "";
$email = isset($_POST["email"]) ? trim($_POST["email"]) : "";
$status = isset($_POST["status"]) ? trim($_POST["status"]) : "active";
$sid = isset($_POST["sid"]) ? trim($_POST["sid"]) : "";
$studentDept = isset($_POST["student_dept"]) ? trim($_POST["student_dept"]) : "";
$teacherDept = isset($_POST["teacher_dept"]) ? trim($_POST["teacher_dept"]) : "";
$teacherUnit = isset($_POST["teacher_unit"]) ? trim($_POST["teacher_unit"]) : "";
$teacherTitle = isset($_POST["teacher_title"]) ? trim($_POST["teacher_title"]) : "";
$contactPerson = isset($_POST["contact_person"]) ? trim($_POST["contact_person"]) : "";
$orgPhones = account_clean_phone_list(isset($_POST["org_phones"]) ? $_POST["org_phones"] : "");

try {
    if (!in_array($mode, ["add", "edit"], true)) {
        throw new Exception("操作模式不合法。");
    }
    if ($id === "" || !preg_match('/^[A-Za-z0-9]{1,10}$/', $id)) {
        throw new Exception("帳號 ID 只能是英數，且長度最多 10。");
    }
    if (!in_array($role, [1, 2, 4], true)) {
        throw new Exception("身分不合法。");
    }
    if ($name === "") {
        throw new Exception("請填寫姓名或單位名稱。");
    }
    if ($mode === "add" && $pwd === "") {
        throw new Exception("新增帳號需填寫密碼。");
    }
    if ($pwd !== "" && mb_strlen($pwd) < 6) {
        throw new Exception("密碼至少 6 碼。");
    }
    if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Email 格式不正確。");
    }
    if (!in_array($status, ["active", "pending"], true)) {
        throw new Exception("帳號狀態不合法。");
    }
    account_validate_phone($tel, "電話");
    foreach ($orgPhones as $phone) {
        account_validate_phone($phone, "單位電話");
    }

    if ($role === 1) {
        if ($sid === "") {
            $sid = $id;
        }
        if (mb_strlen($sid) > 8) {
            throw new Exception("學號長度不可超過 8 字元。");
        }
        if ($studentDept === "") {
            throw new Exception("請填寫學生就讀系所。");
        }
    }
    if ($role === 2 && $teacherUnit === "") {
        throw new Exception("請填寫推薦人單位名稱。");
    }
    if ($role === 2 && $teacherTitle === "") {
        throw new Exception("請填寫推薦人職稱。");
    }
    if ($role === 4 && $contactPerson === "") {
        throw new Exception("請填寫獎助單位聯絡人。");
    }

    if ($role === 4 && $tel === "" && !empty($orgPhones)) {
        $tel = $orgPhones[0];
    }

    $hash = ($pwd !== "") ? password_hash($pwd, PASSWORD_DEFAULT) : null;

    $pdo->beginTransaction();

    if ($mode === "edit") {
        $stmt = $pdo->prepare("SELECT ROLE FROM users WHERE ID = ? AND ROLE <> 3 FOR UPDATE");
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            throw new Exception("找不到可修改的帳號。");
        }
        if ((int)$existing["ROLE"] !== $role) {
            throw new Exception("修改既有帳號時不可變更身分。");
        }
    }

    if ($mode === "add") {
        $stmt = $pdo->prepare("INSERT INTO users (ID, NAME, ROLE, PWD, TEL, EMAIL, status, created_at)
                               VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$id, $name, $role, $hash, $tel, $email, $status]);
    } else {
        if ($hash) {
            $stmt = $pdo->prepare("UPDATE users SET NAME = ?, PWD = ?, TEL = ?, EMAIL = ?, status = ? WHERE ID = ? AND ROLE <> 3");
            $stmt->execute([$name, $hash, $tel, $email, $status, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET NAME = ?, TEL = ?, EMAIL = ?, status = ? WHERE ID = ? AND ROLE <> 3");
            $stmt->execute([$name, $tel, $email, $status, $id]);
        }
    }

    if ($role === 1) {
        $stmt = $pdo->prepare("SELECT 1 FROM students WHERE ID = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn()) {
            $stmt = $pdo->prepare("UPDATE students SET SID = ?, DNAME = ? WHERE ID = ?");
            $stmt->execute([$sid, $studentDept, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO students (ID, SID, DNAME) VALUES (?, ?, ?)");
            $stmt->execute([$id, $sid, $studentDept]);
        }
    } elseif ($role === 2) {
        $stmt = $pdo->prepare("SELECT 1 FROM teachers WHERE ID = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn()) {
            $stmt = $pdo->prepare("UPDATE teachers SET DNAME = ?, UNIT_NAME = ?, JOB_TITLE = ? WHERE ID = ?");
            $stmt->execute([$teacherDept, $teacherUnit, $teacherTitle, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO teachers (ID, DNAME, UNIT_NAME, JOB_TITLE) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $teacherDept, $teacherUnit, $teacherTitle]);
        }
    } elseif ($role === 4) {
        $stmt = $pdo->prepare("SELECT 1 FROM organization WHERE ID = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn()) {
            $stmt = $pdo->prepare("UPDATE organization SET ONAME = ?, CONTACT = ? WHERE ID = ?");
            $stmt->execute([$name, $contactPerson, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO organization (ID, ONAME, CONTACT) VALUES (?, ?, ?)");
            $stmt->execute([$id, $name, $contactPerson]);
        }

        $pdo->prepare("DELETE FROM ophone WHERE ID = ?")->execute([$id]);
        if (!empty($orgPhones)) {
            $stmtP = $pdo->prepare("INSERT INTO ophone (ID, TEL) VALUES (?, ?)");
            foreach ($orgPhones as $phone) {
                $stmtP->execute([$id, $phone]);
            }
        }
    }

    $pdo->commit();
    account_redirect($mode === "add" ? "新增帳號成功" : "修改帳號成功");
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("操作失敗：" . $e->getMessage());
}
