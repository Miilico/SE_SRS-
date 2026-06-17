<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/recommendation_helpers.php";

header("Content-Type: text/plain; charset=UTF-8");

ensure_application_files_table($pdo);
tar_auto_reject_overdue_recommendations($pdo);

echo "TAR auto reject check completed at " . date("Y-m-d H:i:s") . PHP_EOL;
