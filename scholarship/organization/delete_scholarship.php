<?php
session_start();
require_once "db.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/scholarship_access.php";

// 1. 基本登入檢查
organization_require_scholarship_manager();

$scholarship_id = isset($_GET['scholarship_id']) ? $_GET['scholarship_id'] : null;

if (!$scholarship_id) {
    header("Location: my_scholarships.php?error=" . urlencode("缺少獎學金編號"));
    exit;
}

try {
    $scholarship = organization_fetch_managed_scholarship($pdo, $scholarship_id);
    if (!$scholarship) {
        header("Location: my_scholarships.php?error=" . urlencode("刪除失敗：找不到該項目或您無權限刪除"));
        exit;
    }

    $sql = "DELETE FROM scholarship WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$scholarship_id]);

    if ($stmt->rowCount() > 0) {
        header("Location: my_scholarships.php?success=delete");
    } else {
        header("Location: my_scholarships.php?error=" . urlencode("刪除失敗：找不到該項目或您無權限刪除"));
    }
    exit;

} catch (PDOException $e) {
    header("Location: my_scholarships.php?error=" . urlencode("無法刪除：已有學生申請此獎學金或系統錯誤"));
    exit;
}
?>
