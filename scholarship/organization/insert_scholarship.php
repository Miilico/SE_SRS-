<?php

/*echo "<pre>";
var_dump($_POST);
exit;*/

session_start();
require_once "db.php";

// 權限檢查
/*if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'provider') {
    die("無權限");
}*/

// 接收表單
$provider_id = $_SESSION['user']['id'];
$name       = trim($_POST['scholarship_name'] ?? '');
$start_date = trim($_POST['start_date'] ?? '');
$deadline   = trim($_POST['deadline'] ?? '');
$condi      = trim($_POST['conditions'] ?? '');
$amount     = trim($_POST['amount'] ?? '');

// 檢查必填欄位 
if ($name === '' || $amount === '' || $start_date === '' || $deadline === '') { 
    site_flash_redirect("add_scholarship.php", "請完整填寫必填欄位", "danger");
}

// 檢查金額必須為正數 
if (!is_numeric($amount) || (float)$amount <= 0) {
    site_flash_redirect("add_scholarship.php", "金額必須為正數", "danger");
}

function parse_form_date($value)
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    if (
        !$date ||
        ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
    ) {
        return null;
    }

    return $date;
}

$startDateObj = parse_form_date($start_date);
$deadlineObj = parse_form_date($deadline);

if (
    !$startDateObj ||
    !$deadlineObj
) {
    site_flash_redirect("add_scholarship.php", "日期格式錯誤，請使用有效日期", "danger");
}

$start_date = $startDateObj->format('Y-m-d');
$deadline = $deadlineObj->format('Y-m-d');

// 檢查日期邏輯 
if ($startDateObj > $deadlineObj) {
    site_flash_redirect("add_scholarship.php", "開始日期不能晚於截止日期", "danger");
}

try {
    $sql = "INSERT INTO scholarship (NAME, provider_id, DEADLINE, CONDI, AMOUNT, start_date) 
            VALUES (?, ?, ?, ?, ?, ?)"; 
            $stmt = $pdo->prepare($sql); 
            $stmt->execute([$name, $provider_id, $deadline, $condi, $amount, $start_date]); 
            site_flash_redirect("add_scholarship.php", "新增成功！", "success");
} catch (PDOException $e) { 
    site_flash_redirect("add_scholarship.php", "新增失敗：" . $e->getMessage(), "danger");
}
