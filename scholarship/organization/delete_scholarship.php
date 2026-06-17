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
    header("Location: my_scholarships.php?error=" . urlencode("缺少獎學金編號"));
    exit;
}

try {
    $sql = "DELETE FROM scholarship WHERE id = ? AND provider_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$scholarship_id, $provider_id]);

    if ($stmt->rowCount() > 0) {
        header("Location: my_scholarships.php?msg=" . urlencode("獎助學金已刪除"));
    } else {
        header("Location: my_scholarships.php?error=" . urlencode("刪除失敗：找不到該項目或您無權限刪除"));
    }
    exit;
} catch (PDOException $e) {
    header("Location: my_scholarships.php?error=" . urlencode("無法刪除：已有學生申請此獎學金或系統錯誤"));
    exit;
}
