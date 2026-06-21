<?php
//declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . "/flash_helpers.php";

$pdo = new PDO(
  "mysql:host=localhost;dbname=scholarship;charset=utf8mb4",
  "root",
  "",
  [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]
);

// Optional SMTP settings. Leave empty to skip email sending during local testing.
define("SCHOLARSHIP_SMTP_HOST", "");
define("SCHOLARSHIP_SMTP_PORT", 587);
define("SCHOLARSHIP_SMTP_USERNAME", "");
define("SCHOLARSHIP_SMTP_PASSWORD", "");
define("SCHOLARSHIP_SMTP_FROM_EMAIL", "");
define("SCHOLARSHIP_SMTP_FROM_NAME", "Scholarship System");
define("SCHOLARSHIP_SMTP_SECURE", "tls");
// Public URL used in email links. Example: http://127.0.0.1:8081/scholarship
define("SCHOLARSHIP_BASE_URL", "");
// Optional private directory outside the public web root for fallback data.
define("SCHOLARSHIP_PRIVATE_DATA_DIR", "");

// TAR recommendation request auto-rejection threshold.
define("TAR_AUTO_REJECT_DAYS", 14);
