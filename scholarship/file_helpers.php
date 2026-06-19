<?php

function table_has_column($pdo, $tableName, $columnName)
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute(array((string)$tableName, (string)$columnName));
    return (bool)$stmt->fetchColumn();
}

function table_has_index($pdo, $tableName, $indexName)
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
        LIMIT 1
    ");
    $stmt->execute(array((string)$tableName, (string)$indexName));
    return (bool)$stmt->fetchColumn();
}

function ensure_application_files_table($pdo)
{
    $columns = array(
        "uploader_id" => "ALTER TABLE application_files ADD COLUMN uploader_id char(10) NULL AFTER path",
        "file_category" => "ALTER TABLE application_files ADD COLUMN file_category int(11) NULL AFTER uploader_id",
        "stored_name" => "ALTER TABLE application_files ADD COLUMN stored_name varchar(255) NULL AFTER file_category",
        "mime_type" => "ALTER TABLE application_files ADD COLUMN mime_type varchar(255) NULL AFTER stored_name",
        "file_size" => "ALTER TABLE application_files ADD COLUMN file_size int(11) NULL AFTER mime_type",
        "file_path" => "ALTER TABLE application_files ADD COLUMN file_path varchar(255) NULL AFTER file_size",
        "announcement_id" => "ALTER TABLE application_files ADD COLUMN announcement_id int(11) NULL AFTER file_path",
        "application_id" => "ALTER TABLE application_files ADD COLUMN application_id int(11) NULL AFTER announcement_id",
        "scholarship_id" => "ALTER TABLE application_files ADD COLUMN scholarship_id int(11) NULL AFTER application_id",
        "scholarship_provider_id" => "ALTER TABLE application_files ADD COLUMN scholarship_provider_id char(10) NULL AFTER scholarship_id",
        "ticket_id" => "ALTER TABLE application_files ADD COLUMN ticket_id int(11) NULL AFTER scholarship_provider_id",
        "recommendation_id" => "ALTER TABLE application_files ADD COLUMN recommendation_id int(11) NULL AFTER ticket_id",
        "created_at" => "ALTER TABLE application_files ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP AFTER recommendation_id",
    );

    foreach ($columns as $column => $sql) {
        if (!table_has_column($pdo, "application_files", $column)) {
            $pdo->exec($sql);
        }
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM application_files LIKE 'apno'");
    $apno = $stmt->fetch();
    if ($apno && strtoupper((string)$apno["Null"]) === "NO") {
        $pdo->exec("ALTER TABLE application_files MODIFY apno int(11) NULL");
    }

    ensure_recommendations_table($pdo);
}

function ensure_teachers_table($pdo)
{
    if (!table_has_column($pdo, "teachers", "DNAME")) {
        return;
    }

    if (!table_has_column($pdo, "teachers", "UNIT_NAME")) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN UNIT_NAME varchar(100) NULL AFTER DNAME");
    }

    if (!table_has_column($pdo, "teachers", "JOB_TITLE")) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN JOB_TITLE varchar(100) NULL AFTER UNIT_NAME");
    }
}

function ensure_recommendations_table($pdo)
{
    if (!table_has_column($pdo, "recommendations", "content")) {
        return;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM recommendations LIKE 'content'");
    $content = $stmt->fetch();
    if ($content && strtoupper((string)$content["Null"]) === "NO") {
        $pdo->exec("ALTER TABLE recommendations MODIFY content text NULL");
    }

    if (!table_has_column($pdo, "recommendations", "expires_at")) {
        $pdo->exec("ALTER TABLE recommendations ADD COLUMN expires_at datetime NULL AFTER created_at");
    }

    if (!table_has_column($pdo, "recommendations", "submitted_at")) {
        $pdo->exec("ALTER TABLE recommendations ADD COLUMN submitted_at datetime NULL AFTER expires_at");
    }

    if (!table_has_column($pdo, "recommendations", "draft_content")) {
        $pdo->exec("ALTER TABLE recommendations ADD COLUMN draft_content text NULL AFTER content");
    }

    if (!table_has_column($pdo, "recommendations", "status")) {
        $pdo->exec("ALTER TABLE recommendations ADD COLUMN status varchar(20) NOT NULL DEFAULT 'pending' AFTER submitted_at");
    }

    if (!table_has_column($pdo, "recommendations", "rejected_reason")) {
        $pdo->exec("ALTER TABLE recommendations ADD COLUMN rejected_reason text NULL AFTER status");
    }

    if (!table_has_column($pdo, "recommendations", "rejected_source")) {
        $pdo->exec("ALTER TABLE recommendations ADD COLUMN rejected_source varchar(20) NULL AFTER rejected_reason");
    }

    if (!table_has_column($pdo, "recommendations", "rejected_at")) {
        $pdo->exec("ALTER TABLE recommendations ADD COLUMN rejected_at datetime NULL AFTER rejected_source");
    }

    if (!table_has_column($pdo, "recommendations", "teacher_unit")) {
        $pdo->exec("ALTER TABLE recommendations ADD COLUMN teacher_unit varchar(100) NULL AFTER teacher_email");
    }

    if (!table_has_column($pdo, "recommendations", "teacher_title")) {
        $pdo->exec("ALTER TABLE recommendations ADD COLUMN teacher_title varchar(100) NULL AFTER teacher_unit");
    }

    if (!table_has_index($pdo, "recommendations", "uq_recommendations_application_id")) {
        $dupStmt = $pdo->query("
            SELECT application_id
            FROM recommendations
            WHERE application_id IS NOT NULL
            GROUP BY application_id
            HAVING COUNT(*) > 1
            LIMIT 1
        ");

        if (!$dupStmt->fetch()) {
            $pdo->exec("ALTER TABLE recommendations ADD UNIQUE KEY uq_recommendations_application_id (application_id)");
        }
    }
}

function uploaded_file_view_url($fileId)
{
    return "/scholarship/file_view.php?id=" . urlencode((string)$fileId);
}

function uploaded_file_preview_url($fileId)
{
    return "/scholarship/file_preview.php?id=" . urlencode((string)$fileId);
}

function uploaded_file_category_label($fileCategory)
{
    switch ((int)$fileCategory) {
        case 1:
            return "公告附件";
        case 2:
            return "申請者上傳";
        case 3:
            return "工單附件";
        case 4:
            return "導師推薦信";
        default:
            return "其他文件";
    }
}

function uploaded_file_extension($file)
{
    $name = "";
    if (!empty($file["original_name"])) {
        $name = (string)$file["original_name"];
    } elseif (!empty($file["stored_name"])) {
        $name = (string)$file["stored_name"];
    } elseif (!empty($file["file_path"])) {
        $name = (string)$file["file_path"];
    } elseif (!empty($file["path"])) {
        $name = (string)$file["path"];
    }

    return strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
}

function uploaded_file_preview_kind($file)
{
    $ext = uploaded_file_extension($file);
    $mimeType = isset($file["mime_type"]) ? strtolower((string)$file["mime_type"]) : "";

    if (strpos($mimeType, "image/") === 0 || in_array($ext, array("jpg", "jpeg", "png", "gif", "webp"))) {
        return "image";
    }

    if ($mimeType === "application/pdf" || $ext === "pdf") {
        return "pdf";
    }

    if ($ext === "docx") {
        return "docx";
    }

    if ($ext === "doc") {
        return "doc";
    }

    if (strpos($mimeType, "text/") === 0 || in_array($ext, array("txt", "csv"))) {
        return "text";
    }

    return "download";
}

function uploaded_file_full_path($file)
{
    $baseDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . "user_file");
    $filePath = !empty($file["file_path"]) ? $file["file_path"] : (isset($file["path"]) ? $file["path"] : "");

    if (!$baseDir || $filePath === "" || strpos($filePath, "://") !== false) {
        return false;
    }

    $fullPath = realpath(__DIR__ . DIRECTORY_SEPARATOR . $filePath);
    if (!$fullPath) {
        return false;
    }

    $baseDirCheck = strtolower($baseDir . DIRECTORY_SEPARATOR);
    $fullPathCheck = strtolower($fullPath);
    if (strpos($fullPathCheck, $baseDirCheck) !== 0 || !is_file($fullPath)) {
        return false;
    }

    return $fullPath;
}

function delete_uploaded_file_record($pdo, $fileId)
{
    ensure_application_files_table($pdo);

    $stmt = $pdo->prepare("SELECT * FROM application_files WHERE id = ? LIMIT 1");
    $stmt->execute(array((int)$fileId));
    $file = $stmt->fetch();

    if (!$file) {
        return false;
    }

    $fullPath = uploaded_file_full_path($file);

    $deleteStmt = $pdo->prepare("DELETE FROM application_files WHERE id = ?");
    $deleteStmt->execute(array((int)$fileId));

    if ($fullPath && is_file($fullPath)) {
        @unlink($fullPath);
    }

    return true;
}

function normalize_uploaded_context($context)
{
    return array(
        "announcement_id" => isset($context["announcement_id"]) && $context["announcement_id"] !== "" ? (int)$context["announcement_id"] : null,
        "application_id" => isset($context["application_id"]) && $context["application_id"] !== "" ? (int)$context["application_id"] : null,
        "scholarship_id" => isset($context["scholarship_id"]) && $context["scholarship_id"] !== "" ? (int)$context["scholarship_id"] : null,
        "scholarship_provider_id" => isset($context["scholarship_provider_id"]) && $context["scholarship_provider_id"] !== "" ? (string)$context["scholarship_provider_id"] : null,
        "ticket_id" => isset($context["ticket_id"]) && $context["ticket_id"] !== "" ? (int)$context["ticket_id"] : null,
        "recommendation_id" => isset($context["recommendation_id"]) && $context["recommendation_id"] !== "" ? (int)$context["recommendation_id"] : null,
    );
}

function guess_upload_mime_type($tmpPath)
{
    if (function_exists("finfo_open")) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
            if ($mime) {
                return $mime;
            }
        }
    }

    return "application/octet-stream";
}

function store_uploaded_file($pdo, $file, $fileType, $uploaderId, $context)
{
    ensure_application_files_table($pdo);

    $error = isset($file["error"]) ? (int)$file["error"] : UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException("檔案上傳失敗（error=" . $error . "）");
    }

    $originalName = isset($file["name"]) ? basename($file["name"]) : "file";
    $tmpPath = isset($file["tmp_name"]) ? $file["tmp_name"] : "";
    $size = isset($file["size"]) ? (int)$file["size"] : 0;

    if ($tmpPath === "" || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException("找不到上傳暫存檔案");
    }

    if ($size <= 0) {
        throw new RuntimeException("檔案內容不可為空");
    }

    $maxSize = isset($context["max_size"]) ? (int)$context["max_size"] : 20 * 1024 * 1024;
    if ($size > $maxSize) {
        throw new RuntimeException("檔案大小超過限制");
    }

    $allowedExt = isset($context["allowed_ext"]) ? $context["allowed_ext"] : array(
        "pdf",
        "doc",
        "docx",
        "jpg",
        "jpeg",
        "png",
        "xls",
        "xlsx",
        "txt",
        "zip",
        "odt",
        "odc",
        "webp",
        "ppt",
        'pptx',
        "ods",
        "gif"
    );
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === "" || !in_array($ext, $allowedExt)) {
        throw new RuntimeException("不支援的檔案格式：" . htmlspecialchars($ext, ENT_QUOTES, "UTF-8"));
    }

    $blockedExt = array("apk", "mht", "php", "phtml", "phar", "exe", "bat", "cmd", "js", "html", "htm");
    if (in_array($ext, $blockedExt)) {
        throw new RuntimeException("此檔案格式不可上傳");
    }

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . "user_file";
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0775, true)) {
            throw new RuntimeException("上傳資料夾建立失敗");
        }
    }

    $random = str_replace(".", "", uniqid("", true)) . mt_rand(1000, 9999);
    $storedName = "f" . (int)$fileType . "_" . date("Ymd_His") . "_" . $random . "." . $ext;
    $dest = $uploadDir . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($tmpPath, $dest)) {
        throw new RuntimeException("檔案搬移失敗");
    }

    $normalized = normalize_uploaded_context($context);
    $mimeType = guess_upload_mime_type($dest);
    $relativePath = "user_file/" . $storedName;
    $viewUrl = uploaded_file_view_url("__pending__");
    $subTypeMap = array(
        1 => "announcement",
        2 => "application",
        3 => "ticket",
        4 => "recommendation",
    );
    $subType = isset($context["file_subtype"]) ? $context["file_subtype"] : (isset($subTypeMap[(int)$fileType]) ? $subTypeMap[(int)$fileType] : "file");
    $apno = $normalized["application_id"];

    $stmt = $pdo->prepare("
        INSERT INTO application_files
            (apno, file_type, original_name, path, uploader_id, file_category, stored_name, mime_type, file_size, file_path,
             announcement_id, application_id, scholarship_id, scholarship_provider_id, ticket_id, recommendation_id)
        VALUES
            (:apno, :file_type, :original_name, :path, :uploader_id, :file_category, :stored_name, :mime_type, :file_size, :file_path,
             :announcement_id, :application_id, :scholarship_id, :scholarship_provider_id, :ticket_id, :recommendation_id)
    ");
    $stmt->execute(array(
        ":apno" => $apno,
        ":file_type" => $subType,
        ":path" => $viewUrl,
        ":uploader_id" => $uploaderId,
        ":file_category" => (int)$fileType,
        ":original_name" => $originalName,
        ":stored_name" => $storedName,
        ":mime_type" => $mimeType,
        ":file_size" => $size,
        ":file_path" => $relativePath,
        ":announcement_id" => $normalized["announcement_id"],
        ":application_id" => $normalized["application_id"],
        ":scholarship_id" => $normalized["scholarship_id"],
        ":scholarship_provider_id" => $normalized["scholarship_provider_id"],
        ":ticket_id" => $normalized["ticket_id"],
        ":recommendation_id" => $normalized["recommendation_id"],
    ));

    $fileId = (int)$pdo->lastInsertId();
    $viewUrl = uploaded_file_view_url($fileId);

    $stmt = $pdo->prepare("UPDATE application_files SET path = ? WHERE id = ?");
    $stmt->execute(array($viewUrl, $fileId));

    return array(
        "id" => $fileId,
        "original_name" => $originalName,
        "path" => $relativePath,
        "view_url" => uploaded_file_view_url($fileId),
    );
}

function normalize_uploaded_file_entries($files)
{
    if (empty($files) || !isset($files["name"])) {
        return array();
    }

    if (!is_array($files["name"])) {
        return array($files);
    }

    $entries = array();
    foreach ($files["name"] as $index => $name) {
        $entries[] = array(
            "name" => $name,
            "type" => isset($files["type"][$index]) ? $files["type"][$index] : "",
            "tmp_name" => isset($files["tmp_name"][$index]) ? $files["tmp_name"][$index] : "",
            "error" => isset($files["error"][$index]) ? $files["error"][$index] : UPLOAD_ERR_NO_FILE,
            "size" => isset($files["size"][$index]) ? $files["size"][$index] : 0,
        );
    }

    return $entries;
}

function store_uploaded_files($pdo, $files, $fileType, $uploaderId, $context)
{
    $savedFiles = array();

    foreach (normalize_uploaded_file_entries($files) as $file) {
        $error = isset($file["error"]) ? (int)$file["error"] : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $savedFiles[] = store_uploaded_file($pdo, $file, $fileType, $uploaderId, $context);
    }

    return $savedFiles;
}

function delete_uploaded_file($pdo, $fileId, $fileType, $contextColumn, $contextValue)
{
    ensure_application_files_table($pdo);

    $allowedColumns = array(
        "announcement_id" => true,
        "application_id" => true,
        "ticket_id" => true,
        "recommendation_id" => true,
    );
    if (empty($allowedColumns[$contextColumn])) {
        return false;
    }

    $sql = "SELECT * FROM application_files WHERE id = ? AND file_category = ? AND " . $contextColumn . " = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array((int)$fileId, (int)$fileType, $contextValue));
    $file = $stmt->fetch();

    if (!$file) {
        return false;
    }

    $filePath = !empty($file["file_path"]) ? $file["file_path"] : $file["path"];
    $baseDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . "user_file");
    $fullPath = $filePath ? realpath(__DIR__ . DIRECTORY_SEPARATOR . $filePath) : false;

    $deleteStmt = $pdo->prepare("DELETE FROM application_files WHERE id = ?");
    $deleteStmt->execute(array((int)$fileId));

    if ($baseDir && $fullPath && is_file($fullPath)) {
        $baseDirCheck = strtolower($baseDir . DIRECTORY_SEPARATOR);
        $fullPathCheck = strtolower($fullPath);

        if (strpos($fullPathCheck, $baseDirCheck) === 0) {
            $pathCountStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM application_files
                WHERE id <> ? AND (file_path = ? OR path = ?)
            ");
            $pathCountStmt->execute(array((int)$fileId, $filePath, $filePath));

            if ((int)$pathCountStmt->fetchColumn() === 0) {
                @unlink($fullPath);
            }
        }
    }

    return true;
}

function delete_uploaded_files($pdo, $fileIds, $fileType, $contextColumn, $contextValue)
{
    if (!is_array($fileIds)) {
        return 0;
    }

    $deleted = 0;
    $seen = array();
    foreach ($fileIds as $fileId) {
        $fileId = (int)$fileId;
        if ($fileId <= 0 || isset($seen[$fileId])) {
            continue;
        }

        $seen[$fileId] = true;
        if (delete_uploaded_file($pdo, $fileId, $fileType, $contextColumn, $contextValue)) {
            $deleted++;
        }
    }

    return $deleted;
}

function user_can_download_file($pdo, $file, $user)
{
    $fileType = isset($file["file_category"]) ? (int)$file["file_category"] : 0;

    if ($fileType === 1) {
        return true;
    }

    if (empty($user) || empty($user["id"])) {
        return false;
    }

    $userId = (string)$user["id"];
    $role = isset($user["role"]) ? (int)$user["role"] : 0;

    if ($role === 3) {
        return true;
    }

    if ($fileType === 2) {
        if ($file["uploader_id"] === $userId || $file["scholarship_provider_id"] === $userId) {
            return true;
        }

        if (!empty($file["application_id"])) {
            $stmt = $pdo->prepare("
                SELECT a.STID, a.OID, s.provider_id
                FROM application a
                JOIN scholarship s ON a.SCID = s.id
                WHERE a.APNO = ?
                LIMIT 1
            ");
            $stmt->execute(array($file["application_id"]));
            $app = $stmt->fetch();
            if ($app && ($app["STID"] === $userId || $app["OID"] === $userId || $app["provider_id"] === $userId)) {
                return true;
            }

            $stmt = $pdo->prepare("
                SELECT 1
                FROM recommendations
                WHERE application_id = ? AND teacher_id = ?
                LIMIT 1
            ");
            $stmt->execute(array($file["application_id"], $userId));
            if ($stmt->fetchColumn()) {
                return true;
            }
        }

        return false;
    }

    if ($fileType === 3) {
        if (empty($file["ticket_id"])) {
            return false;
        }

        $stmt = $pdo->prepare("SELECT USER_ID FROM tickets WHERE TICKET_ID = ? LIMIT 1");
        $stmt->execute(array($file["ticket_id"]));
        $ticketOwner = $stmt->fetchColumn();
        return $ticketOwner === $userId;
    }

    if ($fileType === 4) {
        if ($file["uploader_id"] === $userId || $file["scholarship_provider_id"] === $userId) {
            return true;
        }

        if (!empty($file["application_id"])) {
            $stmt = $pdo->prepare("
                SELECT a.OID, s.provider_id
                FROM application a
                JOIN scholarship s ON a.SCID = s.id
                WHERE a.APNO = ?
                LIMIT 1
            ");
            $stmt->execute(array($file["application_id"]));
            $app = $stmt->fetch();
            if ($app && ($app["OID"] === $userId || $app["provider_id"] === $userId)) {
                return true;
            }
        }

        return false;
    }

    return false;
}

function fetch_uploaded_files($pdo, $fileType, $contextColumn, $contextValue)
{
    ensure_application_files_table($pdo);

    $allowedColumns = array(
        "announcement_id" => true,
        "application_id" => true,
        "ticket_id" => true,
        "recommendation_id" => true,
    );
    if (empty($allowedColumns[$contextColumn])) {
        return array();
    }

    $sql = "SELECT * FROM application_files WHERE file_category = ? AND " . $contextColumn . " = ? ORDER BY created_at ASC, id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array((int)$fileType, $contextValue));
    return $stmt->fetchAll();
}
