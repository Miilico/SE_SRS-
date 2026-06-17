<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../recommendation_helpers.php";

function redirect_recommendation($token, $params = array())
{
    $query = array_merge(array("token" => $token), $params);
    header("Location: /scholarship/professor/recommendation.php?" . http_build_query($query));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /scholarship/professor/tea_dashboard.php");
    exit;
}

$token = isset($_POST["token"]) ? trim($_POST["token"]) : "";
$reason = isset($_POST["reason"]) ? trim($_POST["reason"]) : "";

if ($token === "") {
    header("Location: /scholarship/professor/tea_dashboard.php?err=" . urlencode("缺少推薦信 token。"));
    exit;
}

if ($reason === "") {
    redirect_recommendation($token, array("err" => "請填寫駁回原因。"));
}

ensure_application_files_table($pdo);
tar_auto_reject_overdue_recommendations($pdo);

$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.content,
        r.status,
        r.teacher_name,
        a.SCNAME,
        stu.NAME AS student_name,
        stu.EMAIL AS student_email
    FROM recommendations r
    JOIN application a ON r.application_id = a.APNO
    JOIN users stu ON a.STID = stu.ID
    WHERE r.token = :token
    LIMIT 1
");
$stmt->execute(array(":token" => $token));
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    redirect_recommendation($token, array("err" => "找不到這筆推薦信請求。"));
}

if ((string)$record["status"] === "submitted" || trim((string)$record["content"]) !== "") {
    redirect_recommendation($token, array("err" => "已提交的推薦信不可駁回。"));
}

if ((string)$record["status"] === "rejected") {
    redirect_recommendation($token, array("rejected" => "1"));
}

try {
    $pdo->beginTransaction();

    $update = $pdo->prepare("
        UPDATE recommendations
        SET status = 'rejected',
            draft_content = NULL,
            rejected_reason = :reason,
            rejected_source = 'teacher',
            rejected_at = NOW()
        WHERE id = :id
          AND COALESCE(status, 'pending') IN ('pending', 'draft')
          AND (content IS NULL OR content = '')
    ");
    $update->execute(array(
        ":reason" => $reason,
        ":id" => $record["id"],
    ));

    if ($update->rowCount() !== 1) {
        throw new RuntimeException("推薦信狀態已變更，請重新整理頁面。");
    }

    $safeStudent = htmlspecialchars($record["student_name"], ENT_QUOTES, "UTF-8");
    $safeScholarship = htmlspecialchars($record["SCNAME"], ENT_QUOTES, "UTF-8");
    $safeReason = htmlspecialchars($reason, ENT_QUOTES, "UTF-8");

    $sent = tar_recommendation_notify_student(
        $record,
        "推薦信撰寫請求已被駁回 - " . $record["SCNAME"],
        array(
            "{$safeStudent} 您好：",
            "",
            "您申請 <strong>{$safeScholarship}</strong> 的推薦信撰寫請求已被導師駁回。",
            "原因：{$safeReason}",
            "您可以回到獎助學金系統查詢申請狀態。"
        )
    );

    if (scholarship_mail_is_configured() && !$sent) {
        throw new RuntimeException("Email 通知寄送失敗，駁回動作尚未完成。");
    }

    $pdo->commit();
    redirect_recommendation($token, array("rejected" => "1"));
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    redirect_recommendation($token, array("err" => "駁回失敗：" . $e->getMessage()));
}
