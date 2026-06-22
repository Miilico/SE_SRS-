<?php

require_once __DIR__ . "/file_helpers.php";

function supplement_note_private_file()
{
    $baseDir = defined("SCHOLARSHIP_PRIVATE_DATA_DIR") && SCHOLARSHIP_PRIVATE_DATA_DIR !== ""
        ? rtrim((string)SCHOLARSHIP_PRIVATE_DATA_DIR, "\\/")
        : dirname(__DIR__) . DIRECTORY_SEPARATOR . "scholarship-private-data";

    if (!@is_dir($baseDir) && !@mkdir($baseDir, 0700, true) && !@is_dir($baseDir)) {
        throw new RuntimeException("無法建立補件原因私有儲存目錄。");
    }

    supplement_note_protect_private_dir($baseDir);

    return $baseDir . DIRECTORY_SEPARATOR . "supplement_notes.json";
}

function supplement_note_protect_private_dir($baseDir)
{
    $htaccess = $baseDir . DIRECTORY_SEPARATOR . ".htaccess";
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
    }

    $index = $baseDir . DIRECTORY_SEPARATOR . "index.html";
    if (!is_file($index)) {
        @file_put_contents($index, "");
    }
}

function supplement_note_file_read_all()
{
    $path = supplement_note_private_file();
    if (!is_file($path)) {
        return array();
    }

    $handle = fopen($path, "rb");
    if (!$handle) {
        throw new RuntimeException("無法讀取補件原因私有檔案。");
    }
    flock($handle, LOCK_SH);
    $json = stream_get_contents($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    $data = json_decode((string)$json, true);
    return is_array($data) ? $data : array();
}

function supplement_note_file_write($applicationId, $note)
{
    $path = supplement_note_private_file();
    $handle = fopen($path, "c+");
    if (!$handle || !flock($handle, LOCK_EX)) {
        throw new RuntimeException("無法寫入補件原因私有檔案。");
    }

    $json = stream_get_contents($handle);
    $data = json_decode((string)$json, true);
    $data = is_array($data) ? $data : array();
    $key = (string)(int)$applicationId;
    $note = trim((string)$note);

    if ($note === "") {
        unset($data[$key]);
    } else {
        $data[$key] = array(
            "note" => $note,
            "updated_at" => date("c"),
        );
    }

    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function supplement_note_get($pdo, $applicationId, $databaseValue = null)
{
    $databaseValue = trim((string)$databaseValue);
    if ($databaseValue !== "") {
        return $databaseValue;
    }

    try {
        $data = supplement_note_file_read_all();
    } catch (Throwable $e) {
        error_log("Unable to read supplement note fallback: " . $e->getMessage());
        return "";
    }

    $key = (string)(int)$applicationId;
    return isset($data[$key]["note"]) ? (string)$data[$key]["note"] : "";
}

function supplement_note_save($pdo, $applicationId, $note)
{
    $note = trim((string)$note);
    if (table_has_column($pdo, "application", "SUPPLEMENT_NOTE")) {
        $stmt = $pdo->prepare("UPDATE application SET SUPPLEMENT_NOTE = ? WHERE APNO = ?");
        $stmt->execute(array($note === "" ? null : $note, (int)$applicationId));
        try {
            supplement_note_file_write($applicationId, "");
        } catch (Throwable $e) {
            error_log("Unable to clear supplement note fallback: " . $e->getMessage());
        }
        return;
    }

    supplement_note_file_write($applicationId, $note);
}
