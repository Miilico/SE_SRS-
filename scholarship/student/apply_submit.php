<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../file_helpers.php";
require_once __DIR__ . "/../mail_helpers.php";
require_once __DIR__ . "/../recommendation_helpers.php";
require_once __DIR__ . "/../custom_form_helpers.php";
require_role(1);

function back_err($msg)
{
    $_SESSION["application_old"] = $_POST;
    $scholarshipId = isset($_POST["SCID"]) ? (int)$_POST["SCID"] : 0;
    $target = "/scholarship/student/apply.php";
    if ($scholarshipId > 0) {
        $target .= "?scid=" . urlencode((string)$scholarshipId);
    }
    site_flash_redirect($target . "#application-form", $msg, "danger");
}

function selected_post($key, $default = "")
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    back_err("請使用表單送出申請。");
}

$studentId = isset($_SESSION["user"]["stid"]) ? $_SESSION["user"]["stid"] : $_SESSION["user"]["id"];
$studentIds = array_values(array_unique(array_filter(array(
    isset($_SESSION["user"]["stid"]) ? (string)$_SESSION["user"]["stid"] : "",
    isset($_SESSION["user"]["id"]) ? (string)$_SESSION["user"]["id"] : "",
))));
$studentName = isset($_SESSION["user"]["name"]) ? $_SESSION["user"]["name"] : "學生";
$scid = isset($_POST["SCID"]) ? (int)$_POST["SCID"] : 0;
$grade = selected_post("GRADE");
$rank = selected_post("RANK");
$teacherId = selected_post("teacher_id");
$recName = selected_post("REC_NAME");
$recUnit = selected_post("REC_UNIT");
$recTitle = selected_post("REC_TITLE");
$recEmail = selected_post("REC_EMAIL");
$recRel = selected_post("REC_REL");
$wantsRecommendation = ($teacherId !== "" || $recName !== "" || $recUnit !== "" || $recTitle !== "" || $recEmail !== "" || $recRel !== "");

$csrfToken = isset($_POST["csrf_token"]) ? (string)$_POST["csrf_token"] : "";
if (empty($_SESSION["application_csrf_token"]) || !hash_equals($_SESSION["application_csrf_token"], $csrfToken)) {
    back_err("表單驗證失敗，請重新操作。");
}

if (empty($_POST["agree"])) {
    back_err("請先確認申請資料正確並勾選同意。");
}

if ($scid <= 0) {
    back_err("請選擇獎助學金。");
}

if ($grade !== "" && (!is_numeric($grade) || (float)$grade < 0 || (float)$grade > 100)) {
    back_err("GPA／成績必須是 0 到 100 之間的數字。");
}

if (strlen($rank) > 11) {
    back_err("班排／系排名稱不可超過 11 個字元。");
}

if ($recEmail !== "" && !filter_var($recEmail, FILTER_VALIDATE_EMAIL)) {
    back_err("推薦人 Email 格式不正確。");
}

if ($wantsRecommendation && $teacherId === "" && $recEmail === "") {
    back_err("請填寫推薦人 Email，才能寄送推薦信填寫連結。");
}

if ($wantsRecommendation && $teacherId === "" && $recName === "") {
    back_err("請填寫推薦人姓名。");
}

if ($wantsRecommendation && $teacherId === "" && $recUnit === "") {
    back_err("請填寫推薦人單位名稱。");
}

if ($wantsRecommendation && $teacherId === "" && $recTitle === "") {
    back_err("請填寫推薦人職稱。");
}

try {
    ensure_application_files_table($pdo);
    ensure_teachers_table($pdo);

    $studentStmt = $pdo->prepare("SELECT 1 FROM students WHERE ID = ?");
    $studentStmt->execute(array($studentId));
    if (!$studentStmt->fetchColumn()) {
        back_err("找不到學生資料，請重新登入後再試。");
    }

    $activeScholarshipSql = table_has_column($pdo, "scholarship", "is_active")
        ? " AND is_active = 1"
        : "";
    $schStmt = $pdo->prepare("
        SELECT id, provider_id, NAME, AMOUNT
        FROM scholarship
        WHERE id = ?
          AND start_date <= CURDATE()
          AND DEADLINE >= CURDATE()
          " . $activeScholarshipSql . "
        LIMIT 1
    ");
    $schStmt->execute(array($scid));
    $scholarship = $schStmt->fetch(PDO::FETCH_ASSOC);
    if (!$scholarship) {
        back_err("獎助學金不存在或目前未開放申請。");
    }

    $customFields = custom_form_fields_for_scholarship($pdo, $scid);

    $teacherName = $recName;
    $teacherUnit = $recUnit;
    $teacherTitle = $recTitle;
    if ($teacherId !== "") {
        $teacherStmt = $pdo->prepare("
            SELECT u.ID, u.NAME, u.EMAIL, t.DNAME, t.UNIT_NAME, t.JOB_TITLE
            FROM teachers t
            JOIN users u ON t.ID = u.ID
            WHERE t.ID = ?
            LIMIT 1
        ");
        $teacherStmt->execute(array($teacherId));
        $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
        if (!$teacher) {
            back_err("找不到指定的推薦人。");
        }

        if ($teacherName === "") {
            $teacherName = $teacher["NAME"];
        }
        if ($teacherUnit === "") {
            $teacherUnit = $teacher["UNIT_NAME"] ?: $teacher["DNAME"];
        }
        if ($teacherTitle === "") {
            $teacherTitle = $teacher["JOB_TITLE"];
        }
        if ($recEmail === "") {
            $recEmail = $teacher["EMAIL"];
        }
    }

    if ($wantsRecommendation && $teacherName === "") {
        $teacherName = "推薦人";
    }

    if ($wantsRecommendation && $teacherUnit === "") {
        back_err("請填寫推薦人單位名稱。");
    }

    if ($wantsRecommendation && $teacherTitle === "") {
        back_err("請填寫推薦人職稱。");
    }

    $pdo->beginTransaction();

    $studentPlaceholders = implode(",", array_fill(0, count($studentIds), "?"));
    $duplicateStmt = $pdo->prepare("
        SELECT APNO
        FROM application
        WHERE SCID = ? AND STID IN (" . $studentPlaceholders . ")
        LIMIT 1
        FOR UPDATE
    ");
    $duplicateStmt->execute(array_merge(array($scid), $studentIds));
    if ($duplicateStmt->fetchColumn()) {
        throw new RuntimeException("你已經申請過這項獎學金，不可重複申請。");
    }

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

    custom_form_save_answers(
        $pdo,
        $customFields,
        $apno,
        $studentId,
        $scid,
        $scholarship["provider_id"]
    );

    $recommendLink = "";
    if ($wantsRecommendation) {
        $token = bin2hex(random_bytes(32));
        $recommendLink = scholarship_public_url("/professor/recommendation.php?token=" . urlencode($token));

        $insertRec = $pdo->prepare("
            INSERT INTO recommendations
                (content, draft_content, teacher_id, teacher_name, teacher_email, teacher_unit, teacher_title, rec_rel, application_id, token, student_name, expires_at, status)
            VALUES
                (NULL, NULL, :teacher_id, :teacher_name, :teacher_email, :teacher_unit, :teacher_title, :rec_rel, :application_id, :token, :student_name, DATE_ADD(NOW(), INTERVAL " . tar_auto_reject_deadline_sql() . " DAY), 'pending')
        ");
        $insertRec->execute(array(
            ":teacher_id" => $teacherId === "" ? null : $teacherId,
            ":teacher_name" => $teacherName === "" ? "推薦人" : $teacherName,
            ":teacher_email" => $recEmail,
            ":teacher_unit" => $teacherUnit === "" ? null : $teacherUnit,
            ":teacher_title" => $teacherTitle === "" ? null : $teacherTitle,
            ":rec_rel" => $recRel === "" ? null : $recRel,
            ":application_id" => $apno,
            ":token" => $token,
            ":student_name" => $studentName,
        ));
    }

    $pdo->commit();
    commit_uploaded_request_files();
    unset($_SESSION["application_csrf_token"]);

    $recommendMailSent = null;
    if ($recommendLink !== "") {
        $safeTeacher = htmlspecialchars($teacherName === "" ? "推薦人" : $teacherName, ENT_QUOTES, "UTF-8");
        $safeStudent = htmlspecialchars($studentName, ENT_QUOTES, "UTF-8");
        $safeLink = htmlspecialchars($recommendLink, ENT_QUOTES, "UTF-8");
        $recommendMailSent = scholarship_send_mail(
            $recEmail,
            $teacherName === "" ? "推薦人" : $teacherName,
            "推薦信填寫邀請 - 學生 " . $studentName,
            scholarship_mail_html(array(
                "敬愛的 {$safeTeacher} 您好：",
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
        $_SESSION["recommend_mail_sent"] = (bool)$recommendMailSent;
        if (!$recommendMailSent) {
            site_flash_add("推薦信 Email 未寄出，請手動將推薦連結提供給推薦人。", "warning");
        }
    }
    header("Location: /scholarship/student/apply.php");
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    rollback_uploaded_request_files();
    back_err("送出失敗：" . $e->getMessage());
}
