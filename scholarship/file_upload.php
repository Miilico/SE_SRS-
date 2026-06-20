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
        if ($role !== 3 && $role !== 4) {
            upload_fail("只有管理員或獎助單位可以上傳公告附件。", 403);
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
                upload_fail("只有對應推薦人或管理員可以上傳推薦信附件。", 403);
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
    $pageTitle = "上傳文件";
    $siteHeaderRequireLogin = true;
    require __DIR__ . "/header.php";
    ?>
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4 p-md-5">
      <h1 class="h3 fw-bold mb-1">上傳文件</h1>
      <div class="text-secondary mb-4">選擇文件類型並填入對應編號後上傳；標示 <span class="text-danger">*</span> 的條件需依文件類型填寫。</div>

  <form method="post" enctype="multipart/form-data" class="vstack gap-3">
    <div>
    <label class="form-label fw-semibold">文件類型 <span class="text-danger" aria-label="必填">*</span></label>
    <select class="form-select" name="file_type" required>
      <option value="1">公告附帶文件</option>
      <option value="2">學生申請獎學金附件</option>
      <option value="3">工單附件</option>
      <option value="4">推薦人推薦信附件</option>
    </select>
    </div>
    <div>
    <label class="form-label fw-semibold">公告 ID（類型 1）</label>
    <input class="form-control" name="announcement_id">
    </div>
    <div>
    <label class="form-label fw-semibold">申請編號 APNO（類型 2 / 4） <span class="text-danger" aria-label="條件式必填">*</span></label>
    <input class="form-control" name="application_id">
    </div>
    <div>
    <label class="form-label fw-semibold">工單 ID（類型 3） <span class="text-danger" aria-label="條件式必填">*</span></label>
    <input class="form-control" name="ticket_id">
    </div>
    <div>
    <label class="form-label fw-semibold">文件 <span class="text-danger" aria-label="必填">*</span></label>
    <input class="form-control" type="file" name="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx,.txt,.zip,.odt,.odc,.webp,.ppt,.pptx,.ods,.gif" required>
    <div class="form-text">允許格式：PDF、DOC、DOCX、JPG、JPEG、PNG、XLS、XLSX、TXT、ZIP、ODT、ODC、WEBP、PPT、PPTX、ODS、GIF；單檔上限 20MB。</div>
    </div>
    <div class="d-flex justify-content-end">
      <button class="btn btn-primary" type="submit">上傳</button>
    </div>
  </form>
    </div>
  </div>
</main>
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
