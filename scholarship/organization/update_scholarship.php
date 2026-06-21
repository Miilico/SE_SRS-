<?php
session_start();
require_once "db.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../custom_form_helpers.php";
require_once __DIR__ . "/scholarship_access.php";

// 權限檢查
organization_require_scholarship_manager();

$isAdmin = organization_is_admin();
$scholarship_id = $_POST['scholarship_id'] ?? null;
$name       = trim($_POST['scholarship_name'] ?? '');
$start_date = trim($_POST['start_date'] ?? '');
$deadline   = trim($_POST['deadline'] ?? '');
$condi      = trim($_POST['conditions'] ?? '');
$amount     = trim($_POST['amount'] ?? '');
$customLabels = $_POST['custom_labels'] ?? array();
$customTypes = $_POST['custom_types'] ?? array();
$customRequired = $_POST['custom_required'] ?? array();
$customNotes = $_POST['custom_notes'] ?? array();
$customFieldIds = $_POST['custom_field_ids'] ?? array();

$redirect_url = "edit_scholarship.php?id=" . urlencode($scholarship_id);

if (!$scholarship_id || $name === '' || $amount === '' || $start_date === '' || $deadline === '') { 
    header("Location: " . $redirect_url . "&error=" . urlencode("請完整填寫必填欄位")); 
    exit; 
}

if (!is_numeric($amount) || (float)$amount <= 0) {
    header("Location: " . $redirect_url . "&error=" . urlencode("金額必須為正數")); 
    exit; 
}

if (strtotime($start_date) > strtotime($deadline)) {
    header("Location: " . $redirect_url . "&error=" . urlencode("開始日期不能晚於截止日期")); 
    exit; 
}

$managedScholarship = organization_fetch_managed_scholarship($pdo, $scholarship_id);
if (!$managedScholarship) {
    header("Location: my_scholarships.php?error=" . urlencode("找不到該獎助學金或您無權限編輯"));
    exit;
}

try {
    custom_form_validate_unique_labels($customLabels);
    $pdo->beginTransaction();

    // 1. 更新主表
    if ($isAdmin) {
        $sql = "UPDATE scholarship SET NAME = ?, DEADLINE = ?, CONDI = ?, AMOUNT = ?, start_date = ? 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $deadline, $condi, $amount, $start_date, $scholarship_id]);
    } else {
        $sql = "UPDATE scholarship SET NAME = ?, DEADLINE = ?, CONDI = ?, AMOUNT = ?, start_date = ? 
                WHERE id = ? AND provider_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $deadline, $condi, $amount, $start_date, $scholarship_id, organization_current_user_id()]);
    }
            
    custom_form_replace_fields(
        $pdo,
        $scholarship_id,
        $customLabels,
        $customTypes,
        $customRequired,
        $customNotes,
        $customFieldIds
    );
    if (isset($_FILES['scholarship_attachment']) && $_FILES['scholarship_attachment']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['scholarship_attachment']['tmp_name'];
        $file_name = $_FILES['scholarship_attachment']['name'];
        $file_size = $_FILES['scholarship_attachment']['size'];
        $file_type = $_FILES['scholarship_attachment']['type'];
        
        $upload_dir = __DIR__ . '/../uploads/scholarships/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $stored_name = 'sc_' . $scholarship_id . '_' . time() . '_' . uniqid() . '.' . $ext;
        $destination = $upload_dir . $stored_name;
        
        if (move_uploaded_file($file_tmp, $destination)) {
            // 先刪除資料庫中舊的附件紀錄
            $del_file_sql = "DELETE FROM scholarship_attachments WHERE scholarship_id = ?";
            $pdo->prepare($del_file_sql)->execute([$scholarship_id]);
            
            // 寫入新附件紀錄
            $file_sql = "INSERT INTO scholarship_attachments 
                        (scholarship_id, original_name, stored_name, file_path, file_size, mime_type) 
                        VALUES (?, ?, ?, ?, ?, ?)";
            $file_stmt = $pdo->prepare($file_sql);
            $file_stmt->execute([
                $scholarship_id,
                $file_name, 
                $stored_name, 
                '/scholarship/uploads/scholarships/' . $stored_name, 
                $file_size,
                $file_type
            ]);
        }
    }
    $pdo->commit();
    header("Location: " . $redirect_url . "&success=1"); 
    exit; 

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: " . $redirect_url . "&error=" . urlencode("更新失敗：" . $e->getMessage())); 
    exit; 
}
