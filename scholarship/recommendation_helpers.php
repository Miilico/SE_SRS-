<?php
require_once __DIR__ . "/file_helpers.php";
require_once __DIR__ . "/mail_helpers.php";

if (!defined("TAR_AUTO_REJECT_DAYS")) {
    define("TAR_AUTO_REJECT_DAYS", 14);
}

function tar_auto_reject_deadline_sql()
{
    return max(1, (int)TAR_AUTO_REJECT_DAYS);
}

function tar_recommendation_status_label($row)
{
    $status = isset($row["status"]) ? (string)$row["status"] : "";
    if ($status === "submitted" || !empty($row["content"])) {
        return "已提交";
    }
    if ($status === "rejected") {
        return "已駁回";
    }
    if ($status === "draft" || !empty($row["draft_content"])) {
        return "草稿";
    }
    return "待填寫";
}

function tar_recommendation_notify_student($row, $subject, $lines)
{
    if (empty($row["student_email"])) {
        return false;
    }

    return scholarship_send_mail(
        $row["student_email"],
        isset($row["student_name"]) ? $row["student_name"] : "",
        $subject,
        scholarship_mail_html($lines)
    );
}

function tar_auto_reject_overdue_recommendations($pdo)
{
    ensure_application_files_table($pdo);
    $days = tar_auto_reject_deadline_sql();

    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.teacher_name,
            r.created_at,
            a.SCNAME,
            stu.NAME AS student_name,
            stu.EMAIL AS student_email
        FROM recommendations r
        JOIN application a ON r.application_id = a.APNO
        JOIN users stu ON a.STID = stu.ID
        WHERE COALESCE(r.status, 'pending') IN ('pending', 'draft')
          AND (r.content IS NULL OR r.content = '')
          AND r.rejected_at IS NULL
          AND r.created_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)
        LIMIT 50
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $reason = "超過 " . $days . " 天未撰寫推薦信，系統自動駁回。";

        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare("
                UPDATE recommendations
                SET status = 'rejected',
                    rejected_reason = ?,
                    rejected_source = 'system',
                    rejected_at = NOW()
                WHERE id = ?
                  AND COALESCE(status, 'pending') IN ('pending', 'draft')
                  AND (content IS NULL OR content = '')
                  AND rejected_at IS NULL
            ");
            $update->execute(array($reason, $row["id"]));

            if ($update->rowCount() > 0) {
                $sent = tar_recommendation_notify_student(
                    $row,
                    "推薦信撰寫請求已被駁回 - " . $row["SCNAME"],
                    array(
                        htmlspecialchars($row["student_name"], ENT_QUOTES, "UTF-8") . " 您好：",
                        "",
                        "您申請 <strong>" . htmlspecialchars($row["SCNAME"], ENT_QUOTES, "UTF-8") . "</strong> 的推薦信請求已被系統自動駁回。",
                        "原因：" . htmlspecialchars($reason, ENT_QUOTES, "UTF-8"),
                        "請至獎助學金系統查看申請狀態。"
                    )
                );

                if (scholarship_mail_is_configured() && !$sent) {
                    throw new RuntimeException("Email 通知寄送失敗，已取消自動駁回。");
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Auto reject recommendation failed: " . $e->getMessage());
        }
    }
}
