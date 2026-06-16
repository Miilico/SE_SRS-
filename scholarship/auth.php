<?php
// auth.php
//declare(strict_types=1);

/*function require_login(): void {
  if (empty($_SESSION["user"])) {
    header("Location: /scholarship/login.php?msg=" . urlencode("請先登入"));
    exit;
  }
}*/

function require_login() {
  if (empty($_SESSION["user"])) {
    header("Location: /scholarship/login.php?msg=" . urlencode("請先登入"));
    exit;
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

    if ((int)(isset($u["role"]) ? $u["role"] : 0) !== (int)$role) {
        http_response_code(403);
        exit("Forbidden: role required");
    }
}