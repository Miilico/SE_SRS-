<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/file_helpers.php";

function deny_file_download($message, $status) {
    http_response_code($status);
    exit($message);
}

$fileId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($fileId <= 0) {
    deny_file_download("未指定檔案。", 400);
}

ensure_application_files_table($pdo);

$stmt = $pdo->prepare("SELECT * FROM application_files WHERE id = ? LIMIT 1");
$stmt->execute(array($fileId));
$file = $stmt->fetch();

if (!$file) {
    deny_file_download("找不到檔案。", 404);
}

$user = isset($_SESSION["user"]) ? $_SESSION["user"] : array();
if (!user_can_download_file($pdo, $file, $user)) {
    deny_file_download("您沒有權限下載此檔案。", 403);
}

$baseDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . "user_file");
$filePath = !empty($file["file_path"]) ? $file["file_path"] : $file["path"];
$fullPath = realpath(__DIR__ . DIRECTORY_SEPARATOR . $filePath);

if (!$baseDir || !$fullPath) {
    deny_file_download("檔案不存在。", 404);
}

$baseDirCheck = strtolower($baseDir . DIRECTORY_SEPARATOR);
$fullPathCheck = strtolower($fullPath);
if (strpos($fullPathCheck, $baseDirCheck) !== 0 || !is_file($fullPath)) {
    deny_file_download("檔案路徑不合法。", 403);
}

$mimeType = !empty($file["mime_type"]) ? $file["mime_type"] : "application/octet-stream";
$downloadName = basename($file["original_name"]);

while (ob_get_level() > 0) {
    ob_end_clean();
}

header("Content-Type: " . $mimeType);
header("Content-Length: " . filesize($fullPath));
header("Content-Disposition: attachment; filename=\"" . str_replace("\"", "", $downloadName) . "\"");
header("X-Content-Type-Options: nosniff");
readfile($fullPath);
exit;
