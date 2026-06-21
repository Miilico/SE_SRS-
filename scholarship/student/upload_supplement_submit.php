<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../file_helpers.php";
require_role(1);

function supplement_redirect($apno, $message)
{
    header(
        "Location: /scholarship/student/upload_supplement.php?apno=" .
        urlencode((string)$apno) . "&err=" . urlencode($message)
    );
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /scholarship/student/my_applications.php");
    exit;
}

$user = isset($_SESSION["user"]) ? $_SESSION["user"] : array();
$studentId = !empty($user["stid"]) ? $user["stid"] : (!empty($user["id"]) ? $user["id"] : "");
$studentIds = array_values(array_unique(array_filter(array(
    !empty($user["stid"]) ? (string)$user["stid"] : "",
    !empty($user["id"]) ? (string)$user["id"] : "",
))));
$apno = isset($_POST["apno"]) ? (int)$_POST["apno"] : 0;
$csrfToken = isset($_POST["csrf_token"]) ? (string)$_POST["csrf_token"] : "";

if (
    empty($_SESSION["supplement_csrf_token"]) ||
    !hash_equals($_SESSION["supplement_csrf_token"], $csrfToken)
) {
    supplement_redirect($apno, "表單驗證失敗，請重新操作。");
}

$uploads = isset($_FILES["SUPPLEMENT_FILES"])
    ? normalize_uploaded_file_entries($_FILES["SUPPLEMENT_FILES"])
    : array();
$uploads = array_values(array_filter($uploads, function ($file) {
    return isset($file["error"]) && (int)$file["error"] !== UPLOAD_ERR_NO_FILE;
}));

if (empty($uploads)) {
    supplement_redirect($apno, "請至少選擇一個補件檔案。");
}

$savedPaths = array();

try {
    $pdo->beginTransaction();

    $studentPlaceholders = implode(",", array_fill(0, count($studentIds), "?"));
    $stmt = $pdo->prepare("
        SELECT APNO, RESULT, SCID, OID
        FROM application
        WHERE APNO = ? AND STID IN (" . $studentPlaceholders . ")
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute(array_merge(array($apno), $studentIds));
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        throw new RuntimeException("找不到申請資料，或你沒有補件權限。");
    }
    if ($application["RESULT"] !== "需補件") {
        throw new RuntimeException("此申請目前不在可補件狀態。");
    }

    foreach ($uploads as $file) {
        $saved = store_uploaded_file($pdo, $file, 2, $studentId, array(
            "application_id" => $apno,
            "scholarship_id" => $application["SCID"],
            "scholarship_provider_id" => $application["OID"],
            "file_subtype" => "supplement",
            "allowed_ext" => array("pdf", "doc", "docx", "jpg", "jpeg", "png"),
            "max_size" => 10 * 1024 * 1024,
        ));
        $savedPaths[] = $saved["path"];
    }

    $updateStmt = $pdo->prepare("
        UPDATE application
        SET RESULT = '已補件'
        WHERE APNO = ?
    ");
    $updateStmt->execute(array($apno));

    $pdo->commit();
    commit_uploaded_request_files();
    unset($_SESSION["supplement_csrf_token"]);

    header(
        "Location: /scholarship/student/application_detail.php?apno=" .
        urlencode((string)$apno) . "&msg=" . urlencode("補件上傳成功，申請狀態已更新為已補件。")
    );
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    rollback_uploaded_request_files();

    foreach ($savedPaths as $relativePath) {
        $fullPath = realpath(__DIR__ . "/../" . $relativePath);
        $uploadDir = realpath(__DIR__ . "/../user_file");
        if ($fullPath && $uploadDir && strpos(strtolower($fullPath), strtolower($uploadDir . DIRECTORY_SEPARATOR)) === 0) {
            @unlink($fullPath);
        }
    }

    supplement_redirect($apno, $e->getMessage());
}
