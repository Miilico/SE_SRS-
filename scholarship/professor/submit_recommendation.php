<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../recommendation_helpers.php";

function back_to_recommendation($token, $params = array())
{
    $message = isset($params["message"]) ? $params["message"] : "";
    $type = isset($params["type"]) ? $params["type"] : "info";
    if ($message !== "") {
        site_flash_add($message, $type);
    }

    header("Location: /scholarship/professor/recommendation.php?token=" . urlencode($token));
    exit;
}

function fail_and_back($message, $token)
{
    if ($token === "") {
        site_flash_redirect("/scholarship/professor/tea_dashboard.php", $message, "danger");
    }

    back_to_recommendation($token, array("message" => $message, "type" => "danger"));
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /scholarship/professor/tea_dashboard.php");
    exit;
}

$token = isset($_POST["token"]) ? trim($_POST["token"]) : "";
$content = isset($_POST["content"]) ? trim($_POST["content"]) : "";
$action = isset($_POST["action"]) ? trim($_POST["action"]) : "submit";

if ($token === "") {
    fail_and_back("缺少推薦信 token。", "");
}

if (!in_array($action, array("save_draft", "submit"), true)) {
    fail_and_back("未知的操作。", $token);
}

ensure_application_files_table($pdo);
tar_auto_reject_overdue_recommendations($pdo);

$stmt = $pdo->prepare("
    SELECT
        r.id AS recommendation_id,
        r.teacher_id,
        r.teacher_name,
        r.content,
        r.draft_content,
        r.status,
        r.expires_at,
        r.rejected_reason,
        a.APNO,
        a.SCID,
        a.SCNAME,
        stu.NAME AS student_name,
        stu.EMAIL AS student_email,
        s.provider_id,
        provider.NAME AS provider_name,
        provider.EMAIL AS provider_email
    FROM recommendations r
    JOIN application a ON r.application_id = a.APNO
    JOIN scholarship s ON a.SCID = s.id
    JOIN users stu ON a.STID = stu.ID
    LEFT JOIN users provider ON s.provider_id = provider.ID
    WHERE r.token = :token
    LIMIT 1
");
$stmt->execute(array(":token" => $token));
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    fail_and_back("找不到這筆推薦信請求。", $token);
}

$status = (string)($record["status"] ?? "pending");
$hasSubmittedContent = trim((string)($record["content"] ?? "")) !== "";

if ($status === "submitted" || $hasSubmittedContent) {
    fail_and_back("已提交的推薦信不可再次編輯。", $token);
}

if ($status === "rejected") {
    fail_and_back("此推薦信請求已駁回，不能再編輯或提交。", $token);
}

if (!empty($record["expires_at"]) && strtotime($record["expires_at"]) < time()) {
    fail_and_back("此推薦信請求已逾期。", $token);
}

if ($action === "save_draft") {
    $update = $pdo->prepare("
        UPDATE recommendations
        SET draft_content = :content,
            status = CASE WHEN :content_check = '' THEN 'pending' ELSE 'draft' END
        WHERE token = :token
          AND COALESCE(status, 'pending') IN ('pending', 'draft')
          AND (content IS NULL OR content = '')
    ");
    $update->execute(array(
        ":content" => $content,
        ":content_check" => $content,
        ":token" => $token,
    ));

    back_to_recommendation($token, array("message" => "草稿已暫存。", "type" => "success"));
}

if ($content === "") {
    fail_and_back("請先填寫推薦信內容。", $token);
}

try {
    $pdo->beginTransaction();

    $update = $pdo->prepare("
        UPDATE recommendations
        SET content = :content,
            draft_content = NULL,
            status = 'submitted',
            submitted_at = NOW()
        WHERE token = :token
          AND COALESCE(status, 'pending') IN ('pending', 'draft')
          AND (content IS NULL OR content = '')
    ");
    $update->execute(array(
        ":content" => $content,
        ":token" => $token,
    ));

    if ($update->rowCount() !== 1) {
        throw new RuntimeException("推薦信狀態已變更，請重新整理頁面。");
    }

    if (!empty($_FILES["RECOMMENDATION_FILE"]) && $_FILES["RECOMMENDATION_FILE"]["error"] !== UPLOAD_ERR_NO_FILE) {
        $uploaderId = empty($record["teacher_id"]) ? "EXTERNAL" : $record["teacher_id"];

        store_uploaded_file($pdo, $_FILES["RECOMMENDATION_FILE"], 4, $uploaderId, array(
            "application_id" => $record["APNO"],
            "scholarship_id" => $record["SCID"],
            "scholarship_provider_id" => $record["provider_id"],
            "recommendation_id" => $record["recommendation_id"],
            "allowed_ext" => array("pdf", "doc", "docx", "jpg", "jpeg", "png")
        ));
    }

    $teacherName = trim((string)$record["teacher_name"]) === "" ? "推薦老師" : $record["teacher_name"];
    $safeTeacher = htmlspecialchars($teacherName, ENT_QUOTES, "UTF-8");
    $safeStudent = htmlspecialchars($record["student_name"], ENT_QUOTES, "UTF-8");
    $safeScholarship = htmlspecialchars($record["SCNAME"], ENT_QUOTES, "UTF-8");

    $studentSent = tar_recommendation_notify_student(
        $record,
        "推薦信已完成 - " . $record["SCNAME"],
        array(
            "{$safeStudent} 您好：",
            "",
            "{$safeTeacher} 已完成 <strong>{$safeScholarship}</strong> 的推薦信。",
            "您可以回到獎助學金系統查詢目前申請狀態。"
        )
    );

    if (scholarship_mail_is_configured() && !$studentSent) {
        throw new RuntimeException("Email 通知寄送失敗，推薦信尚未完成提交。");
    }

    if (!empty($record["provider_email"])) {
        scholarship_send_mail(
            $record["provider_email"],
            $record["provider_name"],
            "申請推薦信已完成 - " . $record["SCNAME"],
            scholarship_mail_html(array(
                "您好：",
                "",
                "學生 <strong>{$safeStudent}</strong> 申請 <strong>{$safeScholarship}</strong> 的推薦信已由 {$safeTeacher} 完成。",
                "請回到系統檢閱申請資料。"
            ))
        );
    }

    $pdo->commit();
    back_to_recommendation($token, array("message" => "推薦信已提交。提交後不可再次編輯。", "type" => "success"));
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail_and_back("推薦信提交失敗：" . $e->getMessage(), $token);
}
