<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../file_helpers.php";
require_once __DIR__ . "/../mail_helpers.php";
require_once __DIR__ . "/../recommendation_helpers.php";
require_role(1);

function back_err($msg)
{
    site_flash_redirect("/scholarship/student/apply.php", $msg, "danger");
}

function selected_post($key, $default = "")
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

function save_application_upload($pdo, $file, $uploaderId, $apno, $scid, $providerId, $fileSubtype, $allowExt)
{
    $saved = store_uploaded_file($pdo, $file, 2, $uploaderId, array(
        "application_id" => $apno,
        "scholarship_id" => $scid,
        "scholarship_provider_id" => $providerId,
        "file_subtype" => $fileSubtype,
        "allowed_ext" => $allowExt
    ));

    return array(
        "original" => $saved["original_name"],
        "path_url" => $saved["view_url"],
        "file_id" => $saved["id"]
    );
}

function build_recommendation_url($token)
{
    $host = isset($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : "127.0.0.1";
    return "http://" . $host . "/scholarship/professor/recommendation.php?token=" . urlencode($token);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    back_err("請使用表單送出申請。");
}

$studentId = isset($_SESSION["user"]["stid"]) ? $_SESSION["user"]["stid"] : $_SESSION["user"]["id"];
$studentName = isset($_SESSION["user"]["name"]) ? $_SESSION["user"]["name"] : "學生";
$scid = isset($_POST["SCID"]) ? (int)$_POST["SCID"] : 0;
$grade = selected_post("GRADE");
$rank = selected_post("RANK");
$teacherId = selected_post("teacher_id");
$recEmail = selected_post("REC_EMAIL");
$recRel = selected_post("REC_REL");

if ($scid <= 0) {
    back_err("請選擇獎助學金。");
}

if ($recEmail !== "" && !filter_var($recEmail, FILTER_VALIDATE_EMAIL)) {
    back_err("推薦教師 Email 格式不正確。");
}

if (empty($_FILES["AUTOBI_FILE"]) || $_FILES["AUTOBI_FILE"]["error"] === UPLOAD_ERR_NO_FILE) {
    back_err("請上傳自傳檔案。");
}

try {
    ensure_application_files_table($pdo);

    $studentStmt = $pdo->prepare("SELECT 1 FROM students WHERE ID = ?");
    $studentStmt->execute(array($studentId));
    if (!$studentStmt->fetchColumn()) {
        back_err("找不到學生資料，請重新登入後再試。");
    }

    $schStmt = $pdo->prepare("SELECT id, provider_id, NAME, AMOUNT FROM scholarship WHERE id = ? LIMIT 1");
    $schStmt->execute(array($scid));
    $scholarship = $schStmt->fetch(PDO::FETCH_ASSOC);
    if (!$scholarship) {
        back_err("獎助學金不存在。");
    }

    $teacherName = "";
    $teacherDept = "";
    if ($teacherId !== "") {
        $teacherStmt = $pdo->prepare("
            SELECT u.ID, u.NAME, u.EMAIL, t.DNAME
            FROM teachers t
            JOIN users u ON t.ID = u.ID
            WHERE t.ID = ?
            LIMIT 1
        ");
        $teacherStmt->execute(array($teacherId));
        $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
        if (!$teacher) {
            back_err("找不到指定的推薦教師。");
        }

        $teacherName = $teacher["NAME"];
        $teacherDept = $teacher["DNAME"];
        if ($recEmail === "") {
            $recEmail = $teacher["EMAIL"];
        }
    }

    if ($teacherId === "" && $recEmail !== "") {
        $teacherName = "推薦教師";
    }

    $pdo->beginTransaction();

    $insertApp = $pdo->prepare("
        INSERT INTO application
            (RANK, APDATE, GRADE, AMOUNT, RESULT, STID, OID, SCID, SCNAME)
        VALUES
            (:rank, CURDATE(), :grade, :amount, '審查中', :stid, :oid, :scid, :scname)
    ");
    $insertApp->execute(array(
        ":rank" => $rank === "" ? null : $rank,
        ":grade" => $grade === "" ? null : $grade,
        ":amount" => $scholarship["AMOUNT"],
        ":stid" => $studentId,
        ":oid" => $scholarship["provider_id"],
        ":scid" => $scholarship["id"],
        ":scname" => $scholarship["NAME"],
    ));

    $apno = (int)$pdo->lastInsertId();
    if ($apno <= 0) {
        throw new RuntimeException("無法建立申請資料。");
    }

    $autobi = save_application_upload(
        $pdo,
        $_FILES["AUTOBI_FILE"],
        $studentId,
        $apno,
        $scid,
        $scholarship["provider_id"],
        "autobi",
        array("pdf", "doc", "docx")
    );

    $updateAutobi = $pdo->prepare("UPDATE application SET AUTOBI = ? WHERE APNO = ?");
    $updateAutobi->execute(array($autobi["path_url"], $apno));

    if (!empty($_FILES["OTHER_FILES"]) && is_array($_FILES["OTHER_FILES"]["name"])) {
        $names = $_FILES["OTHER_FILES"]["name"];
        for ($i = 0; $i < count($names); $i++) {
            if ($_FILES["OTHER_FILES"]["error"][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $oneFile = array(
                "name" => $_FILES["OTHER_FILES"]["name"][$i],
                "type" => $_FILES["OTHER_FILES"]["type"][$i],
                "tmp_name" => $_FILES["OTHER_FILES"]["tmp_name"][$i],
                "error" => $_FILES["OTHER_FILES"]["error"][$i],
                "size" => $_FILES["OTHER_FILES"]["size"][$i],
            );

            save_application_upload(
                $pdo,
                $oneFile,
                $studentId,
                $apno,
                $scid,
                $scholarship["provider_id"],
                "support",
                array("pdf", "doc", "docx", "jpg", "jpeg", "png")
            );
        }
    }

    $recommendLink = "";
    if ($teacherId !== "" || $recEmail !== "") {
        $token = bin2hex(random_bytes(32));
        $recommendLink = build_recommendation_url($token);

        $insertRec = $pdo->prepare("
            INSERT INTO recommendations
                (content, draft_content, teacher_id, teacher_name, teacher_email, rec_rel, application_id, token, student_name, expires_at, status)
            VALUES
                (NULL, NULL, :teacher_id, :teacher_name, :teacher_email, :rec_rel, :application_id, :token, :student_name, DATE_ADD(NOW(), INTERVAL " . tar_auto_reject_deadline_sql() . " DAY), 'pending')
        ");
        $insertRec->execute(array(
            ":teacher_id" => $teacherId === "" ? null : $teacherId,
            ":teacher_name" => $teacherName === "" ? "推薦教師" : $teacherName,
            ":teacher_email" => $recEmail,
            ":rec_rel" => $recRel === "" ? null : $recRel,
            ":application_id" => $apno,
            ":token" => $token,
            ":student_name" => $studentName,
        ));
    }

    $pdo->commit();

    if ($recommendLink !== "") {
        $safeTeacher = htmlspecialchars($teacherName === "" ? "推薦教師" : $teacherName, ENT_QUOTES, "UTF-8");
        $safeStudent = htmlspecialchars($studentName, ENT_QUOTES, "UTF-8");
        $safeLink = htmlspecialchars($recommendLink, ENT_QUOTES, "UTF-8");
        scholarship_send_mail(
            $recEmail,
            $teacherName === "" ? "推薦教師" : $teacherName,
            "推薦信填寫邀請 - 學生 " . $studentName,
            scholarship_mail_html(array(
                "敬愛的 {$safeTeacher} 老師您好：",
                "",
                "學生 <strong>{$safeStudent}</strong> 邀請您填寫獎助學金推薦信。",
                "請點選以下連結完成推薦：",
                "<a href=\"{$safeLink}\">{$safeLink}</a>",
                "",
                "謝謝您的協助。"
            ))
        );
    }

    site_flash_add("申請已送出，申請編號 APNO={$apno}。", "success");
    if ($recommendLink !== "") {
        $_SESSION["recommend_link"] = $recommendLink;
    }
    header("Location: /scholarship/student/apply.php");
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    back_err("送出失敗：" . $e->getMessage());
}
