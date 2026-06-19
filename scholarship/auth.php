<?php
// auth.php
//declare(strict_types=1);

require_once __DIR__ . "/flash_helpers.php";

function require_login() {
  if (empty($_SESSION["user"])) {
    site_flash_redirect("/scholarship/login.php", "請先登入", "info");
  }
}


/**
 * 使用數字角色：
 * 1 = student
 * 2 = professor
 * 3 = admin
 * 4=獎助學金單位
 */
/*
function require_role(int $role): void {
  require_login();
  $u = $_SESSION["user"] ?? [];

  if ((int)($u["role"] ?? 0) !== $role) {
    http_response_code(403);
    exit("Forbidden: role required");
  }
}
*/
function require_role($role) {
    require_login();
    $u = isset($_SESSION["user"]) ? $_SESSION["user"] : [];

    $roles = is_array($role) ? $role : array($role);
    $currentRole = (int)(isset($u["role"]) ? $u["role"] : 0);

    foreach ($roles as $allowedRole) {
        if ($currentRole === (int)$allowedRole) {
            return;
        }
    }

    http_response_code(403);
    exit("Forbidden: role required");
}
