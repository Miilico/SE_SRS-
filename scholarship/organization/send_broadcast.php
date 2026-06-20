<?php
session_start();
require_once "db.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/scholarship_access.php";
require_once __DIR__ . "/../mail_helpers.php";

organization_require_scholarship_manager();

$scholarship_id = $_POST['scholarship_id'] ?? null;
$target_type = $_POST['target_type'] ?? 'all';
$departments = $_POST['departments'] ?? [];

if (!$scholarship_id) {
    die("缺少參數");
}

// 取得獎學金資料供信件內文使用
$scholarship = organization_fetch_managed_scholarship($pdo, $scholarship_id);

if (!$scholarship) {
    die("找不到該獎助學金或無權限");
}

// 準備撈取目標學生的 SQL
$params = [];
$sql_students = "SELECT u.EMAIL, u.NAME 
                 FROM users u 
                 JOIN students s ON u.ID = s.ID 
                 WHERE u.ROLE = 1 AND u.status = 'active'";

// 若選擇特定系所，加上條件過濾
if ($target_type === 'dept' && !empty($departments)) {
    $placeholders = implode(',', array_fill(0, count($departments), '?'));
    $sql_students .= " AND s.DNAME IN ($placeholders)";
    $params = $departments;
}

$stmt_students = $pdo->prepare($sql_students);
$stmt_students->execute($params);
$students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

$success_count = 0;

// 組合公版信件內容
$sc_name = htmlspecialchars($scholarship['NAME']);
$sc_amount = htmlspecialchars($scholarship['AMOUNT']);
$sc_deadline = htmlspecialchars($scholarship['DEADLINE']);
$subject = "【新獎學金開放申請】" . $sc_name;

foreach ($students as $student) {
    if (!empty($student['EMAIL'])) {
        $toName = htmlspecialchars($student['NAME']);
        
        $htmlBody = "<p>親愛的 {$toName} 同學您好：</p>";
        $htmlBody .= "<p>系統已發佈新的獎助學金：<strong style='color:#0d6efd;'>{$sc_name}</strong>，歡迎符合資格的同學踴躍申請！</p>";
        $htmlBody .= "<ul style='background-color:#f8f9fa; padding: 15px 30px; border-radius: 5px;'>";
        $htmlBody .= "<li><strong>獎助金額：</strong> {$sc_amount} 元</li>";
        $htmlBody .= "<li><strong>申請截止日期：</strong> {$sc_deadline}</li>";
        $htmlBody .= "</ul>";
        $htmlBody .= "<p>詳細申請條件與辦法，請登入「高大獎助學金系統」查看並線上提交申請表。</p>";
        $htmlBody .= "<p><br>系統自動發送，請勿直接回覆本信件。</p>";

        // 呼叫寄信引擎
        if (scholarship_send_mail($student['EMAIL'], $student['NAME'], $subject, $htmlBody)) {
            $success_count++;
        }
    }

    /*
    // 🔽 原本的即時寄信改為寫入 email_queue 的方式
        // 準備隊列 INSERT 語法
    $insertQueue = $pdo->prepare("
        INSERT INTO email_queue (recipient_email, recipient_name, subject, body, status) 
        VALUES (?, ?, ?, ?, 'pending')
    ");

    $success_count = 0;

    foreach ($students as $student) {
        if (!empty($student['EMAIL'])) {
            $toName = htmlspecialchars($student['NAME']);
            
            $htmlBody = "<p>親愛的 {$toName} 同學您好：</p>";
            $htmlBody .= "<p>系統已發佈新的獎助學金：<strong style='color:#0d6efd;'>{$sc_name}</strong>，歡迎符合資格的同學踴躍申請！</p>";
            $htmlBody .= "<ul style='background-color:#f8f9fa; padding: 15px 30px; border-radius: 5px;'>";
            $htmlBody .= "<li><strong>獎助金額：</strong> {$sc_amount} 元</li>";
            $htmlBody .= "<li><strong>申請截止日期：</strong> {$sc_deadline}</li>";
            $htmlBody .= "</ul>";
            $htmlBody .= "<p>詳細申請條件與辦法，請登入「高大獎助學金系統」查看並線上提交申請表。</p>";
            $htmlBody .= "<p><br>系統自動發送，請勿直接回覆本信件。</p>";

            // 🔽 將原本的 scholarship_send_mail 改為塞入資料庫隊列
            $insertQueue->execute([$student['EMAIL'], $toName, $subject, $htmlBody]);
            $success_count++;
        }
    }

    // 完成後導回清單並顯示「已加入排程」的數量 (瞬間跳轉，不會卡頓)
    header("Location: my_scholarships.php?broadcast_success=1&count=" . $success_count);
    exit;
    
    */ 
}

// 完成後導回清單並顯示成功數量
header("Location: my_scholarships.php?broadcast_success=1&count=" . $success_count);
exit;
