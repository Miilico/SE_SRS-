<?php
session_start();
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../file_helpers.php";

date_default_timezone_set('Asia/Taipei');
require_once __DIR__ . "/../auth.php";

require_role(3);
ensure_application_files_table($pdo);
//if (!isset($_SESSION['role']) || $_SESSION['role'] != 3) { die("非法訪問"); }

// 處理刪除動作
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM announcement WHERE id = ?");
        $stmt->execute([$id]);
        echo "<script>alert('公告已刪除'); window.location.href='post_management.php';</script>";
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

    // 從 Session 取得管理員 ID 
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
                // 從標題提取 SCNAME，假設格式為「【結果公告】獎學金名稱 獲獎名單」
                // 也可以更簡單地將所有 RESULT='通過' 且 IS_POSTED=0 的全部標記
                $updateApp = $pdo->prepare("UPDATE application SET IS_POSTED = 1 WHERE RESULT = '通過' AND IS_POSTED = 0");
                $updateApp->execute();
            }
        }
        if (!empty($_FILES["ANNOUNCEMENT_FILE"]) && $_FILES["ANNOUNCEMENT_FILE"]["error"] !== UPLOAD_ERR_NO_FILE) {
            store_uploaded_file($pdo, $_FILES["ANNOUNCEMENT_FILE"], 1, $aid, array(
                "announcement_id" => $announcementId
            ));
        }
        echo "<script>alert('$msg'); window.location.href='post_management.php';</script>";
    } catch (Exception $e) {
        die("操作失敗：" . $e->getMessage());
    }
}
?>
