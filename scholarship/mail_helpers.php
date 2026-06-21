<?php

require_once __DIR__ . "/student/PHPMailer.php";
require_once __DIR__ . "/student/SMTP.php";
require_once __DIR__ . "/student/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

if (!defined("SCHOLARSHIP_SMTP_HOST")) {
    define("SCHOLARSHIP_SMTP_HOST", "");
}
if (!defined("SCHOLARSHIP_SMTP_PORT")) {
    define("SCHOLARSHIP_SMTP_PORT", 587);
}
if (!defined("SCHOLARSHIP_SMTP_USERNAME")) {
    define("SCHOLARSHIP_SMTP_USERNAME", "");
}
if (!defined("SCHOLARSHIP_SMTP_PASSWORD")) {
    define("SCHOLARSHIP_SMTP_PASSWORD", "");
}
if (!defined("SCHOLARSHIP_SMTP_FROM_EMAIL")) {
    define("SCHOLARSHIP_SMTP_FROM_EMAIL", "");
}
if (!defined("SCHOLARSHIP_SMTP_FROM_NAME")) {
    define("SCHOLARSHIP_SMTP_FROM_NAME", "Scholarship System");
}
if (!defined("SCHOLARSHIP_SMTP_SECURE")) {
    define("SCHOLARSHIP_SMTP_SECURE", "tls");
}
if (!defined("SCHOLARSHIP_BASE_URL")) {
    define("SCHOLARSHIP_BASE_URL", "");
}

function scholarship_public_url($path)
{
    $baseUrl = rtrim((string)SCHOLARSHIP_BASE_URL, "/");
    if ($baseUrl === "") {
        $https = !empty($_SERVER["HTTPS"]) && strtolower((string)$_SERVER["HTTPS"]) !== "off";
        $scheme = $https ? "https" : "http";
        $host = isset($_SERVER["HTTP_HOST"]) ? (string)$_SERVER["HTTP_HOST"] : "127.0.0.1";
        if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
            $host = "127.0.0.1";
        }
        $baseUrl = $scheme . "://" . $host . "/scholarship";
    }

    return $baseUrl . "/" . ltrim((string)$path, "/");
}

function scholarship_mail_is_configured()
{
    return SCHOLARSHIP_SMTP_HOST !== ""
        && SCHOLARSHIP_SMTP_USERNAME !== ""
        && SCHOLARSHIP_SMTP_PASSWORD !== ""
        && SCHOLARSHIP_SMTP_FROM_EMAIL !== "";
}

function scholarship_send_mail($toEmail, $toName, $subject, $htmlBody)
{
    if ($toEmail === "" || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if (!scholarship_mail_is_configured()) {
        error_log("Scholarship mail skipped: SMTP is not configured.");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = SCHOLARSHIP_SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SCHOLARSHIP_SMTP_USERNAME;
        $mail->Password = SCHOLARSHIP_SMTP_PASSWORD;
        $mail->SMTPSecure = SCHOLARSHIP_SMTP_SECURE;
        $mail->Port = (int)SCHOLARSHIP_SMTP_PORT;
        $mail->CharSet = "UTF-8";
        $mail->Encoding = "base64";

        $mail->setFrom(SCHOLARSHIP_SMTP_FROM_EMAIL, SCHOLARSHIP_SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;

        $mail->send();
        return true;
    } catch (MailException $e) {
        error_log("Scholarship mail failed: " . $mail->ErrorInfo);
        return false;
    }
}

function scholarship_mail_html($lines)
{
    $html = array();
    foreach ($lines as $line) {
        $html[] = $line === "" ? "<br>" : $line;
    }
    return implode("<br>", $html);
}
