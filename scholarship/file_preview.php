<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/file_helpers.php";

function deny_file_preview($message, $status)
{
    http_response_code($status);
    exit($message);
}

function preview_recommendation_token_can_download_file($pdo, $file, $token)
{
    if ($token === "" || empty($file["application_id"])) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT 1
        FROM recommendations
        WHERE token = ? AND application_id = ?
        LIMIT 1
    ");
    $stmt->execute(array($token, $file["application_id"]));

    return (bool)$stmt->fetchColumn();
}

$fileId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($fileId <= 0) {
    deny_file_preview("無效的檔案編號。", 400);
}

ensure_application_files_table($pdo);

$stmt = $pdo->prepare("SELECT * FROM application_files WHERE id = ? LIMIT 1");
$stmt->execute(array($fileId));
$file = $stmt->fetch();

if (!$file) {
    deny_file_preview("找不到檔案。", 404);
}

$user = isset($_SESSION["user"]) ? $_SESSION["user"] : array();
$recToken = isset($_GET["rec_token"]) ? trim($_GET["rec_token"]) : "";
if (!user_can_download_file($pdo, $file, $user) && !preview_recommendation_token_can_download_file($pdo, $file, $recToken)) {
    deny_file_preview("沒有權限預覽此檔案。", 403);
}

$fullPath = uploaded_file_full_path($file);
if (!$fullPath) {
    deny_file_preview("檔案不存在。", 404);
}

$mimeType = !empty($file["mime_type"]) ? $file["mime_type"] : "application/octet-stream";
$extension = uploaded_file_extension($file);
$mimeByExtension = array(
    "pdf" => "application/pdf",
    "jpg" => "image/jpeg",
    "jpeg" => "image/jpeg",
    "png" => "image/png",
    "gif" => "image/gif",
    "webp" => "image/webp",
    "txt" => "text/plain; charset=utf-8",
    "csv" => "text/csv; charset=utf-8",
    "doc" => "application/msword",
    "docx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
);

if ($mimeType === "application/octet-stream" && isset($mimeByExtension[$extension])) {
    $mimeType = $mimeByExtension[$extension];
}

$previewable = in_array(uploaded_file_preview_kind($file), array("image", "pdf", "docx", "doc", "text"));
if (!$previewable) {
    deny_file_preview("此檔案格式不支援預覽。", 415);
}

$downloadName = basename($file["original_name"]);

while (ob_get_level() > 0) {
    ob_end_clean();
}

header("Content-Type: " . $mimeType);
header("Content-Length: " . filesize($fullPath));
header("Content-Disposition: inline; filename=\"" . str_replace("\"", "", $downloadName) . "\"");
header("X-Content-Type-Options: nosniff");
readfile($fullPath);
exit;
