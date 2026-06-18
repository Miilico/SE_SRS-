<?php
$adminHeaderBootstrapOnly = true;
require __DIR__ . "/../header.php";
unset($adminHeaderBootstrapOnly);
require_once __DIR__ . "/../file_helpers.php";

ensure_application_files_table($pdo);

function document_h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function document_format_label($file)
{
    $ext = uploaded_file_extension($file);
    return $ext === "" ? "無副檔名" : strtoupper($ext);
}

function document_size_label($bytes)
{
    $bytes = (int)$bytes;
    if ($bytes <= 0) {
        return "未知";
    }

    $units = array("B", "KB", "MB", "GB");
    $size = (float)$bytes;
    $unitIndex = 0;
    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size = $size / 1024;
        $unitIndex++;
    }

    return ($unitIndex === 0 ? (string)(int)$size : number_format($size, 1)) . " " . $units[$unitIndex];
}

function document_uploader_label($file)
{
    $uploaderId = isset($file["uploader_id"]) ? trim((string)$file["uploader_id"]) : "";
    $uploaderName = isset($file["uploader_name"]) ? trim((string)$file["uploader_name"]) : "";

    if ($uploaderName !== "" && $uploaderId !== "") {
        return $uploaderName . "（" . $uploaderId . "）";
    }

    if ($uploaderName !== "") {
        return $uploaderName;
    }

    if ($uploaderId !== "") {
        return $uploaderId;
    }

    return "未知上傳人";
}

function document_uploader_role_label($role)
{
    switch ((int)$role) {
        case 1:
            return "學生";
        case 2:
            return "推薦人";
        case 3:
            return "管理員";
        case 4:
            return "獎助單位";
        default:
            return "未知";
    }
}

function document_detail_type_label($file)
{
    $category = isset($file["file_category"]) ? (int)$file["file_category"] : 0;
    $fileType = isset($file["file_type"]) ? trim((string)$file["file_type"]) : "";
    $fileTypeKey = strtolower($fileType);

    if ($category === 1) {
        return "公告附件";
    }

    if ($category === 2) {
        $applicationTypes = array(
            "autobi" => "申請自傳",
            "support" => "申請佐證資料",
            "application" => "申請附件",
        );

        return isset($applicationTypes[$fileTypeKey]) ? $applicationTypes[$fileTypeKey] : "申請檔案";
    }

    if ($category === 3) {
        return "工單附件";
    }

    if ($category === 4) {
        return "推薦信附件";
    }

    return $fileType !== "" ? "其他檔案：" . $fileType : "其他檔案";
}

function document_context_label($file)
{
    $parts = array();
    if (!empty($file["announcement_id"])) {
        $parts[] = "公告 #" . $file["announcement_id"];
    }
    if (!empty($file["application_id"])) {
        $parts[] = "申請 #" . $file["application_id"];
    }
    if (!empty($file["scholarship_id"])) {
        $parts[] = "獎學金 #" . $file["scholarship_id"];
    }
    if (!empty($file["ticket_id"])) {
        $parts[] = "工單 #" . $file["ticket_id"];
    }
    if (!empty($file["recommendation_id"])) {
        $parts[] = "推薦 #" . $file["recommendation_id"];
    }

    return empty($parts) ? "未關聯" : implode(" / ", $parts);
}

function document_request_value($name, $default = "")
{
    return isset($_GET[$name]) ? trim((string)$_GET[$name]) : $default;
}

function document_query_url($overrides)
{
    $params = $_GET;
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === "") {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    $query = http_build_query($params);
    return "document_management.php" . ($query === "" ? "" : "?" . $query);
}

function document_selected($current, $value)
{
    return (string)$current === (string)$value ? " selected" : "";
}

function document_input_value($name)
{
    return document_h(document_request_value($name));
}

$files = array();
$formats = array();
$uploaders = array();
$detailTypes = array(
    "announcement" => "公告附件",
    "application_autobi" => "申請自傳",
    "application_support" => "申請佐證資料",
    "application_other" => "申請檔案",
    "ticket" => "工單附件",
    "recommendation" => "推薦信附件",
    "other" => "其他檔案",
);
$allowedPageSizes = array("10" => 10, "50" => 50, "100" => 100, "500" => 500, "all" => "all");
$pageSizeParam = document_request_value("page_size", "50");
if (!array_key_exists($pageSizeParam, $allowedPageSizes)) {
    $pageSizeParam = "50";
}
$pageSize = $allowedPageSizes[$pageSizeParam];
$page = max(1, (int)document_request_value("page", "1"));
$totalFiles = 0;
$totalPages = 1;
$offset = 0;
$startRow = 0;
$endRow = 0;

$filterFormat = document_request_value("format", "all");
$filterUploader = document_request_value("uploader", "all");
$filterDetailType = document_request_value("detail_type", "all");
$filterUploaderRole = document_request_value("uploader_role", "all");
$searchName = document_request_value("search_name");
$searchUploaderId = document_request_value("search_uploader_id");
$searchUploaderName = document_request_value("search_uploader_name");
$searchUploaderEmail = document_request_value("search_uploader_email");
$searchContext = document_request_value("search_context");
$searchSystem = document_request_value("search_system");

try {
    $extensionExpr = "LOWER(CASE
        WHEN LOCATE('.', COALESCE(NULLIF(f.original_name, ''), NULLIF(f.stored_name, ''), NULLIF(f.file_path, ''), f.path)) > 0
        THEN SUBSTRING_INDEX(COALESCE(NULLIF(f.original_name, ''), NULLIF(f.stored_name, ''), NULLIF(f.file_path, ''), f.path), '.', -1)
        ELSE ''
    END)";

    $formatStmt = $pdo->query("
        SELECT DISTINCT $extensionExpr AS ext
        FROM application_files f
        ORDER BY ext ASC
    ");
    foreach ($formatStmt->fetchAll() as $row) {
        $ext = isset($row["ext"]) ? trim((string)$row["ext"]) : "";
        $formats[$ext === "" ? "no_ext" : $ext] = $ext === "" ? "無副檔名" : strtoupper($ext);
    }

    $uploaderStmt = $pdo->query("
        SELECT DISTINCT f.uploader_id, u.NAME AS uploader_name
        FROM application_files f
        LEFT JOIN users u ON f.uploader_id = u.ID
        ORDER BY u.NAME ASC, f.uploader_id ASC
    ");
    foreach ($uploaderStmt->fetchAll() as $row) {
        $id = isset($row["uploader_id"]) ? trim((string)$row["uploader_id"]) : "";
        $key = $id === "" ? "__unknown" : $id;
        $uploaders[$key] = document_uploader_label($row);
    }

    $where = array();
    $params = array();

    if ($filterFormat !== "all") {
        if ($filterFormat === "no_ext") {
            $where[] = "$extensionExpr = ''";
        } elseif (isset($formats[$filterFormat])) {
            $where[] = "$extensionExpr = :format";
            $params[":format"] = strtolower($filterFormat);
        }
    }

    if ($filterUploader !== "all") {
        if ($filterUploader === "__unknown") {
            $where[] = "(f.uploader_id IS NULL OR f.uploader_id = '')";
        } else {
            $where[] = "f.uploader_id = :uploader";
            $params[":uploader"] = $filterUploader;
        }
    }

    if ($filterDetailType !== "all") {
        switch ($filterDetailType) {
            case "announcement":
                $where[] = "f.file_category = 1";
                break;
            case "application_autobi":
                $where[] = "f.file_category = 2 AND f.file_type = 'autobi'";
                break;
            case "application_support":
                $where[] = "f.file_category = 2 AND f.file_type = 'support'";
                break;
            case "application_other":
                $where[] = "f.file_category = 2 AND (f.file_type IS NULL OR f.file_type NOT IN ('autobi', 'support'))";
                break;
            case "ticket":
                $where[] = "f.file_category = 3";
                break;
            case "recommendation":
                $where[] = "f.file_category = 4";
                break;
            case "other":
                $where[] = "(f.file_category IS NULL OR f.file_category NOT IN (1, 2, 3, 4))";
                break;
        }
    }

    if ($filterUploaderRole !== "all") {
        if ($filterUploaderRole === "0") {
            $where[] = "(u.ROLE IS NULL OR u.ROLE NOT IN (1, 2, 3, 4))";
        } elseif (in_array($filterUploaderRole, array("1", "2", "3", "4"))) {
            $where[] = "u.ROLE = :uploader_role";
            $params[":uploader_role"] = (int)$filterUploaderRole;
        }
    }

    if ($searchName !== "") {
        $where[] = "(f.original_name LIKE :search_name_original OR f.stored_name LIKE :search_name_stored)";
        $params[":search_name_original"] = "%" . $searchName . "%";
        $params[":search_name_stored"] = "%" . $searchName . "%";
    }

    if ($searchUploaderId !== "") {
        $where[] = "f.uploader_id LIKE :search_uploader_id";
        $params[":search_uploader_id"] = "%" . $searchUploaderId . "%";
    }

    if ($searchUploaderName !== "") {
        $where[] = "u.NAME LIKE :search_uploader_name";
        $params[":search_uploader_name"] = "%" . $searchUploaderName . "%";
    }

    if ($searchUploaderEmail !== "") {
        $where[] = "u.EMAIL LIKE :search_uploader_email";
        $params[":search_uploader_email"] = "%" . $searchUploaderEmail . "%";
    }

    if ($searchContext !== "") {
        $where[] = "(
            CAST(f.announcement_id AS CHAR) LIKE :search_context_announcement OR
            CAST(f.application_id AS CHAR) LIKE :search_context_application OR
            CAST(f.scholarship_id AS CHAR) LIKE :search_context_scholarship OR
            CAST(f.ticket_id AS CHAR) LIKE :search_context_ticket OR
            CAST(f.recommendation_id AS CHAR) LIKE :search_context_recommendation
        )";
        $params[":search_context_announcement"] = "%" . $searchContext . "%";
        $params[":search_context_application"] = "%" . $searchContext . "%";
        $params[":search_context_scholarship"] = "%" . $searchContext . "%";
        $params[":search_context_ticket"] = "%" . $searchContext . "%";
        $params[":search_context_recommendation"] = "%" . $searchContext . "%";
    }

    if ($searchSystem !== "") {
        $where[] = "(
            CAST(f.id AS CHAR) LIKE :search_system_id OR
            f.mime_type LIKE :search_system_mime OR
            f.file_type LIKE :search_system_type OR
            f.created_at LIKE :search_system_created OR
            $extensionExpr LIKE :search_system_ext
        )";
        $params[":search_system_id"] = "%" . $searchSystem . "%";
        $params[":search_system_mime"] = "%" . $searchSystem . "%";
        $params[":search_system_type"] = "%" . $searchSystem . "%";
        $params[":search_system_created"] = "%" . $searchSystem . "%";
        $params[":search_system_ext"] = "%" . $searchSystem . "%";
    }

    $whereSql = empty($where) ? "" : " WHERE " . implode(" AND ", $where);
    $fromSql = "
        FROM application_files f
        LEFT JOIN users u ON f.uploader_id = u.ID
    ";

    $countStmt = $pdo->prepare("SELECT COUNT(*) " . $fromSql . $whereSql);
    $countStmt->execute($params);
    $totalFiles = (int)$countStmt->fetchColumn();

    if ($pageSize === "all") {
        $totalPages = 1;
        $page = 1;
        $limitSql = "";
    } else {
        $totalPages = max(1, (int)ceil($totalFiles / $pageSize));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $pageSize;
        $limitSql = " LIMIT " . (int)$pageSize . " OFFSET " . (int)$offset;
    }

    $stmt = $pdo->prepare("
        SELECT f.*, u.NAME AS uploader_name, u.EMAIL AS uploader_email, u.ROLE AS uploader_role
        " . $fromSql . $whereSql . "
        ORDER BY f.created_at DESC, f.id DESC
        " . $limitSql
    );
    $stmt->execute($params);
    $files = $stmt->fetchAll();

    if ($totalFiles > 0) {
        $startRow = $pageSize === "all" ? 1 : $offset + 1;
        $endRow = $pageSize === "all" ? $totalFiles : min($offset + $pageSize, $totalFiles);
    }
} catch (PDOException $e) {
    die("查詢失敗：" . $e->getMessage());
}

$pageTitle = "檔案管理";
$activeNav = "document_management.php";
$siteHeaderExtraHead = '
<style>
    .document-name {
        min-width: 220px;
        max-width: 340px;
        font-weight: 800;
        overflow-wrap: anywhere;
    }

    .document-meta {
        display: block;
        margin-top: 4px;
        color: var(--site-muted);
        font-size: 12px;
        font-weight: 600;
    }

    .document-uploader-email {
        display: block;
        margin-top: 4px;
        color: var(--site-muted);
        font-size: 12px;
        overflow-wrap: anywhere;
    }

    .document-actions {
        display: flex;
        flex-wrap: nowrap;
        gap: 6px;
    }

    .document-check {
        width: 18px;
        height: 18px;
    }

    .document-preview-frame {
        width: 100%;
        height: min(72vh, 760px);
        border: 1px solid var(--site-border);
        border-radius: 6px;
        background: #f8fafc;
    }

    .document-docx-preview {
        width: 100%;
        min-height: min(72vh, 760px);
        max-height: min(72vh, 760px);
        overflow: auto;
        padding: 24px;
        border: 1px solid var(--site-border);
        border-radius: 6px;
        background: #fff;
    }

    .document-docx-preview img {
        max-width: 100%;
        height: auto;
    }

    @media (max-width: 640px) {
        .document-actions {
            flex-wrap: wrap;
        }
    }
</style>';
?>
<?php require __DIR__ . "/../header.php"; ?>

<div class="mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">檔案管理</h1>
        <div class="text-secondary">管理系統使用者上傳的檔案</div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
    <form method="get" action="document_management.php">
        <input type="hidden" name="page_size" value="<?php echo document_h($pageSizeParam); ?>">
        <input type="hidden" name="page" value="1">

        <div class="border-bottom pb-3 mb-3">
            <h2 class="h6 fw-bold mb-3">篩選</h2>
            <div class="row g-3 row-cols-1 row-cols-md-2 row-cols-xl-4" aria-label="檔案篩選">
                <div class="col">
                    <label class="form-label fw-semibold" for="documentFilterFormat">格式</label>
                    <select class="form-select" id="documentFilterFormat" name="format">
                        <option value="all">全部格式</option>
                        <?php foreach ($formats as $formatValue => $formatLabel): ?>
                            <option value="<?php echo document_h($formatValue); ?>"<?php echo document_selected($filterFormat, $formatValue); ?>>
                                <?php echo document_h($formatLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label class="form-label fw-semibold" for="documentFilterDetailType">檔案歸屬</label>
                    <select class="form-select" id="documentFilterDetailType" name="detail_type">
                        <option value="all">全部檔案歸屬</option>
                        <?php foreach ($detailTypes as $detailValue => $detailLabel): ?>
                            <option value="<?php echo document_h($detailValue); ?>"<?php echo document_selected($filterDetailType, $detailValue); ?>>
                                <?php echo document_h($detailLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label class="form-label fw-semibold" for="documentFilterUploader">上傳人</label>
                    <select class="form-select" id="documentFilterUploader" name="uploader">
                        <option value="all">全部上傳人</option>
                        <?php foreach ($uploaders as $uploaderValue => $uploaderLabel): ?>
                            <option value="<?php echo document_h($uploaderValue); ?>"<?php echo document_selected($filterUploader, $uploaderValue); ?>>
                                <?php echo document_h($uploaderLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label class="form-label fw-semibold" for="documentFilterUploaderRole">上傳人身分</label>
                    <select class="form-select" id="documentFilterUploaderRole" name="uploader_role">
                        <option value="all">全部身分</option>
                        <option value="1"<?php echo document_selected($filterUploaderRole, "1"); ?>>學生</option>
                        <option value="2"<?php echo document_selected($filterUploaderRole, "2"); ?>>推薦人</option>
                        <option value="3"<?php echo document_selected($filterUploaderRole, "3"); ?>>管理員</option>
                        <option value="4"<?php echo document_selected($filterUploaderRole, "4"); ?>>獎助單位</option>
                        <option value="0"<?php echo document_selected($filterUploaderRole, "0"); ?>>未知</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="border-bottom pb-3 mb-3">
            <h2 class="h6 fw-bold mb-3">搜尋</h2>
            <div class="row g-3 row-cols-1 row-cols-md-2 row-cols-xl-3" aria-label="檔案搜尋">
                <div class="col">
                    <label class="form-label fw-semibold" for="documentSearchName">檔案名</label>
                    <input class="form-control" id="documentSearchName" name="search_name" type="text" value="<?php echo document_input_value("search_name"); ?>" placeholder="原始檔名或儲存檔名">
                </div>
                <div class="col">
                    <label class="form-label fw-semibold" for="documentSearchUploaderId">上傳人 ID</label>
                    <input class="form-control" id="documentSearchUploaderId" name="search_uploader_id" type="text" value="<?php echo document_input_value("search_uploader_id"); ?>" placeholder="帳號 ID">
                </div>
                <div class="col">
                    <label class="form-label fw-semibold" for="documentSearchUploaderName">上傳人姓名</label>
                    <input class="form-control" id="documentSearchUploaderName" name="search_uploader_name" type="text" value="<?php echo document_input_value("search_uploader_name"); ?>" placeholder="姓名或單位名稱">
                </div>
                <div class="col">
                    <label class="form-label fw-semibold" for="documentSearchUploaderEmail">上傳人 Email</label>
                    <input class="form-control" id="documentSearchUploaderEmail" name="search_uploader_email" type="text" value="<?php echo document_input_value("search_uploader_email"); ?>" placeholder="email">
                </div>
                <div class="col">
                    <label class="form-label fw-semibold" for="documentSearchContext">關聯編號</label>
                    <input class="form-control" id="documentSearchContext" name="search_context" type="text" value="<?php echo document_input_value("search_context"); ?>" placeholder="公告、申請、獎學金、工單、推薦">
                </div>
                <div class="col">
                    <label class="form-label fw-semibold" for="documentSearchSystem">系統欄位</label>
                    <input class="form-control" id="documentSearchSystem" name="search_system" type="text" value="<?php echo document_input_value("search_system"); ?>" placeholder="檔案 ID、MIME、子類型">
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 justify-content-end mt-3">
                <a class="btn btn-outline-secondary" href="document_management.php">清除</a>
                <button class="btn btn-primary" type="submit">套用</button>
            </div>
        </div>
    </form>

    <form method="post" action="document_process.php" data-document-form>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="text-secondary">批量操作套用於已勾選的檔案。</div>
            <button type="submit" name="bulk_delete" value="1" class="btn btn-danger" id="documentBulkDelete" disabled>批量刪除</button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th><input class="document-check" type="checkbox" id="documentSelectAll" aria-label="全選"></th>
                        <th>檔案</th>
                        <th>格式</th>
                        <th>類型</th>
                        <th>上傳人</th>
                        <th>關聯</th>
                        <th>大小</th>
                        <th>上傳時間</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="documentRows">
                    <?php foreach ($files as $file): ?>
                        <?php
                        $format = document_format_label($file);
                        $category = uploaded_file_category_label(isset($file["file_category"]) ? $file["file_category"] : 0);
                        $detailType = document_detail_type_label($file);
                        $uploader = document_uploader_label($file);
                        $uploaderId = isset($file["uploader_id"]) ? trim((string)$file["uploader_id"]) : "";
                        $uploaderName = isset($file["uploader_name"]) ? trim((string)$file["uploader_name"]) : "";
                        $uploaderEmail = isset($file["uploader_email"]) ? trim((string)$file["uploader_email"]) : "";
                        $uploaderRole = document_uploader_role_label(isset($file["uploader_role"]) ? $file["uploader_role"] : 0);
                        $context = document_context_label($file);
                        $previewKind = uploaded_file_preview_kind($file);
                        $previewUrl = uploaded_file_preview_url($file["id"]);
                        $downloadUrl = uploaded_file_view_url($file["id"]);
                        $createdAt = empty($file["created_at"]) ? "未知" : $file["created_at"];
                        $storedName = isset($file["stored_name"]) ? (string)$file["stored_name"] : "";
                        $mimeType = isset($file["mime_type"]) ? (string)$file["mime_type"] : "";
                        $fileType = isset($file["file_type"]) ? (string)$file["file_type"] : "";
                        $announcementId = isset($file["announcement_id"]) ? (string)$file["announcement_id"] : "";
                        $applicationId = isset($file["application_id"]) ? (string)$file["application_id"] : "";
                        $scholarshipId = isset($file["scholarship_id"]) ? (string)$file["scholarship_id"] : "";
                        $ticketId = isset($file["ticket_id"]) ? (string)$file["ticket_id"] : "";
                        $recommendationId = isset($file["recommendation_id"]) ? (string)$file["recommendation_id"] : "";
                        $nameSearch = strtolower($file["original_name"] . " " . $storedName);
                        $contextSearch = strtolower($context . " " . $announcementId . " " . $applicationId . " " . $scholarshipId . " " . $ticketId . " " . $recommendationId);
                        $systemSearch = strtolower($file["id"] . " " . $mimeType . " " . $fileType . " " . $format . " " . $createdAt);
                        ?>
                        <tr
                            data-document-row
                            data-format="<?php echo document_h($format); ?>"
                            data-uploader="<?php echo document_h($uploader); ?>"
                            data-uploader-id="<?php echo document_h(strtolower($uploaderId)); ?>"
                            data-uploader-name="<?php echo document_h(strtolower($uploaderName)); ?>"
                            data-uploader-email="<?php echo document_h(strtolower($uploaderEmail)); ?>"
                            data-uploader-role="<?php echo document_h($uploaderRole); ?>"
                            data-category="<?php echo document_h($category); ?>"
                            data-detail-type="<?php echo document_h($detailType); ?>"
                            data-name-search="<?php echo document_h($nameSearch); ?>"
                            data-context-search="<?php echo document_h($contextSearch); ?>"
                            data-system-search="<?php echo document_h($systemSearch); ?>">
                            <td>
                                <input class="document-check document-row-check" type="checkbox" name="selected_ids[]" value="<?php echo (int)$file["id"]; ?>" aria-label="選擇檔案 <?php echo (int)$file["id"]; ?>">
                            </td>
                            <td>
                                <div class="document-name">
                                    <?php echo document_h($file["original_name"]); ?>
                                    <span class="document-meta">#<?php echo (int)$file["id"]; ?> / <?php echo document_h($file["stored_name"] ?: "舊資料"); ?></span>
                                </div>
                            </td>
                            <td><?php echo document_h($format); ?></td>
                            <td>
                                <?php echo document_h($category); ?>
                                <span class="document-meta"><?php echo document_h($detailType); ?></span>
                            </td>
                            <td>
                                <?php echo document_h($uploader); ?>
                                <span class="document-meta"><?php echo document_h($uploaderRole); ?></span>
                                <?php if ($uploaderEmail !== ""): ?>
                                    <span class="document-uploader-email"><?php echo document_h($uploaderEmail); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo document_h($context); ?></td>
                            <td><?php echo document_h(document_size_label($file["file_size"])); ?></td>
                            <td><?php echo document_h($createdAt); ?></td>
                            <td>
                                <div class="document-actions">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary document-preview-btn"
                                        data-preview-kind="<?php echo document_h($previewKind); ?>"
                                        data-preview-url="<?php echo document_h($previewUrl); ?>"
                                        data-download-url="<?php echo document_h($downloadUrl); ?>"
                                        data-file-name="<?php echo document_h($file["original_name"]); ?>"
                                        <?php echo $previewKind === "download" ? 'disabled title="此檔案格式不支援預覽"' : ""; ?>>
                                        預覽
                                    </button>
                                    <a class="btn btn-sm btn-outline-success" href="<?php echo document_h($downloadUrl); ?>">下載</a>
                                    <button type="submit" name="delete_one" value="<?php echo (int)$file["id"]; ?>" class="btn btn-sm btn-outline-danger document-delete-one">刪除</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($files)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-secondary">目前沒有檔案。</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
            <div class="text-secondary">
                顯示 <?php echo document_h($startRow); ?>-<?php echo document_h($endRow); ?> / <?php echo document_h($totalFiles); ?> 份檔案，
                已選 <span id="documentSelectedCount">0</span> 份
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2" aria-label="檔案清單分頁">
                <label class="form-label mb-0" for="documentPageSize">每頁</label>
                <select class="form-select w-auto" id="documentPageSize" data-url-template="<?php echo document_h(document_query_url(array("page" => 1, "page_size" => "__PAGE_SIZE__"))); ?>">
                    <option value="10"<?php echo document_selected($pageSizeParam, "10"); ?>>10</option>
                    <option value="50"<?php echo document_selected($pageSizeParam, "50"); ?>>50</option>
                    <option value="100"<?php echo document_selected($pageSizeParam, "100"); ?>>100</option>
                    <option value="500"<?php echo document_selected($pageSizeParam, "500"); ?>>500</option>
                    <option value="all"<?php echo document_selected($pageSizeParam, "all"); ?>>全部</option>
                </select>
                <?php if ($page > 1): ?>
                    <a class="btn btn-outline-secondary" href="<?php echo document_h(document_query_url(array("page" => $page - 1))); ?>">上一頁</a>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" disabled>上一頁</button>
                <?php endif; ?>
                <span class="text-secondary">第 <?php echo document_h($page); ?> / <?php echo document_h($totalPages); ?> 頁</span>
                <?php if ($page < $totalPages): ?>
                    <a class="btn btn-outline-secondary" href="<?php echo document_h(document_query_url(array("page" => $page + 1))); ?>">下一頁</a>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" disabled>下一頁</button>
                <?php endif; ?>
            </div>
        </div>
    </form>
    </div>
</div>

<div class="modal fade" id="documentPreviewModal" tabindex="-1" aria-labelledby="documentPreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="documentPreviewTitle">檔案預覽</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
            </div>
            <div class="modal-body">
                <div id="documentPreviewBody"></div>
            </div>
            <div class="modal-footer">
                <a id="documentPreviewDownload" class="btn btn-outline-success" href="#">下載</a>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">關閉</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/mammoth@1.8.0/mammoth.browser.min.js"></script>
<script>
    (function() {
        var selectedCount = document.getElementById("documentSelectedCount");
        var pageSizeSelect = document.getElementById("documentPageSize");
        var bulkDelete = document.getElementById("documentBulkDelete");
        var selectAll = document.getElementById("documentSelectAll");
        var form = document.querySelector("[data-document-form]");
        var rows = Array.prototype.slice.call(document.querySelectorAll("[data-document-row]"));

        function updateSelectionState() {
            var checkedRows = rows.filter(function(row) {
                var checkbox = row.querySelector(".document-row-check");
                return checkbox && checkbox.checked;
            });

            selectedCount.textContent = String(checkedRows.length);
            bulkDelete.disabled = checkedRows.length === 0;

            if (selectAll) {
                selectAll.checked = rows.length > 0 && checkedRows.length === rows.length;
                selectAll.indeterminate = checkedRows.length > 0 && checkedRows.length < rows.length;
            }
        }

        if (pageSizeSelect) {
            pageSizeSelect.addEventListener("change", function() {
                var template = pageSizeSelect.getAttribute("data-url-template");
                if (template) {
                    window.location.href = template.replace("__PAGE_SIZE__", encodeURIComponent(pageSizeSelect.value));
                }
            });
        }

        rows.forEach(function(row) {
            var checkbox = row.querySelector(".document-row-check");
            if (checkbox) {
                checkbox.addEventListener("change", updateSelectionState);
            }
        });

        if (selectAll) {
            selectAll.addEventListener("change", function() {
                rows.forEach(function(row) {
                    var checkbox = row.querySelector(".document-row-check");
                    if (checkbox) {
                        checkbox.checked = selectAll.checked;
                    }
                });
                updateSelectionState();
            });
        }

        if (form) {
            form.addEventListener("submit", function(event) {
                var submitter = event.submitter;
                var message = submitter && submitter.classList.contains("document-delete-one")
                    ? "確定要刪除此檔案嗎？此操作無法復原。"
                    : "確定要刪除已選檔案嗎？此操作無法復原。";

                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        }

        document.querySelectorAll(".document-preview-btn").forEach(function(button) {
            button.addEventListener("click", function() {
                var kind = button.getAttribute("data-preview-kind");
                var previewUrl = button.getAttribute("data-preview-url");
                var downloadUrl = button.getAttribute("data-download-url");
                var fileName = button.getAttribute("data-file-name") || "檔案預覽";
                var modalEl = document.getElementById("documentPreviewModal");
                var body = document.getElementById("documentPreviewBody");
                var title = document.getElementById("documentPreviewTitle");
                var download = document.getElementById("documentPreviewDownload");

                title.textContent = fileName;
                download.href = downloadUrl;
                body.innerHTML = "";

                if (kind === "image") {
                    var image = document.createElement("img");
                    image.src = previewUrl;
                    image.alt = fileName;
                    image.style.maxWidth = "100%";
                    image.style.height = "auto";
                    body.appendChild(image);
                } else if (kind === "pdf" || kind === "text" || kind === "doc") {
                    var frame = document.createElement("iframe");
                    frame.className = "document-preview-frame";
                    frame.src = previewUrl;
                    frame.title = fileName;
                    body.appendChild(frame);
                } else if (kind === "docx") {
                    var container = document.createElement("div");
                    container.className = "document-docx-preview";
                    container.textContent = "載入中...";
                    body.appendChild(container);

                    fetch(previewUrl, { credentials: "same-origin" })
                        .then(function(response) {
                            if (!response.ok) {
                                throw new Error("preview failed");
                            }
                            return response.arrayBuffer();
                        })
                        .then(function(buffer) {
                            return mammoth.convertToHtml({ arrayBuffer: buffer });
                        })
                        .then(function(result) {
                            container.innerHTML = result.value || "此檔案沒有可顯示的內容。";
                        })
                        .catch(function() {
                            container.innerHTML = "此檔案暫時無法預覽，請下載查看。";
                        });
                }

                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
        });

        updateSelectionState();
    })();
</script>
</main>
</body>
</html>
