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

function custom_form_tables_ready($pdo)
{
    return custom_form_table_exists($pdo, "scholarship_fields")
        && custom_form_table_exists($pdo, "application_custom_answers");
}

function custom_form_fields_for_scholarship($pdo, $scholarshipId)
{
    if (!custom_form_table_exists($pdo, "scholarship_fields")) {
        return array();
    }

    $stmt = $pdo->prepare("
        SELECT id, scholarship_id, field_label, field_type, is_required, sort_order
        FROM scholarship_fields
        WHERE scholarship_id = ?
        ORDER BY sort_order, id
    ");
    $stmt->execute(array((int)$scholarshipId));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function custom_form_replace_fields($pdo, $scholarshipId, $labels, $types, $requiredValues)
{
    if (!custom_form_tables_ready($pdo)) {
        throw new RuntimeException("Custom form migration has not been applied.");
    }

    $labels = is_array($labels) ? $labels : array();
    $types = is_array($types) ? $types : array();
    $requiredValues = is_array($requiredValues) ? $requiredValues : array();

    $pdo->prepare("DELETE FROM scholarship_fields WHERE scholarship_id = ?")
        ->execute(array((int)$scholarshipId));

    $insert = $pdo->prepare("
        INSERT INTO scholarship_fields
            (scholarship_id, field_label, field_type, is_required, sort_order)
        VALUES (?, ?, ?, ?, ?)
    ");

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
        $insert->execute(array((int)$scholarshipId, $label, $type, $isRequired, $index));
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

function custom_form_save_answers($pdo, $fields, $applicationId, $studentId, $scholarshipId, $providerId)
{
    if (empty($fields)) {
        return;
    }
    if (!custom_form_table_exists($pdo, "application_custom_answers")) {
        throw new RuntimeException("Custom form migration has not been applied.");
    }

    $values = custom_form_answer_values_from_post();
    $existingStmt = $pdo->prepare("
        SELECT field_id, answer_value, file_id
        FROM application_custom_answers
        WHERE application_id = ?
    ");
    $existingStmt->execute(array((int)$applicationId));

    $existing = array();
    foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[(int)$row["field_id"]] = $row;
    }

    $upsert = $pdo->prepare("
        INSERT INTO application_custom_answers
            (application_id, field_id, answer_value, file_id)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            answer_value = VALUES(answer_value),
            file_id = VALUES(file_id),
            updated_at = CURRENT_TIMESTAMP
    ");

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
            } elseif (isset($existing[$fieldId])) {
                $answerValue = (string)$existing[$fieldId]["answer_value"];
                $fileId = $existing[$fieldId]["file_id"] === null
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
            $upsert->execute(array($applicationId, $fieldId, $answerValue, $fileId));
        }
    }
}
