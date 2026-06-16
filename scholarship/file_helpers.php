<?php

function ensure_application_files_table($pdo) {
    return;
}

function uploaded_file_view_url($fileId) {
    return "/scholarship/file_view.php?id=" . urlencode((string)$fileId);
}

function normalize_uploaded_context($context) {
    return array(
        "announcement_id" => isset($context["announcement_id"]) && $context["announcement_id"] !== "" ? (int)$context["announcement_id"] : null,
        "application_id" => isset($context["application_id"]) && $context["application_id"] !== "" ? (int)$context["application_id"] : null,
        "scholarship_id" => isset($context["scholarship_id"]) && $context["scholarship_id"] !== "" ? (int)$context["scholarship_id"] : null,
        "scholarship_provider_id" => isset($context["scholarship_provider_id"]) && $context["scholarship_provider_id"] !== "" ? (string)$context["scholarship_provider_id"] : null,
        "ticket_id" => isset($context["ticket_id"]) && $context["ticket_id"] !== "" ? (int)$context["ticket_id"] : null,
        "recommendation_id" => isset($context["recommendation_id"]) && $context["recommendation_id"] !== "" ? (int)$context["recommendation_id"] : null,
    );
}

function guess_upload_mime_type($tmpPath) {
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

function store_uploaded_file($pdo, $file, $fileType, $uploaderId, $context) {
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

    $allowedExt = isset($context["allowed_ext"]) ? $context["allowed_ext"] : array("pdf",
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
        "ods","gif");
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === "" || !in_array($ext, $allowedExt)) {
        throw new RuntimeException("不支援的檔案格式：" . htmlspecialchars($ext, ENT_QUOTES, "UTF-8"));
    }

    $blockedExt = array("apk","mht","php", "phtml", "phar", "exe", "bat", "cmd", "js", "html", "htm");
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

function user_can_download_file($pdo, $file, $user) {
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

function fetch_uploaded_files($pdo, $fileType, $contextColumn, $contextValue) {
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
