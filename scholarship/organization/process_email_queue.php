<?php
// 這支程式負責在背景把 email_queue 裡的信寄出去
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/mail_helpers.php"; // 引入你們強大的寄信模組

try {
    // 1. 鎖定並撈取 50 筆待寄送的信件 (FOR UPDATE 可以防止多個排程同時搶同一封信)
    $pdo->beginTransaction();
    
    $stmt = $pdo->query("SELECT * FROM email_queue WHERE status = 'pending' LIMIT 50 FOR UPDATE");
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($emails)) {
        $pdo->commit();
        exit("沒有需要寄送的信件。\n");
    }

    // 將狀態改為 processing，避免下一分鐘的排程重複抓取
    $ids = array_column($emails, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $updateStmt = $pdo->prepare("UPDATE email_queue SET status = 'processing' WHERE id IN ($placeholders)");
    $updateStmt->execute($ids);
    
    $pdo->commit();

    // 2. 開始逐一寄信
    $successStmt = $pdo->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
    $failStmt = $pdo->prepare("UPDATE email_queue SET status = 'failed', error_msg = ? WHERE id = ?");

    $successCount = 0;
    foreach ($emails as $email) {
        // 呼叫你們寫好的 scholarship_send_mail
        $isSent = scholarship_send_mail(
            $email['recipient_email'], 
            $email['recipient_name'], 
            $email['subject'], 
            $email['body']
        );

        if ($isSent) {
            $successStmt->execute([$email['id']]);
            $successCount++;
        } else {
            // 如果寄送失敗，記錄下來
            $failStmt->execute(["PHPMailer 寄送失敗", $email['id']]);
        }
    }
    
    echo "成功處理 " . count($emails) . " 封信件，實際寄出 " . $successCount . " 封。\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("處理佇列發生錯誤：" . $e->getMessage());
}
?>