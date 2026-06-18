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
    header("Location: add_scholarship.php?error=" . urlencode("請完整填寫必填欄位")); 
    exit; 
}

// 檢查金額必須為正數 
if (!is_numeric($amount) || (float)$amount <= 0) {
    header("Location: add_scholarship.php?error=" . urlencode("金額必須為正數")); 
    exit; 
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
    header("Location: add_scholarship.php?error=" . urlencode("日期格式錯誤，請使用有效日期"));
    exit;
}

$start_date = $startDateObj->format('Y-m-d');
$deadline = $deadlineObj->format('Y-m-d');

// 檢查日期邏輯 
if ($startDateObj > $deadlineObj) {
    header("Location: add_scholarship.php?error=" . urlencode("開始日期不能晚於截止日期")); 
    exit; 
}

try {
    $sql = "INSERT INTO scholarship (NAME, provider_id, DEADLINE, CONDI, AMOUNT, start_date) 
            VALUES (?, ?, ?, ?, ?, ?)"; 
            $stmt = $pdo->prepare($sql); 
            $stmt->execute([$name, $provider_id, $deadline, $condi, $amount, $start_date]); 
            $scholarship_id = $pdo->lastInsertId();

            // 處理自訂表單欄位
            if (isset($_POST['custom_labels']) && is_array($_POST['custom_labels'])) {
            
                $labels    = $_POST['custom_labels'];
                $types     = $_POST['custom_types'];
                $requireds = $_POST['custom_required'];

                // 預備 SQL 陳述式，準備批次寫入新建立的 scholarship_fields 表
                $field_sql = "INSERT INTO scholarship_fields (scholarship_id, field_label, field_type, is_required) VALUES (?, ?, ?, ?)";
                $field_stmt = $pdo->prepare($field_sql);

                // 透過迴圈，將陣列中的每一筆自訂題目依序寫入
                for ($i = 0; $i < count($labels); $i++) {
                    $label = trim($labels[$i]);
                    $type  = $types[$i];
                    $req   = intval($requireds[$i]);

                    // 確保項目名稱不是空的才寫入資料庫
                    if ($label !== '') {
                        $field_stmt->execute([
                            $scholarship_id, 
                            $label, 
                            $type, 
                            $req
                        ]);
                    }
                }
            }
            // 處理檔案上傳
            if (isset($_FILES['scholarship_attachment']) && $_FILES['scholarship_attachment']['error'] === UPLOAD_ERR_OK) {
                $file_tmp  = $_FILES['scholarship_attachment']['tmp_name'];
                $file_name = $_FILES['scholarship_attachment']['name'];
                $file_size = $_FILES['scholarship_attachment']['size'];
                $file_type = $_FILES['scholarship_attachment']['type'];
                
                // 設定儲存路徑 (確保 /uploads/scholarships/ 資料夾存在且具備寫入權限)
                $upload_dir = __DIR__ . '/../uploads/scholarships/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // 產生唯一的檔案名稱避免覆蓋
                $ext = pathinfo($file_name, PATHINFO_EXTENSION);
                $stored_name = 'sc_' . $scholarship_id . '_' . time() . '_' . uniqid() . '.' . $ext;
                $destination = $upload_dir . $stored_name;
                
                if (move_uploaded_file($file_tmp, $destination)) {
                    // 將檔案紀錄寫入 application_files (file_category 設為 1 代表系統公告/簡章類)
                    $file_sql = "INSERT INTO application_files 
                                (file_type, original_name, path, uploader_id, file_category, stored_name, mime_type, file_size, scholarship_id) 
                                VALUES ('announcement', ?, ?, ?, 1, ?, ?, ?, ?)";
                    $file_stmt = $pdo->prepare($file_sql);
                    $file_stmt->execute([
                        $file_name, 
                        '/scholarship/uploads/scholarships/' . $stored_name, 
                        $provider_id, 
                        $stored_name, 
                        $file_type, 
                        $file_size, 
                        $scholarship_id
                    ]);
                }
            }
            header("Location: add_scholarship.php?success=1"); 
            exit; 
} catch (PDOException $e) { 
    header("Location: add_scholarship.php?error=" . urlencode("新增失敗：" . $e->getMessage())); 
    exit; 
}
