<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../file_helpers.php";
require_once __DIR__ . "/../custom_form_helpers.php";
require_role(1);

function back_to_edit($apno, $message) {
    header(
        "Location: /scholarship/student/edit_application.php?apno=" .
        urlencode($apno) . "&err=" . urlencode($message)
    );
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /scholarship/student/my_applications.php");
    exit;
}

$user = isset($_SESSION["user"]) ? $_SESSION["user"] : array();
$stId = !empty($user["stid"]) ? $user["stid"] : (!empty($user["id"]) ? $user["id"] : "");

$apno = isset($_POST["apno"]) ? (int)$_POST["apno"] : 0;
$grade = isset($_POST["grade"]) ? trim($_POST["grade"]) : "";
$rank = isset($_POST["rank"]) ? trim($_POST["rank"]) : "";
$teacherEmail = isset($_POST["teacher_email"]) ? trim($_POST["teacher_email"]) : "";
$recRel = isset($_POST["rec_rel"]) ? trim($_POST["rec_rel"]) : "";
$csrfToken = isset($_POST["csrf_token"]) ? $_POST["csrf_token"] : "";
$deleteFileIds = isset($_POST["delete_file_ids"]) && is_array($_POST["delete_file_ids"])
    ? $_POST["delete_file_ids"]
    : array();

if (
    empty($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $csrfToken)
) {
    back_to_edit($apno, "表單驗證失敗，請重新操作。");
}

if ($apno <= 0 || $stId === "") {
    back_to_edit($apno, "申請資料不正確。");
}

if ($grade !== "" && (!is_numeric($grade) || $grade < 0 || $grade > 100)) {
    back_to_edit($apno, "成績必須介於 0 到 100。");
}

if (!filter_var($teacherEmail, FILTER_VALIDATE_EMAIL)) {
    back_to_edit($apno, "推薦教師 Email 格式不正確。");
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT RESULT, SCID, OID
        FROM application
        WHERE APNO = :apno AND STID = :stid
        FOR UPDATE
    ");
    $stmt->execute(array(":apno" => $apno, ":stid" => $stId));
    $application = $stmt->fetch();

    if (!$application) {
        throw new RuntimeException("找不到申請資料。");
    }

    if (in_array($application["RESULT"], array("通過", "不通過"), true)) {
        throw new RuntimeException("此申請已審核完成，不可修改。");
    }

    $stmt = $pdo->prepare("
        UPDATE application
        SET GRADE = :grade, RANK = :rank
        WHERE APNO = :apno AND STID = :stid
    ");
    $stmt->execute(array(
        ":grade" => ($grade === "" ? null : $grade),
        ":rank" => ($rank === "" ? null : $rank),
        ":apno" => $apno,
        ":stid" => $stId
    ));

    if (
        !empty($_FILES["AUTOBI_FILE"]) &&
        isset($_FILES["AUTOBI_FILE"]["error"]) &&
        $_FILES["AUTOBI_FILE"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {
        $oldAutobiStmt = $pdo->prepare("
            SELECT id
            FROM application_files
            WHERE COALESCE(application_id, apno) = ?
              AND file_type = 'autobi'
        ");
        $oldAutobiStmt->execute(array($apno));
        $oldAutobiFileIds = $oldAutobiStmt->fetchAll(PDO::FETCH_COLUMN);

        $saved = store_uploaded_file(
            $pdo,
            $_FILES["AUTOBI_FILE"],
            2,
            $stId,
            array(
                "application_id" => $apno,
                "scholarship_id" => $application["SCID"],
                "scholarship_provider_id" => $application["OID"],
                "file_subtype" => "autobi",
                "allowed_ext" => array("pdf", "doc", "docx"),
                "max_size" => 10 * 1024 * 1024
            )
        );

        $stmt = $pdo->prepare("
            UPDATE application
            SET AUTOBI = :path
            WHERE APNO = :apno AND STID = :stid
        ");

        $stmt->execute(array(
            ":path" => $saved["view_url"],
            ":apno" => $apno,
            ":stid" => $stId
        ));

        delete_uploaded_files($pdo, $oldAutobiFileIds, 2, "application_id", $apno);
    }

    $stmt = $pdo->prepare("
        UPDATE recommendations
        SET teacher_email = :email, rec_rel = :rec_rel
        WHERE application_id = :apno
    ");
    $stmt->execute(array(
        ":email" => $teacherEmail,
        ":rec_rel" => ($recRel === "" ? null : $recRel),
        ":apno" => $apno
    ));

    if (
        !empty($_FILES["OTHER_FILES"]) &&
        is_array($_FILES["OTHER_FILES"]["name"])
    ) {
        $count = count($_FILES["OTHER_FILES"]["name"]);

        for ($i = 0; $i < $count; $i++) {
            if ($_FILES["OTHER_FILES"]["error"][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $file = array(
                "name" => $_FILES["OTHER_FILES"]["name"][$i],
                "type" => $_FILES["OTHER_FILES"]["type"][$i],
                "tmp_name" => $_FILES["OTHER_FILES"]["tmp_name"][$i],
                "error" => $_FILES["OTHER_FILES"]["error"][$i],
                "size" => $_FILES["OTHER_FILES"]["size"][$i]
            );

            store_uploaded_file(
                $pdo,
                $file,
                2,
                $stId,
                array(
                    "application_id" => $apno,
                    "scholarship_id" => $application["SCID"],
                    "scholarship_provider_id" => $application["OID"],
                    "file_subtype" => "support",
                    "allowed_ext" => array(
                        "pdf", "doc", "docx",
                        "jpg", "jpeg", "png"
                    ),
                    "max_size" => 10 * 1024 * 1024
                )
            );
        }
    }

    $customFields = custom_form_fields_for_scholarship($pdo, $application["SCID"]);
    custom_form_save_answers(
        $pdo,
        $customFields,
        $apno,
        $stId,
        $application["SCID"],
        $application["OID"]
    );

    $supportFileStmt = $pdo->prepare("
        SELECT id
        FROM application_files
        WHERE COALESCE(application_id, apno) = ?
          AND file_category = 2
          AND file_type = 'support'
    ");
    $supportFileStmt->execute(array($apno));
    $supportFileIds = array_map("intval", $supportFileStmt->fetchAll(PDO::FETCH_COLUMN));
    $requestedDeleteIds = array_map("intval", $deleteFileIds);
    $allowedDeleteIds = array_values(array_intersect($requestedDeleteIds, $supportFileIds));
    delete_uploaded_files($pdo, $allowedDeleteIds, 2, "application_id", $apno);

    $pdo->commit();
    unset($_SESSION["csrf_token"]);

    header(
        "Location: /scholarship/student/application_detail.php?apno=" .
        urlencode($apno) . "&msg=" . urlencode("修改成功。")
    );
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    back_to_edit($apno, $e->getMessage());
}
