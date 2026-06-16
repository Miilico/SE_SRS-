<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/file_helpers.php";

function upload_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function upload_fail($message, $status) {
    http_response_code($status);
    exit($message);
}

function current_upload_user() {
    return isset($_SESSION["user"]) ? $_SESSION["user"] : array();
}

function derive_application_context($pdo, $applicationId) {
    $stmt = $pdo->prepare("
        SELECT a.APNO, a.STID, a.OID, a.SCID, s.provider_id
        FROM application a
        JOIN scholarship s ON a.SCID = s.id
        WHERE a.APNO = ?
        LIMIT 1
    ");
    $stmt->execute(array($applicationId));
    return $stmt->fetch();
}

function ensure_can_upload($pdo, $fileType, $user, $context) {
    if (empty($user) || empty($user["id"])) {
        upload_fail("請先登入後再上傳檔案。", 403);
    }

    $userId = (string)$user["id"];
    $role = isset($user["role"]) ? (int)$user["role"] : 0;

    if ($fileType === 1) {
        if ($role !== 3) {
            upload_fail("只有管理員可以上傳公告附件。", 403);
        }
        return $context;
    }

    if ($fileType === 2) {
        if (empty($context["application_id"])) {
            upload_fail("缺少申請編號。", 400);
        }
        $app = derive_application_context($pdo, $context["application_id"]);
        if (!$app) {
            upload_fail("找不到申請資料。", 404);
        }
        if ($role !== 3 && $app["STID"] !== $userId) {
            upload_fail("只有申請學生或管理員可以上傳申請附件。", 403);
        }
        $context["scholarship_id"] = $app["SCID"];
        $context["scholarship_provider_id"] = $app["provider_id"];
        return $context;
    }

    if ($fileType === 3) {
        if (empty($context["ticket_id"])) {
            upload_fail("缺少工單編號。", 400);
        }
        $stmt = $pdo->prepare("SELECT USER_ID FROM tickets WHERE TICKET_ID = ? LIMIT 1");
        $stmt->execute(array($context["ticket_id"]));
        $ticketOwner = $stmt->fetchColumn();
        if (!$ticketOwner) {
            upload_fail("找不到工單。", 404);
        }
        if ($role !== 3 && $ticketOwner !== $userId) {
            upload_fail("只有工單提交者或管理員可以上傳工單附件。", 403);
        }
        return $context;
    }

    if ($fileType === 4) {
        if (empty($context["application_id"])) {
            upload_fail("缺少申請編號。", 400);
        }
        $app = derive_application_context($pdo, $context["application_id"]);
        if (!$app) {
            upload_fail("找不到申請資料。", 404);
        }
        if ($role !== 3) {
            $stmt = $pdo->prepare("
                SELECT 1
                FROM recommendations
                WHERE application_id = ? AND teacher_id = ?
                LIMIT 1
            ");
            $stmt->execute(array($context["application_id"], $userId));
            if (!$stmt->fetchColumn()) {
                upload_fail("只有對應導師或管理員可以上傳推薦信附件。", 403);
            }
        }
        $context["scholarship_id"] = $app["SCID"];
        $context["scholarship_provider_id"] = $app["provider_id"];
        return $context;
    }

    upload_fail("不支援的文件類型。", 400);
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    require_login();
    ?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>上傳文件</title>
  <style>
    body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;margin:32px;background:#f6f7fb;color:#111827}
    form{max-width:640px;background:#fff;border-radius:8px;padding:22px;box-shadow:0 1px 8px rgba(0,0,0,.06)}
    label{display:block;font-weight:700;margin:14px 0 6px}
    input,select{width:100%;box-sizing:border-box;padding:9px;border:1px solid #d0d5dd;border-radius:6px}
    button{margin-top:18px;padding:9px 16px;border:0;border-radius:6px;background:#2563eb;color:#fff;font-weight:700}
  </style>
</head>
<body>
  <h1>上傳文件</h1>
  <form method="post" enctype="multipart/form-data">
    <label>文件類型</label>
    <select name="file_type" required>
      <option value="1">公告附帶文件</option>
      <option value="2">學生申請獎學金附件</option>
      <option value="3">工單附件</option>
      <option value="4">導師推薦信附件</option>
    </select>
    <label>公告 ID（類型 1）</label>
    <input name="announcement_id">
    <label>申請編號 APNO（類型 2 / 4）</label>
    <input name="application_id">
    <label>工單 ID（類型 3）</label>
    <input name="ticket_id">
    <label>文件</label>
    <input type="file" name="file" required>
    <button type="submit">上傳</button>
  </form>
</body>
</html>
    <?php
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    upload_fail("不支援的請求方式。", 405);
}

$user = current_upload_user();
$fileType = isset($_POST["file_type"]) ? (int)$_POST["file_type"] : 0;
$context = array(
    "announcement_id" => isset($_POST["announcement_id"]) ? $_POST["announcement_id"] : null,
    "application_id" => isset($_POST["application_id"]) ? $_POST["application_id"] : null,
    "ticket_id" => isset($_POST["ticket_id"]) ? $_POST["ticket_id"] : null,
);

$context = ensure_can_upload($pdo, $fileType, $user, normalize_uploaded_context($context));

if (empty($_FILES["file"])) {
    upload_fail("請選擇要上傳的文件。", 400);
}

try {
    $saved = store_uploaded_file($pdo, $_FILES["file"], $fileType, $user["id"], $context);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(array(
        "ok" => true,
        "file_id" => $saved["id"],
        "download_url" => $saved["view_url"],
        "original_name" => $saved["original_name"],
    ));
} catch (Exception $e) {
    upload_fail("上傳失敗：" . $e->getMessage(), 500);
}
