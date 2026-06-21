<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../mail_helpers.php";

$recipient = isset($argv[1]) ? trim((string)$argv[1]) : "";
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/test_mail.php recipient@gmail.com" . PHP_EOL);
    exit(1);
}

if (!scholarship_mail_is_configured()) {
    fwrite(STDERR, "SMTP is not configured in scholarship/config.php." . PHP_EOL);
    exit(1);
}

$sent = scholarship_send_mail(
    $recipient,
    "SMTP tester",
    "Scholarship System Gmail test",
    scholarship_mail_html(array(
        "This is a test message from Scholarship System.",
        "If you received it, the Gmail SMTP configuration is working."
    ))
);

fwrite($sent ? STDOUT : STDERR, $sent ? "Mail sent successfully." . PHP_EOL : "Mail sending failed. Check the PHP error log." . PHP_EOL);
exit($sent ? 0 : 1);
