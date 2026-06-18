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
                array("/scholarship/admin/document_management.php", "文件管理"),
                array("/scholarship/admin/app_management.php", "申請管理"),
                array("/scholarship/profile.php", "個人檔案"),
                array("/scholarship/ticket_list.php", "回報問題"),
            );
        }

        if ((int)$role === 1) {
            return array(
                array("/scholarship/student/student-dashboard.php", "總覽"),
                array("/scholarship/student/browse_scholarships.php", "瀏覽獎助學金"),
                array("/scholarship/student/apply.php", "申請獎助學金"),
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
            array("/index.php", "最新公告"),
            array("/scholarship/register.php", "註冊"),
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
$siteHeaderMaxWidth = isset($siteHeaderMaxWidth) ? $siteHeaderMaxWidth : ($siteHeaderIsAdmin ? "1120px" : "1140px");
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

        .site-admin a {
            color: var(--site-primary);
            text-decoration: none;
        }

        .site-admin a:hover {
            text-decoration: underline;
        }

        .site-admin .admin-page-head {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 18px;
        }

        .site-admin .admin-page-title,
        .site-admin .admin-title {
            margin: 0;
            color: var(--site-text);
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .site-admin .admin-page-subtitle,
        .site-admin .muted,
        .site-admin .text-muted {
            color: var(--site-muted) !important;
        }

        .site-admin .admin-page-subtitle {
            margin-top: 6px;
        }

        .site-admin .admin-form-lead {
            margin-bottom: 18px;
        }

        .site-admin .admin-tight-gap {
            margin-top: 6px;
        }

        .site-admin .admin-section-gap {
            margin-top: 14px;
        }

        .site-admin .admin-note {
            margin-top: 20px;
            color: var(--site-muted);
            text-align: center;
        }

        .site-admin .admin-card,
        .site-admin .card,
        .site-admin .form-box,
        .site-admin .form-container,
        .site-admin .post-container,
        .site-admin .admin-container {
            width: 100%;
            margin: 0;
            padding: 24px;
            background: var(--site-surface);
            border: 1px solid var(--site-border);
            border-radius: var(--site-radius);
            box-shadow: var(--site-shadow);
        }

        .site-admin .form-box {
            max-width: 720px;
            margin: 0 auto;
        }

        .site-admin .form-container {
            max-width: 560px;
            margin: 0 auto;
        }

        .site-admin .post-container {
            max-width: 780px;
            margin: 0 auto;
        }

        .site-admin .card-body {
            padding: 0;
        }

        .site-admin .admin-actions,
        .site-admin .nav-container,
        .site-admin .btn-group,
        .site-admin .nav-bar {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            gap: 10px;
            align-items: center;
            margin: 0 0 18px;
            text-align: left;
        }

        .site-admin .admin-actions-bottom {
            margin-top: 22px;
            margin-bottom: 0;
        }

        .site-admin .btn,
        .site-admin .admin-btn,
        .site-admin .btn-action,
        .site-admin .save-btn,
        .site-admin button.btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 8px 14px;
            border: 0;
            border-radius: 6px;
            background: var(--site-primary);
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
        }

        .site-admin .btn:hover,
        .site-admin .admin-btn:hover,
        .site-admin .btn-action:hover,
        .site-admin .save-btn:hover {
            text-decoration: none;
            filter: brightness(.96);
        }

        .site-admin .btn-sm {
            min-height: 30px;
            padding: 5px 10px;
            font-size: 13px;
        }

        .site-admin .btn-secondary {
            background: #64748b;
        }

        .site-admin .btn-success,
        .site-admin .btn-add,
        .site-admin .btn-pass,
        .site-admin .bg-success {
            background: var(--site-success) !important;
        }

        .site-admin .btn-danger,
        .site-admin .btn-del,
        .site-admin .btn-fail,
        .site-admin .bg-danger {
            background: var(--site-danger) !important;
        }

        .site-admin .btn-edit,
        .site-admin .btn-primary {
            background: var(--site-primary) !important;
        }

        .site-admin .admin-table-wrap,
        .site-admin .table-responsive {
            overflow-x: auto;
        }

        .site-admin .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 10px 0 16px;
        }

        .site-admin .filter-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 38px;
            padding: 8px 12px;
            border: 1px solid #d0d5dd;
            border-radius: 999px;
            background: #fff;
            color: #344054;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
        }

        .site-admin .filter-btn:hover {
            border-color: var(--site-primary);
            color: var(--site-primary-dark);
        }

        .site-admin .filter-btn.active {
            background: var(--site-primary);
            color: #fff;
            border-color: var(--site-primary);
        }

        .site-admin .filter-count {
            display: inline-flex;
            min-width: 24px;
            height: 22px;
            align-items: center;
            justify-content: center;
            padding: 0 7px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #475569;
            font-size: 12px;
            line-height: 1;
        }

        .site-admin .filter-btn.active .filter-count {
            background: rgba(255, 255, 255, .22);
            color: #fff;
        }

        .site-admin table,
        .site-admin .table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            background: var(--site-surface);
        }

        .site-admin th,
        .site-admin td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--site-border);
            text-align: left;
            vertical-align: middle;
        }

        .site-admin th {
            background: #f8fafc;
            color: #475569;
            font-size: 13px;
            font-weight: 800;
        }

        .site-admin tr:last-child td {
            border-bottom: 0;
        }

        .site-admin .text-end {
            text-align: right;
        }

        .site-admin .d-inline {
            display: inline;
        }

        .site-admin label {
            display: block;
            margin-bottom: 7px;
            color: #475569;
            font-weight: 700;
        }

        .site-admin input[type="text"],
        .site-admin input[type="email"],
        .site-admin input[type="password"],
        .site-admin textarea,
        .site-admin select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--site-border);
            border-radius: 6px;
            background: #fff;
            color: var(--site-text);
            font: inherit;
        }

        .site-admin input:focus,
        .site-admin textarea:focus,
        .site-admin select:focus {
            border-color: var(--site-primary);
            outline: 3px solid rgba(37, 99, 235, .14);
        }

        .site-admin .form-group {
            margin-bottom: 18px;
        }

        .site-admin .form-container input {
            margin-bottom: 12px;
        }

        .site-admin .sub-title {
            margin: 20px 0 12px;
            padding: 8px 10px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #475569;
            font-size: 14px;
            font-weight: 800;
        }

        .site-admin .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
        }

        .site-admin .bg-info {
            background: #0891b2 !important;
        }

        .site-admin .hint {
            margin-top: 6px;
            color: var(--site-danger);
            font-size: 13px;
        }

        .site-admin .grid {
            display: grid;
            gap: 14px;
        }

        .site-admin .kpi {
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        .site-admin .post-title {
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--site-border);
            font-size: 28px;
            font-weight: 800;
        }

        .site-admin .post-meta {
            margin-bottom: 22px;
            color: var(--site-muted);
            font-size: 14px;
        }

        .site-admin .post-content {
            white-space: pre-wrap;
            font-size: 17px;
        }

        .site-admin .announcement-title-link {
            color: var(--site-text);
            font-weight: 800;
        }

        @media (max-width: 900px) {

            .site-shell,
            .admin-shell {
                padding: 20px 14px 36px;
            }

            .site-admin .admin-page-head {
                display: block;
            }

            .site-admin .kpi {
                grid-template-columns: 1fr;
            }

            .site-admin .app-status-filters .filter-btn {
                flex: 1 1 140px;
                justify-content: center;
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
