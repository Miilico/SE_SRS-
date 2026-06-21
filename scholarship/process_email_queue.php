<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/mail_helpers.php";
require_once __DIR__ . "/file_helpers.php";

try {
    $pdo->beginTransaction();

    $hasRetryColumns = table_has_column($pdo, "email_queue", "attempts")
        && table_has_column($pdo, "email_queue", "available_at");
    $where = $hasRetryColumns
        ? "status = 'pending' OR (status = 'failed' AND attempts < 3 AND available_at <= NOW())"
        : "status = 'pending'";

    $stmt = $pdo->query("SELECT * FROM email_queue WHERE " . $where . " ORDER BY id LIMIT 50 FOR UPDATE");
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($emails)) {
        $pdo->commit();
        exit("No queued email." . PHP_EOL);
    }

    $ids = array_column($emails, "id");
    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $updateStmt = $pdo->prepare("UPDATE email_queue SET status = 'processing' WHERE id IN (" . $placeholders . ")");
    $updateStmt->execute($ids);
    $pdo->commit();

    $successStmt = $pdo->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
    $failStmt = $hasRetryColumns
        ? $pdo->prepare("UPDATE email_queue SET status = 'failed', attempts = attempts + 1, available_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE), error_msg = ? WHERE id = ?")
        : $pdo->prepare("UPDATE email_queue SET status = 'failed', error_msg = ? WHERE id = ?");

    $successCount = 0;
    foreach ($emails as $email) {
        $sent = scholarship_send_mail(
            $email["recipient_email"],
            $email["recipient_name"],
            $email["subject"],
            $email["body"]
        );

        if ($sent) {
            $successStmt->execute(array($email["id"]));
            $successCount++;
        } else {
            $failStmt->execute(array("PHPMailer delivery failed", $email["id"]));
        }
    }

    echo "Processed " . count($emails) . " email(s); sent " . $successCount . "." . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Email queue failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
