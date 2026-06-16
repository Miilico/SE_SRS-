<?php
require_once __DIR__ . "/config.php";

// 沒登入 → 去 login
if (empty($_SESSION["user"])) {
  header("Location: /scholarship/login.php");
  exit;
}

//$role = $_SESSION["user"]["role"] ?? null;
$role = isset($_SESSION["user"]["role"]) ? $_SESSION["user"]["role"] : null;

// 角色導向
if ($role == 1) {
  header("Location: /scholarship/student/student-dashboard.php");
  exit;
}
if ($role == 2) {
  header("Location: /scholarship/professor/tea_dashboard.php");
  exit;
}
if ($role == 3) {
  header("Location: /scholarship/admin/admin_dashboard.php");
  exit;
}
if ($role == 4) {
  header("Location: /scholarship/organization/org-dashboard.php");
  exit;
}

// 角色不明 → 登出
header("Location: /scholarship/logout.php");
exit;
