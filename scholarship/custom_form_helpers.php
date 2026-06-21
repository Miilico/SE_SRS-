<?php

function custom_form_allowed_types()
{
    return array("text", "number", "textarea", "file");
}

function custom_form_table_exists($pdo, $tableName)
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
        LIMIT 1
    ");
    $stmt->execute(array((string)$tableName));
    return (bool)$stmt->fetchColumn();
}

function custom_form_column_exists($pdo, $tableName, $columnName)
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

function custom_form_tables_ready($pdo)
{
    return custom_form_table_exists($pdo, "scholarship_fields")
        && custom_form_table_exists($pdo, "application_custom_answers");
}

function custom_form_collection_has_opened($scholarship, $today = null)
{
    if (empty($scholarship["start_date"])) {
        return false;
    }

    $startDate = substr((string)$scholarship["start_date"], 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        return false;
    }

    $today = $today === null ? date("Y-m-d") : substr((string)$today, 0, 10);
    return $startDate <= $today;
}

function custom_form_normalized_label($label)
{
    $label = trim((string)$label);
    $label = preg_replace('/[\s\x{3000}\-_\/]+/u', '', $label);
    return function_exists('mb_strtolower')
        ? mb_strtolower($label, 'UTF-8')
        : strtolower($label);
}

function custom_form_reserved_labels()
{
    return array(
        'gpa',
        '成績',
        'gpa成績',
        '學業成績',
        '排名',
        '班排',
        '系排',
        '班排名',
        '系排名',
        '班排系排',
        '推薦信',
        '推薦人',
        '推薦教師',
        '推薦人email',
        '推薦教師email',
    );
}

function custom_form_validate_unique_labels($labels)
{
    $seen = array();
    $reserved = array_fill_keys(custom_form_reserved_labels(), true);
    foreach ((array)$labels as $rawLabel) {
        $label = trim((string)$rawLabel);
        if ($label === '') {
            continue;
        }

        $normalized = custom_form_normalized_label($label);
        if (isset($reserved[$normalized])) {
            throw new InvalidArgumentException('「' . $label . '」是系統固定欄位，不可重複新增。');
        }
        if (isset($seen[$normalized])) {
            throw new InvalidArgumentException('自訂欄位名稱不可重複：「' . $label . '」。');
        }
        $seen[$normalized] = true;
    }
}

function custom_form_fields_for_scholarship($pdo, $scholarshipId)
{
    if (!custom_form_table_exists($pdo, "scholarship_fields")) {
        return array();
    }

    $noteColumn = custom_form_column_exists($pdo, "scholarship_fields", "field_note")
        ? "field_note"
        : "'' AS field_note";
    $stmt = $pdo->prepare("
        SELECT id, scholarship_id, field_label, field_type, is_required, sort_order, " . $noteColumn . "
        FROM scholarship_fields
        WHERE scholarship_id = ?
        ORDER BY sort_order, id
    ");
    $stmt->execute(array((int)$scholarshipId));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function custom_form_replace_fields($pdo, $scholarshipId, $labels, $types, $requiredValues, $notes = array(), $fieldIds = array())
{
    if (!custom_form_tables_ready($pdo)) {
        throw new RuntimeException("Custom form migration has not been applied.");
    }

    $labels = is_array($labels) ? $labels : array();
    $types = is_array($types) ? $types : array();
    $requiredValues = is_array($requiredValues) ? $requiredValues : array();
    $notes = is_array($notes) ? $notes : array();
    $fieldIds = is_array($fieldIds) ? $fieldIds : array();
    custom_form_validate_unique_labels($labels);

    $hasNote = custom_form_column_exists($pdo, "scholarship_fields", "field_note");
    $insert = $hasNote
        ? $pdo->prepare("
            INSERT INTO scholarship_fields
                (scholarship_id, field_label, field_type, is_required, sort_order, field_note)
            VALUES (?, ?, ?, ?, ?, ?)
        ")
        : $pdo->prepare("
            INSERT INTO scholarship_fields
                (scholarship_id, field_label, field_type, is_required, sort_order)
            VALUES (?, ?, ?, ?, ?)
        ");
    $update = $hasNote
        ? $pdo->prepare("
            UPDATE scholarship_fields
            SET field_label = ?, field_type = ?, is_required = ?, sort_order = ?, field_note = ?
            WHERE id = ? AND scholarship_id = ?
        ")
        : $pdo->prepare("
            UPDATE scholarship_fields
            SET field_label = ?, field_type = ?, is_required = ?, sort_order = ?
            WHERE id = ? AND scholarship_id = ?
        ");

    $existingStmt = $pdo->prepare("SELECT id, field_type FROM scholarship_fields WHERE scholarship_id = ?");
    $existingStmt->execute(array((int)$scholarshipId));
    $existing = array();
    foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[(int)$row["id"]] = $row;
    }
    $keptIds = array();

    $allowedTypes = custom_form_allowed_types();
    foreach ($labels as $index => $rawLabel) {
        $label = trim((string)$rawLabel);
        if ($label === "") {
            continue;
        }

        $type = isset($types[$index]) ? (string)$types[$index] : "text";
        if (!in_array($type, $allowedTypes, true)) {
            throw new InvalidArgumentException("Unsupported custom field type.");
        }

        $isRequired = !empty($requiredValues[$index]) ? 1 : 0;
        $note = isset($notes[$index]) ? trim((string)$notes[$index]) : "";
        $fieldId = isset($fieldIds[$index]) ? (int)$fieldIds[$index] : 0;

        if ($fieldId > 0 && isset($existing[$fieldId])) {
            if ($existing[$fieldId]["field_type"] !== $type) {
                $answerStmt = $pdo->prepare("SELECT 1 FROM application_custom_answers WHERE field_id = ? LIMIT 1");
                $answerStmt->execute(array($fieldId));
                if ($answerStmt->fetchColumn()) {
                    throw new InvalidArgumentException("已有學生填寫的欄位不可變更欄位類型。");
                }
            }
            $params = array($label, $type, $isRequired, $index);
            if ($hasNote) {
                $params[] = $note === "" ? null : $note;
            }
            $params[] = $fieldId;
            $params[] = (int)$scholarshipId;
            $update->execute($params);
            $keptIds[$fieldId] = true;
            continue;
        }

        $params = array((int)$scholarshipId, $label, $type, $isRequired, $index);
        if ($hasNote) {
            $params[] = $note === "" ? null : $note;
        }
        $insert->execute($params);
        $keptIds[(int)$pdo->lastInsertId()] = true;
    }

    foreach (array_keys($existing) as $existingId) {
        if (isset($keptIds[$existingId])) {
            continue;
        }
        $answerStmt = $pdo->prepare("SELECT 1 FROM application_custom_answers WHERE field_id = ? LIMIT 1");
        $answerStmt->execute(array($existingId));
        if ($answerStmt->fetchColumn()) {
            throw new InvalidArgumentException("已有學生填寫的欄位不可刪除，可改為非必填或修改顯示名稱。");
        }
        $pdo->prepare("DELETE FROM scholarship_fields WHERE id = ? AND scholarship_id = ?")
            ->execute(array($existingId, (int)$scholarshipId));
    }
}

function custom_form_answer_values_from_post()
{
    return isset($_POST["CUSTOM_FIELDS"]) && is_array($_POST["CUSTOM_FIELDS"])
        ? $_POST["CUSTOM_FIELDS"]
        : array();
}

function custom_form_uploaded_file($fieldId)
{
    if (empty($_FILES["CUSTOM_FILES"]) || !is_array($_FILES["CUSTOM_FILES"]["name"])) {
        return null;
    }

    $key = (string)(int)$fieldId;
    if (!isset($_FILES["CUSTOM_FILES"]["name"][$key])) {
        return null;
    }

    return array(
        "name" => $_FILES["CUSTOM_FILES"]["name"][$key],
        "type" => $_FILES["CUSTOM_FILES"]["type"][$key],
        "tmp_name" => $_FILES["CUSTOM_FILES"]["tmp_name"][$key],
        "error" => $_FILES["CUSTOM_FILES"]["error"][$key],
        "size" => $_FILES["CUSTOM_FILES"]["size"][$key],
    );
}

function custom_form_file_id_from_answer($answerValue)
{
    $query = parse_url((string)$answerValue, PHP_URL_QUERY);
    if (!$query) {
        return null;
    }

    parse_str($query, $params);
    return !empty($params["id"]) ? (int)$params["id"] : null;
}

function custom_form_save_answers($pdo, $fields, $applicationId, $studentId, $scholarshipId, $providerId)
{
    if (empty($fields)) {
        return array();
    }
    if (!custom_form_table_exists($pdo, "application_custom_answers")) {
        throw new RuntimeException("Custom form migration has not been applied.");
    }

    $values = custom_form_answer_values_from_post();
    $deleteFileFields = isset($_POST["DELETE_CUSTOM_FILES"]) && is_array($_POST["DELETE_CUSTOM_FILES"])
        ? array_fill_keys(array_map("intval", $_POST["DELETE_CUSTOM_FILES"]), true)
        : array();
    $hasFileId = custom_form_column_exists($pdo, "application_custom_answers", "file_id");
    $existingColumns = $hasFileId
        ? "id, field_id, answer_value, file_id"
        : "id, field_id, answer_value";
    $existingStmt = $pdo->prepare("
        SELECT " . $existingColumns . "
        FROM application_custom_answers
        WHERE application_id = ?
    ");
    $existingStmt->execute(array((int)$applicationId));

    $existing = array();
    foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[(int)$row["field_id"]] = $row;
    }
    $replacedFileIds = array();

    if ($hasFileId) {
        $insertAnswer = $pdo->prepare("
            INSERT INTO application_custom_answers
                (application_id, field_id, answer_value, file_id)
            VALUES (?, ?, ?, ?)
        ");
        $updateAnswer = $pdo->prepare("
            UPDATE application_custom_answers
            SET answer_value = ?, file_id = ?
            WHERE id = ?
        ");
    } else {
        $insertAnswer = $pdo->prepare("
            INSERT INTO application_custom_answers
                (application_id, field_id, answer_value)
            VALUES (?, ?, ?)
        ");
        $updateAnswer = $pdo->prepare("
            UPDATE application_custom_answers
            SET answer_value = ?
            WHERE id = ?
        ");
    }

    foreach ($fields as $field) {
        $fieldId = (int)$field["id"];
        $type = $field["field_type"];
        $required = !empty($field["is_required"]);
        $answerValue = isset($values[$fieldId]) ? trim((string)$values[$fieldId]) : "";
        $fileId = null;

        if ($type === "file") {
            $upload = custom_form_uploaded_file($fieldId);
            $hasUpload = $upload && (int)$upload["error"] !== UPLOAD_ERR_NO_FILE;

            if ($hasUpload) {
                $oldFileId = null;
                if (isset($existing[$fieldId])) {
                    $oldFileId = !empty($existing[$fieldId]["file_id"])
                        ? (int)$existing[$fieldId]["file_id"]
                        : custom_form_file_id_from_answer($existing[$fieldId]["answer_value"]);
                }
                $saved = store_uploaded_file($pdo, $upload, 2, $studentId, array(
                    "application_id" => $applicationId,
                    "scholarship_id" => $scholarshipId,
                    "scholarship_provider_id" => $providerId,
                    "file_subtype" => "custom",
                    "allowed_ext" => array("pdf", "doc", "docx", "jpg", "jpeg", "png"),
                    "max_size" => 10 * 1024 * 1024,
                ));
                $answerValue = $saved["view_url"];
                $fileId = (int)$saved["id"];
                if ($oldFileId && $oldFileId !== $fileId) {
                    $replacedFileIds[] = $oldFileId;
                }
            } elseif (!empty($deleteFileFields[$fieldId]) && isset($existing[$fieldId])) {
                $oldFileId = !empty($existing[$fieldId]["file_id"])
                    ? (int)$existing[$fieldId]["file_id"]
                    : custom_form_file_id_from_answer($existing[$fieldId]["answer_value"]);
                if ($oldFileId) {
                    $replacedFileIds[] = $oldFileId;
                }
                $answerValue = "";
                $fileId = null;
            } elseif (isset($existing[$fieldId])) {
                $answerValue = (string)$existing[$fieldId]["answer_value"];
                $fileId = !isset($existing[$fieldId]["file_id"]) || $existing[$fieldId]["file_id"] === null
                    ? null
                    : (int)$existing[$fieldId]["file_id"];
            }
        } elseif ($type === "number" && $answerValue !== "" && !is_numeric($answerValue)) {
            throw new InvalidArgumentException($field["field_label"] . "必須是數字。");
        }

        if ($required && $answerValue === "") {
            throw new InvalidArgumentException("請填寫「" . $field["field_label"] . "」。");
        }

        if ($answerValue !== "" || $required || isset($existing[$fieldId])) {
            if (isset($existing[$fieldId])) {
                if ($hasFileId) {
                    $updateAnswer->execute(array($answerValue, $fileId, $existing[$fieldId]["id"]));
                } else {
                    $updateAnswer->execute(array($answerValue, $existing[$fieldId]["id"]));
                }
            } elseif ($hasFileId) {
                $insertAnswer->execute(array($applicationId, $fieldId, $answerValue, $fileId));
            } else {
                $insertAnswer->execute(array($applicationId, $fieldId, $answerValue));
            }
        }
    }

    return array_values(array_unique(array_map("intval", $replacedFileIds)));
}
