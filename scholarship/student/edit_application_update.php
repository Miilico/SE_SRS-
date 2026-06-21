<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../file_helpers.php";
require_once __DIR__ . "/../custom_form_helpers.php";
require_once __DIR__ . "/../application_helpers.php";
require_once __DIR__ . "/../mail_helpers.php";
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

if (
    empty($_SESSION["edit_application_csrf_token"]) ||
    !hash_equals($_SESSION["edit_application_csrf_token"], $csrfToken)
) {
    back_to_edit($apno, "表單驗證失敗，請重新操作。");
}

if ($apno <= 0 || $stId === "") {
    back_to_edit($apno, "申請資料不正確。");
}

if ($grade !== "" && (!is_numeric($grade) || $grade < 0 || $grade > 100)) {
    back_to_edit($apno, "成績必須介於 0 到 100。");
}

if ($teacherEmail !== "" && !filter_var($teacherEmail, FILTER_VALIDATE_EMAIL)) {
    back_to_edit($apno, "推薦教師 Email 格式不正確。");
}

try {
    $recommendationToNotify = null;
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

    if (application_status_is_final($application["RESULT"])) {
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

    $recStmt = $pdo->prepare("
        SELECT id, teacher_name, teacher_email, rec_rel, token, status, content
        FROM recommendations
        WHERE application_id = :apno
        LIMIT 1
        FOR UPDATE
    ");
    $recStmt->execute(array(":apno" => $apno));
    $recommendation = $recStmt->fetch(PDO::FETCH_ASSOC);
    if ($recommendation) {
        $recommendationLocked = $recommendation["status"] === "submitted"
            || $recommendation["status"] === "rejected"
            || trim((string)$recommendation["content"]) !== "";
        $emailChanged = strcasecmp(trim((string)$recommendation["teacher_email"]), $teacherEmail) !== 0;
        $relationChanged = trim((string)$recommendation["rec_rel"]) !== $recRel;

        if ($recommendationLocked && ($emailChanged || $relationChanged)) {
            throw new RuntimeException("推薦信已提交或駁回，不能再修改推薦人資料。");
        }

        if (!$recommendationLocked) {
            $stmt = $pdo->prepare("
                UPDATE recommendations
                SET teacher_email = :email, rec_rel = :rec_rel
                WHERE id = :id
            ");
            $stmt->execute(array(
                ":email" => $teacherEmail,
                ":rec_rel" => ($recRel === "" ? null : $recRel),
                ":id" => $recommendation["id"]
            ));

            if ($emailChanged && $teacherEmail !== "") {
                $recommendationToNotify = array(
                    "email" => $teacherEmail,
                    "name" => (string)$recommendation["teacher_name"],
                    "token" => (string)$recommendation["token"],
                );
            }
        }
    }

    $customFields = custom_form_fields_for_scholarship($pdo, $application["SCID"]);
    $replacedCustomFileIds = custom_form_save_answers(
        $pdo,
        $customFields,
        $apno,
        $stId,
        $application["SCID"],
        $application["OID"]
    );

    $pdo->commit();
    commit_uploaded_request_files();
    unset($_SESSION["edit_application_csrf_token"]);

    foreach ($replacedCustomFileIds as $replacedFileId) {
        try {
            delete_uploaded_file_record($pdo, $replacedFileId);
        } catch (Throwable $cleanupError) {
            error_log("Unable to remove replaced custom file #" . $replacedFileId . ": " . $cleanupError->getMessage());
        }
    }

    if ($recommendationToNotify) {
        $link = scholarship_public_url("/professor/recommendation.php?token=" . urlencode($recommendationToNotify["token"]));
        $safeLink = htmlspecialchars($link, ENT_QUOTES, "UTF-8");
        $mailSent = scholarship_send_mail(
            $recommendationToNotify["email"],
            $recommendationToNotify["name"],
            "獎助學金推薦信填寫通知",
            scholarship_mail_html(array(
                "學生已更新推薦人 Email，請使用以下連結填寫推薦信：",
                "<a href=\"{$safeLink}\">{$safeLink}</a>"
            ))
        );
        if (!$mailSent) {
            site_flash_add("推薦人資料已更新，但 Email 寄送失敗，請手動提供推薦連結。", "warning");
        }
    }

    header(
        "Location: /scholarship/student/application_detail.php?apno=" .
        urlencode($apno) . "&msg=" . urlencode("修改成功。")
    );
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    rollback_uploaded_request_files();

    back_to_edit($apno, $e->getMessage());
}
