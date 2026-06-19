<?php
session_start();
require_once "db.php";

// 1. 基本登入檢查
if (!isset($_SESSION['user']['id'])) {
    die("請先登入");
}
$provider_id = $_SESSION['user']['id'];

// 2. 接收表單資料
$name = $_POST['scholarship_name'] ?? '';
$amount = $_POST['amount'] ?? 0;
$conditions = $_POST['conditions'] ?? '';
$start_date = $_POST['start_date'] ?? '';
$deadline = $_POST['deadline'] ?? '';

// 接收動態欄位陣列
$custom_labels = $_POST['custom_labels'] ?? [];
$custom_types = $_POST['custom_types'] ?? [];
$custom_required = $_POST['custom_required'] ?? [];

if (!$name || !$start_date || !$deadline) {
    header("Location: add_scholarship.php?error=" . urlencode("請填寫必填欄位"));
    exit;
}

try {
    $pdo->beginTransaction();

    // 3. 寫入 scholarship 表 (注意：稍早新增的 is_active 欄位會自動套用預設值 1)
    $sql = "INSERT INTO scholarship (NAME, provider_id, DEADLINE, CONDI, AMOUNT, start_date) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $provider_id, $deadline, $conditions, $amount, $start_date]);
    
    $scholarship_id = $pdo->lastInsertId();

    // 4. 寫入 scholarship_fields 自訂表單欄位
    if (!empty($custom_labels)) {
        $field_sql = "INSERT INTO scholarship_fields (scholarship_id, field_label, field_type, is_required) VALUES (?, ?, ?, ?)";
        $field_stmt = $pdo->prepare($field_sql);
        
        for ($i = 0; $i < count($custom_labels); $i++) {
            $label = trim($custom_labels[$i]);
            if (empty($label)) continue; // 跳過空標題
            
            $type = $custom_types[$i];
            $is_req = $custom_required[$i];
            
            $field_stmt->execute([$scholarship_id, $label, $type, $is_req]);
        }
    }

    // 🔽 5. 處理官方附件上傳 (FR-SSS22) 
    if (isset($_FILES['scholarship_attachment']) && $_FILES['scholarship_attachment']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['scholarship_attachment']['tmp_name'];
        $file_name = $_FILES['scholarship_attachment']['name'];
        $file_size = $_FILES['scholarship_attachment']['size'];
        $file_type = $_FILES['scholarship_attachment']['type'];
        
        // 設定儲存路徑
        $upload_dir = __DIR__ . '/../uploads/scholarships/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // 產生唯一的檔案名稱
        $ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $stored_name = 'sc_' . $scholarship_id . '_' . time() . '_' . uniqid() . '.' . $ext;
        $destination = $upload_dir . $stored_name;
        
        if (move_uploaded_file($file_tmp, $destination)) {
            // 寫入專屬的 scholarship_attachments 資料表
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

    // 🔽 6. 自動同步至系統管理員公告 (UC-SAS007)
    // 組合要發佈的公告內容
    $announce_title = "【新獎學金開放申請】" . $name;
    $announce_content = "有新的獎助學金「{$name}」開放申請囉！\n\n申請截止日期：{$deadline}\n獎助金額：{$amount} 元\n\n詳情請登入系統查看並進行申請。";
    $admin_id = 'Z0000000'; // 系統管理員預設帳號
    
    $sync_sql = "INSERT INTO announcement (title, ADATE, ATIME, CONTENT, AID, CATEGORY) 
                 VALUES (?, CURRENT_DATE(), CURRENT_TIME(), ?, ?, 0)";
    $sync_stmt = $pdo->prepare($sync_sql);
    $sync_stmt->execute([$announce_title, $announce_content, $admin_id]);

    $pdo->commit();
    header("Location: my_scholarships.php?success=add");
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    header("Location: add_scholarship.php?error=" . urlencode("資料庫錯誤：" . $e->getMessage()));
    exit;
}
?>