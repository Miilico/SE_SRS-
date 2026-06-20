<?php
session_start();
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../file_helpers.php";
require_once __DIR__ . "/../flash_helpers.php";
require_once __DIR__ . "/../mail_helpers.php";

date_default_timezone_set('Asia/Taipei');
require_once __DIR__ . "/../auth.php";

require_role(4);
ensure_application_files_table($pdo);
//if (!isset($_SESSION['role']) || $_SESSION['role'] != 3) { die("非法訪問"); }

// 處理刪除動作
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $id = $_GET['id'];
    $provider_id = $_SESSION['user']['id'];
    try {
        $check = $pdo->prepare("SELECT id FROM announcement WHERE id = ? AND AID = ?");
        $check->execute([$id, $provider_id]);
        if (!$check->fetch()) {
            die("無權限刪除此公告");
        }

        $files = fetch_uploaded_files($pdo, 1, "announcement_id", $id);
        $fileIds = array();
        foreach ($files as $file) {
            $fileIds[] = $file["id"];
        }
        delete_uploaded_files($pdo, $fileIds, 1, "announcement_id", $id);

        $stmt = $pdo->prepare("DELETE FROM announcement WHERE id = ? AND AID = ?");
        $stmt->execute([$id, $provider_id]);
        site_flash_redirect("org_post_management.php", "公告已刪除", "success");
    } catch (PDOException $e) {
        die("刪除失敗：" . $e->getMessage());
    }
    exit;
}

// 處理 POST 提交 (新增/修改)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mode     = $_POST['mode'];
    $id       = $_POST['id'];
    $category = $_POST['category'];
    $title    = $_POST['title']; // 例如：【結果公告】校內優秀獎學金 獲獎名單
    $content  = $_POST['content'];
    $aid      = $_POST['aid'];
    $adate    = date("Y-m-d");
    $atime    = date("H:i:s");

    // 從 Session 取得獎助單位 ID 
    $aid = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;

    // 安全檢查
    if (empty($aid)) {
        die("錯誤：抓不到管理員 ID，請重新登入系統。");
    }

    try {
        if ($mode == 'edit') {
            // 修改模式 (注意您的 SQL 語法在 ATIME 後面漏了一個逗號)
            $sql = "UPDATE announcement SET TITLE = ?, CONTENT = ?, ADATE = ?, ATIME = ?, CATEGORY = ? WHERE ID = ?";
            $pdo->prepare($sql)->execute([$title, $content, $adate, $atime, $category, $id]);
            $announcementId = (int)$id;
            $msg = "公告修改成功！";
        } else {
            // 新增模式
            $sql = "INSERT INTO announcement (ADATE, ATIME, CONTENT, AID, TITLE, CATEGORY) VALUES (?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$adate, $atime, $content, $aid, $title, $category]);
            $announcementId = (int)$pdo->lastInsertId();
            $msg = "公告發佈成功！";

            // --- 標記已公告狀態邏輯 ---
            if ($category == 1) {
                
                //1.直接寄送
                // 從標題提取 SCNAME，假設格式為「【結果公告】獎學金名稱 獲獎名單」
                // 也可以更簡單地將所有 RESULT='通過' 且 IS_POSTED=0 的全部標記
                $updateApp = $pdo->prepare("UPDATE application SET IS_POSTED = 1 WHERE RESULT = '通過' AND IS_POSTED = 0");
                $updateApp->execute();
                
                /*
                //2. 寫入 email_queue 讓背景工作者慢慢寄
                // 1. 先撈出這次要發佈的「通過」且「尚未公告」的學生資料 (包含信箱與姓名)
                $stmtGetWinners = $pdo->prepare("
                    SELECT u.EMAIL, u.NAME, a.SCNAME 
                    FROM application a
                    JOIN users u ON a.STID = u.ID
                    WHERE a.RESULT = '通過' AND a.IS_POSTED = 0 AND a.OID = ?
                ");
                $stmtGetWinners->execute([$aid]); // $aid 是目前單位的 ID
                $winners = $stmtGetWinners->fetchAll(PDO::FETCH_ASSOC);

                // 2. 將這些學生塞入 email_queue (秒殺完成，不用等寄信！)
                if (!empty($winners)) {
                    $insertQueue = $pdo->prepare("
                        INSERT INTO email_queue (recipient_email, recipient_name, subject, body, status) 
                        VALUES (?, ?, ?, ?, 'pending')
                    ");
                    
                    foreach ($winners as $w) {
                        $subject = "【獲獎通知】" . $w['SCNAME'] . " 審查結果";
                        $body = scholarship_mail_html([
                            $w['NAME'] . " 同學您好：",
                            "",
                            "恭喜您！您申請的「" . $w['SCNAME'] . "」已通過審查。",
                            "詳細名單請登入獎助學金系統查看最新公告。",
                            "",
                            "這是一封系統自動發送的信件，請勿直接回覆。"
                        ]);
                        
                        $insertQueue->execute([$w['EMAIL'], $w['NAME'], $subject, $body]);
                    }
                }

                // 3. 最後把狀態標記為已公告 (確保下次不會重複寄)
                $updateApp = $pdo->prepare("UPDATE application SET IS_POSTED = 1 WHERE RESULT = '通過' AND IS_POSTED = 0 AND OID = ?");
                $updateApp->execute([$aid]);
                */
            }
        }
        $announcementFileContext = array("announcement_id" => $announcementId);
        if ($mode == 'edit' && !empty($_POST["delete_announcement_files"])) {
            delete_uploaded_files($pdo, $_POST["delete_announcement_files"], 1, "announcement_id", $announcementId);
        }
        if (!empty($_FILES["ANNOUNCEMENT_FILES"])) {
            store_uploaded_files($pdo, $_FILES["ANNOUNCEMENT_FILES"], 1, $aid, $announcementFileContext);
        }
        if (!empty($_FILES["ANNOUNCEMENT_FILE"])) {
            store_uploaded_files($pdo, $_FILES["ANNOUNCEMENT_FILE"], 1, $aid, $announcementFileContext);
        }
        site_flash_redirect("org_post_management.php", $msg, "success");
    } catch (Exception $e) {
        die("操作失敗：" . $e->getMessage());
    }
}
