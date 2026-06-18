<?php
session_start();
require_once "db.php";

// 1. 基本登入檢查
if (!isset($_SESSION['user']['id'])) {
    die("請先登入");
}

$provider_id = $_SESSION['user']['id'];


$scholarship_id = isset($_GET['scholarship_id']) ? $_GET['scholarship_id'] : null;

if (!$scholarship_id) {
    site_flash_redirect("my_scholarships.php", "缺少獎學金編號", "danger");
}

try {
    $sql = "DELETE FROM scholarship WHERE id = ? AND provider_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$scholarship_id, $provider_id]);

    if ($stmt->rowCount() > 0) {
        site_flash_redirect("my_scholarships.php", "獎助學金已刪除", "success");
    } else {
        site_flash_redirect("my_scholarships.php", "刪除失敗：找不到該項目或您無權限刪除", "danger");
    }
} catch (PDOException $e) {
    site_flash_redirect("my_scholarships.php", "無法刪除：已有學生申請此獎學金或系統錯誤", "danger");
}
