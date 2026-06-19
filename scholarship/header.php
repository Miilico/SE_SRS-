<?php
if (!defined("SITE_HEADER_FUNCTIONS_LOADED")) {
    define("SITE_HEADER_FUNCTIONS_LOADED", true);

    function site_header_h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
    }

    function site_header_script_path()
    {
        return isset($_SERVER["SCRIPT_NAME"]) ? str_replace("\\", "/", $_SERVER["SCRIPT_NAME"]) : "";
    }

    function site_header_is_admin_request()
    {
        return strpos(site_header_script_path(), "/admin/") !== false;
    }

    function site_header_require_roles($roles)
    {
        require_login();

        if (!is_array($roles)) {
            $roles = array($roles);
        }

        $currentRole = isset($_SESSION["user"]["role"]) ? (int)$_SESSION["user"]["role"] : 0;
        foreach ($roles as $role) {
            if ($currentRole === (int)$role) {
                return;
            }
        }

        http_response_code(403);
        exit("Forbidden: role required");
    }

    function site_header_dashboard_url($role)
    {
        switch ((int)$role) {
            case 1:
                return "/scholarship/student/student-dashboard.php";
            case 2:
                return "/scholarship/professor/tea_dashboard.php";
            case 3:
                return "/scholarship/admin/admin_dashboard.php";
            case 4:
                return "/scholarship/organization/org-dashboard.php";
            default:
                return "/scholarship/login.php";
        }
    }

    function site_header_nav_items($role, $isAdmin)
    {
        if ($isAdmin || (int)$role === 3) {
            return array(
                array("/scholarship/admin/admin_dashboard.php", "總覽"),
                array("/scholarship/admin/admin_users_pending.php", "帳號審核"),
                array("/scholarship/admin/account_management.php", "帳號管理"),
                array("/scholarship/admin/post_management.php", "公告管理"),
                array("/scholarship/admin/document_management.php", "檔案管理"),
                array("/scholarship/admin/app_management.php", "獎助學金申請管理"),
                array("/scholarship/organization/my_scholarships.php", "獎助學金表單管理"),
                array("/scholarship/ticket_list.php", "工單管理"),
                array("/scholarship/profile.php", "個人檔案"),
            );
        }

        if ((int)$role === 1) {
            return array(
                array("/scholarship/student/student-dashboard.php", "總覽"),
                array("/scholarship/student/browse_scholarships.php", "瀏覽獎助學金"),
                array("/scholarship/student/apply.php", "申請獎助學金"),
                array("/scholarship/student/my_applications.php", "我的申請"),
                array("/index.php", "查看公告"),
                array("/scholarship/ticket_list.php", "回報問題"),
                array("/scholarship/profile.php", "個人檔案"),
            );
        }

        if ((int)$role === 2) {
            return array(
                array("/scholarship/professor/tea_dashboard.php", "總覽"),
                array("/scholarship/ticket_list.php", "回報問題"),
                array("/scholarship/profile.php", "個人檔案"),
            );
        }

        if ((int)$role === 4) {
            return array(
                array("/scholarship/organization/org-dashboard.php", "總覽"),
                array("/scholarship/organization/my_scholarships.php", "我的獎助學金"),
                array("/scholarship/organization/view_applicants.php", "申請資料"),
                array("/scholarship/organization/add_scholarship.php", "新增獎助學金"),
                array("/scholarship/ticket_list.php", "回報問題"),
                array("/scholarship/profile.php", "個人檔案"),
            );
        }

        return array(
        );
    }

    function site_header_is_active($activeNav, $href)
    {
        $path = parse_url($href, PHP_URL_PATH);
        $base = basename((string)$path);
        $active = (string)$activeNav;

        return $active === $href || $active === $path || $active === $base;
    }

    function site_status_badge_class($status, $type = "application")
    {
        $status = trim((string)$status);

        if ($type === "recommendation") {
            if ($status === "已提交" || $status === "submitted") {
                return "text-bg-success";
            }
            if ($status === "已駁回" || $status === "rejected") {
                return "text-bg-danger";
            }
            if ($status === "草稿" || $status === "draft") {
                return "text-bg-info";
            }
            return "text-bg-warning";
        }

        if ($type === "scholarship") {
            if ($status === "開放中") {
                return "text-bg-success";
            }
            if ($status === "已截止") {
                return "text-bg-secondary";
            }
            return "text-bg-warning";
        }

        if ($status === "通過" || $status === "active") {
            return "text-bg-success";
        }
        if ($status === "不通過" || $status === "未通過" || $status === "rejected") {
            return "text-bg-danger";
        }
        if ($status === "需補件" || $status === "draft") {
            return "text-bg-info";
        }
        if ($status === "pending" || $status === "審查中") {
            return "text-bg-warning";
        }

        return "text-bg-secondary";
    }

    function site_status_badge($status, $type = "application")
    {
        return '<span class="badge rounded-pill ' . site_header_h(site_status_badge_class($status, $type)) . '">' . site_header_h($status) . '</span>';
    }

    function site_header_default_breadcrumbs($pageTitle, $role)
    {
        $home = site_header_dashboard_url($role);
        $items = array(array($home, "總覽"));

        if ((string)$pageTitle !== "" && (string)$pageTitle !== "總覽") {
            $items[] = array("", (string)$pageTitle);
        }

        return $items;
    }

    function site_header_render_breadcrumbs($breadcrumbs)
    {
        if (empty($breadcrumbs) || !is_array($breadcrumbs)) {
            return;
        }

        echo '<nav class="site-breadcrumb" aria-label="Breadcrumb"><ol class="breadcrumb mb-3">';
        $lastIndex = count($breadcrumbs) - 1;
        foreach ($breadcrumbs as $index => $item) {
            $href = isset($item[0]) ? (string)$item[0] : "";
            $label = isset($item[1]) ? (string)$item[1] : "";
            $isLast = $index === $lastIndex || $href === "";

            if ($isLast) {
                echo '<li class="breadcrumb-item active" aria-current="page">' . site_header_h($label) . '</li>';
            } else {
                echo '<li class="breadcrumb-item"><a href="' . site_header_h($href) . '">' . site_header_h($label) . '</a></li>';
            }
        }
        echo '</ol></nav>';
    }

    function site_header_render_flash_messages()
    {
        if (!function_exists("site_flash_take_all")) {
            return;
        }

        foreach (site_flash_take_all() as $flash) {
            if (!is_array($flash)) {
                continue;
            }

            $message = isset($flash["message"]) ? trim((string)$flash["message"]) : "";
            if ($message === "") {
                continue;
            }

            $type = isset($flash["type"]) ? site_flash_normalize_type($flash["type"]) : "info";
            echo '<div class="alert alert-' . site_header_h($type) . ' alert-dismissible fade show" role="alert">';
            echo site_header_h($message);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="關閉"></button>';
            echo '</div>';
        }
    }
}

$siteHeaderIsAdmin = site_header_is_admin_request();

if (!defined("SITE_HEADER_BOOTSTRAPPED")) {
    require_once __DIR__ . "/config.php";
    require_once __DIR__ . "/auth.php";

    if ($siteHeaderIsAdmin) {
        site_header_require_roles(3);
    } elseif (!empty($siteHeaderRequiredRole)) {
        site_header_require_roles($siteHeaderRequiredRole);
    } elseif (!empty($siteHeaderRequireLogin)) {
        require_login();
    }

    define("SITE_HEADER_BOOTSTRAPPED", true);
}

if (!empty($adminHeaderBootstrapOnly) || !empty($siteHeaderBootstrapOnly)) {
    return;
}

if (!empty($GLOBALS["siteHeaderRendered"])) {
    return;
}

$GLOBALS["siteHeaderRendered"] = true;

$pageTitle = isset($pageTitle) ? $pageTitle : "獎助學金系統";
$activeNav = isset($activeNav) ? $activeNav : basename(site_header_script_path());
$siteHeaderMaxWidth = isset($siteHeaderMaxWidth) ? $siteHeaderMaxWidth : "1120px";
$siteHeaderMainClass = isset($siteHeaderMainClass) ? $siteHeaderMainClass : ($siteHeaderIsAdmin ? "admin-shell" : "site-shell");
$siteHeaderShowNav = isset($siteHeaderShowNav) ? (bool)$siteHeaderShowNav : true;
$siteHeaderBodyClass = isset($siteHeaderBodyClass) ? $siteHeaderBodyClass : "";
$siteHeaderExtraHead = isset($siteHeaderExtraHead) ? $siteHeaderExtraHead : "";
$siteHeaderStylesheets = isset($siteHeaderStylesheets) && is_array($siteHeaderStylesheets) ? $siteHeaderStylesheets : array();

$siteHeaderUser = isset($_SESSION["user"]) ? $_SESSION["user"] : array();
$siteHeaderRole = isset($siteHeaderUser["role"]) ? (int)$siteHeaderUser["role"] : 0;
$siteHeaderUserName = isset($siteHeaderUser["name"]) ? $siteHeaderUser["name"] : "";
if (!isset($userName)) {
    $userName = $siteHeaderUserName !== "" ? $siteHeaderUserName : ($siteHeaderIsAdmin ? "管理員" : "");
}
$siteHeaderBrandHref = isset($siteHeaderBrandHref) ? $siteHeaderBrandHref : site_header_dashboard_url($siteHeaderRole);
$siteHeaderNavItems = isset($siteHeaderNavItems) && is_array($siteHeaderNavItems)
    ? $siteHeaderNavItems
    : site_header_nav_items($siteHeaderRole, $siteHeaderIsAdmin);
$siteHeaderBodyClasses = trim("site-bg " . ($siteHeaderIsAdmin ? "site-admin" : "") . " " . $siteHeaderBodyClass);
?>
<!doctype html>
<html lang="zh-Hant">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo site_header_h($pageTitle); ?>｜獎助學金系統</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php foreach ($siteHeaderStylesheets as $stylesheet): ?>
        <link rel="stylesheet" href="<?php echo site_header_h($stylesheet); ?>">
    <?php endforeach; ?>
    <style>
        html {
            scrollbar-gutter: stable;
        }

        :root {
            --site-bg: #f5f7fb;
            --site-surface: #ffffff;
            --site-border: #d9e2ef;
            --site-text: #172033;
            --site-muted: #64748b;
            --site-primary: #2563eb;
            --site-primary-dark: #1d4ed8;
            --site-success: #16a34a;
            --site-danger: #dc2626;
            --site-warning: #f59e0b;
            --site-radius: 8px;
            --site-shadow: 0 12px 30px rgba(15, 23, 42, .08);
        }

        body.site-bg {
            min-height: 100vh;
            background: var(--site-bg);
            color: var(--site-text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans TC", "Microsoft JhengHei", sans-serif;
            letter-spacing: 0;
        }


        .site-topbar {
            background: rgba(255, 255, 255, .96);
            backdrop-filter: blur(10px);
        }

        .site-topbar .container-fluid {
            max-width: <?php echo site_header_h($siteHeaderMaxWidth); ?>;
            width: 100%;
        }

        .site-topbar .nav-link {
            border-radius: 999px;
            color: #334155;
            font-size: 14px;
            font-weight: 600;
            padding: 7px 10px;
        }

        .site-topbar .nav-link.active {
            background: #eaf1ff;
            color: var(--site-primary-dark);
            font-weight: 800;
        }

        .site-topbar .navbar-brand {
            color: var(--site-text);
        }

        .site-userbar {
            color: var(--site-muted);
            font-size: 14px;
            white-space: nowrap;
        }

        .site-shell,
        .admin-shell {
            max-width: <?php echo site_header_h($siteHeaderMaxWidth); ?>;
            width: 100%;
            margin: 0 auto;
            padding: 28px 24px 44px;
        }

        .site-card {
            background: var(--site-surface);
            border: 1px solid var(--site-border);
            border-radius: var(--site-radius);
            box-shadow: var(--site-shadow);
        }

        .site-breadcrumb .breadcrumb {
            --bs-breadcrumb-divider-color: var(--site-muted);
            color: var(--site-muted);
            font-size: 14px;
        }

        .site-breadcrumb a {
            color: var(--site-primary-dark);
            text-decoration: none;
            font-weight: 700;
        }

        .site-breadcrumb a:hover {
            text-decoration: underline;
        }

        .site-breadcrumb .active {
            color: var(--site-muted);
        }

        @media (max-width: 900px) {

            .site-shell,
            .admin-shell {
                padding: 20px 14px 36px;
            }
        }
    </style>
    <?php echo $siteHeaderExtraHead; ?>
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("submit", function(event) {
            var form = event.target;
            if (!form || !form.matches("form[data-confirm]")) {
                return;
            }

            if (!window.confirm(form.getAttribute("data-confirm"))) {
                event.preventDefault();
            }
        });

        document.addEventListener("click", function(event) {
            var target = event.target.closest("[data-confirm]");
            if (!target || target.tagName === "FORM") {
                return;
            }

            if (!window.confirm(target.getAttribute("data-confirm"))) {
                event.preventDefault();
            }
        });
    </script>
</head>

<body class="<?php echo site_header_h($siteHeaderBodyClasses); ?>">
    <?php if ($siteHeaderShowNav): ?>
        <header class="site-topbar navbar navbar-expand-lg border-bottom sticky-top">
            <div class="container-fluid px-3 px-md-4">
                <a class="navbar-brand fw-bold" href="<?php echo site_header_h($siteHeaderBrandHref); ?>">
                     獎助學金系統
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteHeaderNav" aria-controls="siteHeaderNav" aria-expanded="false" aria-label="切換導覽">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="siteHeaderNav">
                    <nav class="navbar-nav me-auto gap-1" aria-label="主要導覽">
                        <?php foreach ($siteHeaderNavItems as $item): ?>
                            <?php list($href, $label) = $item; ?>
                            <a class="nav-link <?php echo site_header_is_active($activeNav, $href) ? "active" : ""; ?>" href="<?php echo site_header_h($href); ?>">
                                <?php echo site_header_h($label); ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                    <div class="site-userbar d-flex align-items-center gap-2 pt-3 pt-lg-0">
                        <?php if ($siteHeaderUser): ?>
                            <span><?php echo site_header_h($siteHeaderUserName); ?></span>
                            <span>|</span>
                            <a href="/scholarship/logout.php">登出</a>
                        <?php else: ?>
                            <a href="/scholarship/register.php">註冊</a>
|
                            <a href="/scholarship/login.php">登入</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>
    <?php endif; ?>
    <main class="<?php echo site_header_h($siteHeaderMainClass); ?>">
        <?php
        if (!isset($breadcrumbs)) {
            $breadcrumbs = site_header_default_breadcrumbs($pageTitle, $siteHeaderRole);
        }
        site_header_render_breadcrumbs($breadcrumbs);
        if (empty($siteHeaderSuppressFlash)) {
            site_header_render_flash_messages();
        }
        ?>
