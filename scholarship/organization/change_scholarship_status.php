<?php
session_start();
require_once "db.php";

// 1. 權限檢查
if (!isset($_SESSION['user']['id'])) {
    die("請先登入");
}

$provider_id = $_SESSION['user']['id'];
$scholarship_id = $_POST['scholarship_id'] ?? null;
$target_status = $_POST['target_status'] ?? null; // 預期收到 1 (開啟) 或 0 (關閉)

if (!$scholarship_id || $target_status === null) {
    header("Location: my_scholarships.php?error=" . urlencode("缺少必要參數"));
    exit;
}

try {
    // 2. 更新 is_active 狀態
    $sql = "UPDATE scholarship SET is_active = ? WHERE id = ? AND provider_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int)$target_status, $scholarship_id, $provider_id]);

    header("Location: my_scholarships.php?success=" . urlencode("獎助學金狀態已更新"));
    exit;
} catch (PDOException $e) {
    header("Location: my_scholarships.php?error=" . urlencode("狀態更新失敗：" . $e->getMessage()));
    exit;
}