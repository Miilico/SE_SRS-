<?php
session_start();
require_once "db.php";
require_once __DIR__ . "/../mail_helpers.php"; // 引入寄信引擎

if (!isset($_SESSION['user']['id'])) {
    die("請先登入");
}

$provider_id = $_SESSION['user']['id'];
$scholarship_id = $_POST['scholarship_id'] ?? null;
$student_ids_raw = $_POST['student_ids'] ?? '';
$new_status = $_POST['batch_status'] ?? '';

if (!$scholarship_id || !$new_status || trim($student_ids_raw) === '') {
    header("Location: view_applicants.php?scholarship_id=" . urlencode($scholarship_id) . "&error=" . urlencode("請填寫完整的批次處理資料"));
    exit;
}

// 1. 驗證獎助學金是否屬於該單位，並取得名稱供信件使用
$check_sql = "SELECT NAME FROM scholarship WHERE id = ? AND provider_id = ?";
$stmt_check = $pdo->prepare($check_sql);
$stmt_check->execute([$scholarship_id, $provider_id]);
$scholarship = $stmt_check->fetch(PDO::FETCH_ASSOC);

if (!$scholarship) {
    die("無權限或找不到該獎助學金");
}
$scholarshipName = $scholarship['NAME'];

// 2. 整理學號陣列（去除空白與空值）
$student_ids = array_filter(array_map('trim', explode(',', $student_ids_raw)));
if (empty($student_ids)) {
    header("Location: view_applicants.php?scholarship_id=" . urlencode($scholarship_id) . "&error=" . urlencode("未偵測到有效的學號"));
    exit;
}

$success_count = 0;
$mail_count = 0;

// 3. 準備更新與撈取資料的 SQL (依據學號 STID 來更新)
$update_sql = "UPDATE application SET RESULT = ? WHERE SCID = ? AND STID = ?";
$stmt_update = $pdo->prepare($update_sql);

$info_sql = "SELECT EMAIL, NAME AS student_name FROM users WHERE ID = ?";
$stmt_info = $pdo->prepare($info_sql);

// 4. 執行批次更新與寄信
try {
    $pdo->beginTransaction();
    
    foreach ($student_ids as $stid) {
        // 執行更新
        $stmt_update->execute([$new_status, $scholarship_id, $stid]);
        
        // 若有確實更新到資料 (代表該學生有申請此獎學金且狀態改變)
        if ($stmt_update->rowCount() > 0) {
            $success_count++;
            
            // 撈取該生 Email 發送通知
            $stmt_info->execute([$stid]);
            $student = $stmt_info->fetch(PDO::FETCH_ASSOC);
            
            if ($student && !empty($student['EMAIL'])) {
                $toEmail = $student['EMAIL'];
                $toName = $student['student_name'];
                $subject = "【系統通知】獎助學金申請狀態更新：" . $scholarshipName;
                
                $htmlBody = "<p>親愛的 {$toName} 同學您好：</p>";
                $htmlBody .= "<p>您申請的獎助學金 <strong>{$scholarshipName}</strong> 狀態已更新為：<span style='color:#0d6efd; font-weight:bold;'>{$new_status}</span></p>";
                
                if ($new_status === '需補件') {
                    $htmlBody .= "<p>請您盡速登入系統完成補件作業，以免影響您的申請權益。</p>";
                } elseif ($new_status === '通過' || $new_status === '已獲獎') {
                    $htmlBody .= "<p>恭喜您通過審查！後續發放事宜將依單位公告為準。</p>";
                } elseif ($new_status === '不通過' || $new_status === '未獲獎') {
                    $htmlBody .= "<p>感謝您的申請，很遺憾本次未獲通過，祝您學業順利。</p>";
                }
                $htmlBody .= "<p><br>系統自動發送，請勿直接回覆本信件。</p>";

                if (scholarship_send_mail($toEmail, $toName, $subject, $htmlBody)) {
                    $mail_count++;
                }
            }
        }
    }
    
    $pdo->commit();
    header("Location: view_applicants.php?scholarship_id=" . urlencode($scholarship_id) . "&batch_success=1&count=$success_count&mail=$mail_count");
} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: view_applicants.php?scholarship_id=" . urlencode($scholarship_id) . "&error=" . urlencode("資料庫處理發生錯誤"));
}
exit;