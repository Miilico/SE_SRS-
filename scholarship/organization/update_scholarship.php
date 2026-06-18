<?php
session_start();
require_once "db.php";

// 權限檢查
if (!isset($_SESSION['user']['id'])) {
    die("請先登入");
}

$provider_id = $_SESSION['user']['id'];
$scholarship_id = $_POST['scholarship_id'] ?? null;
$name       = trim($_POST['scholarship_name'] ?? '');
$start_date = trim($_POST['start_date'] ?? '');
$deadline   = trim($_POST['deadline'] ?? '');
$condi      = trim($_POST['conditions'] ?? '');
$amount     = trim($_POST['amount'] ?? '');

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

try {
    $pdo->beginTransaction();

    // 1. 更新主表
    $sql = "UPDATE scholarship SET NAME = ?, DEADLINE = ?, CONDI = ?, AMOUNT = ?, start_date = ? 
            WHERE id = ? AND provider_id = ?"; 
    $stmt = $pdo->prepare($sql); 
    $stmt->execute([$name, $deadline, $condi, $amount, $start_date, $scholarship_id, $provider_id]); 
            
    // 2. 清除舊的自訂欄位
    $del_fields_sql = "DELETE FROM scholarship_fields WHERE scholarship_id = ?";
    $pdo->prepare($del_fields_sql)->execute([$scholarship_id]);

    // 3. 寫入新的自訂欄位
    if (isset($_POST['custom_labels']) && is_array($_POST['custom_labels'])) {
        $labels    = $_POST['custom_labels'];
        $types     = $_POST['custom_types'];
        $requireds = $_POST['custom_required'];

        $field_sql = "INSERT INTO scholarship_fields (scholarship_id, field_label, field_type, is_required) VALUES (?, ?, ?, ?)";
        $field_stmt = $pdo->prepare($field_sql);

        for ($i = 0; $i < count($labels); $i++) {
            $label = trim($labels[$i]);
            $type  = $types[$i];
            $req   = intval($requireds[$i]);

            if ($label !== '') {
                $field_stmt->execute([$scholarship_id, $label, $type, $req]);
            }
        }
    }
    
    $pdo->commit();
    header("Location: " . $redirect_url . "&success=1"); 
    exit; 

} catch (PDOException $e) { 
    $pdo->rollBack();
    header("Location: " . $redirect_url . "&error=" . urlencode("更新失敗：" . $e->getMessage())); 
    exit; 
}