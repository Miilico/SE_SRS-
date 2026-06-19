<?php
session_start();
require_once "db.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/scholarship_access.php";

// 1. 權限檢查
organization_require_scholarship_manager();

$isAdmin = organization_is_admin();
$scholarship_id = $_POST['scholarship_id'] ?? null;
$target_status = $_POST['target_status'] ?? null; // 預期收到 1 (開啟) 或 0 (關閉)

if (!$scholarship_id || $target_status === null) {
    header("Location: my_scholarships.php?error=" . urlencode("缺少必要參數"));
    exit;
}

try {
    $managedScholarship = organization_fetch_managed_scholarship($pdo, $scholarship_id);
    if (!$managedScholarship) {
        header("Location: my_scholarships.php?error=" . urlencode("找不到該獎助學金或您無權限更新狀態"));
        exit;
    }

    // 2. 更新 is_active 狀態
    if ($isAdmin) {
        $sql = "UPDATE scholarship SET is_active = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([(int)$target_status, $scholarship_id]);
    } else {
        $sql = "UPDATE scholarship SET is_active = ? WHERE id = ? AND provider_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([(int)$target_status, $scholarship_id, organization_current_user_id()]);
    }

    header("Location: my_scholarships.php?success=" . urlencode("獎助學金狀態已更新"));
    exit;
} catch (PDOException $e) {
    header("Location: my_scholarships.php?error=" . urlencode("狀態更新失敗：" . $e->getMessage()));
    exit;
}
