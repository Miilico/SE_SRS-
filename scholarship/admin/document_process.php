<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../file_helpers.php";

require_role(3);
ensure_application_files_table($pdo);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    site_flash_redirect("document_management.php", "不支援的請求方式。", "danger");
}

$ids = array();

if (isset($_POST["delete_one"])) {
    $ids[] = (int)$_POST["delete_one"];
} elseif (isset($_POST["bulk_delete"]) && !empty($_POST["selected_ids"]) && is_array($_POST["selected_ids"])) {
    foreach ($_POST["selected_ids"] as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
}

if (empty($ids)) {
    site_flash_redirect("document_management.php", "請先選擇要管理的文件。", "warning");
}

$deleted = 0;
foreach ($ids as $id) {
    if ($id > 0 && delete_uploaded_file_record($pdo, $id)) {
        $deleted++;
    }
}

if ($deleted > 0) {
    site_flash_redirect("document_management.php", "已刪除 " . $deleted . " 份文件。", "success");
}

site_flash_redirect("document_management.php", "沒有文件被刪除。", "warning");
