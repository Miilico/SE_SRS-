<?php
if (!defined("ADMIN_HEADER_BOOTSTRAPPED")) {
    require_once __DIR__ . "/../config.php";
    require_once __DIR__ . "/../auth.php";

    require_role(3);
    define("ADMIN_HEADER_BOOTSTRAPPED", true);
}

if (!empty($adminHeaderBootstrapOnly)) {
    return;
}

if (!empty($GLOBALS["adminHeaderRendered"])) {
    return;
}

$GLOBALS["adminHeaderRendered"] = true;

$pageTitle = isset($pageTitle) ? $pageTitle : "管理員後台";
$activeNav = isset($activeNav) ? $activeNav : basename($_SERVER["SCRIPT_NAME"]);
$adminMaxWidth = isset($adminMaxWidth) ? $adminMaxWidth : "1120px";
$userName = isset($_SESSION["user"]["name"]) ? $_SESSION["user"]["name"] : "管理員";

$navItems = [
    "admin_dashboard.php" => "總覽",
    "admin_users_pending.php" => "帳號審核",
    "account_management.php" => "帳號管理",
    "post_management.php" => "公告管理",
    "app_management.php" => "申請管理",
];
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle); ?>｜管理員後台</title>
    <style>
        :root {
            --admin-bg: #f5f7fb;
            --admin-surface: #ffffff;
            --admin-border: #d9e2ef;
            --admin-text: #172033;
            --admin-muted: #64748b;
            --admin-primary: #2563eb;
            --admin-primary-dark: #1d4ed8;
            --admin-success: #16a34a;
            --admin-danger: #dc2626;
            --admin-warning: #f59e0b;
            --admin-shadow: 0 12px 30px rgba(15, 23, 42, .08);
            --admin-radius: 8px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--admin-bg);
            color: var(--admin-text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans TC", "Microsoft JhengHei", sans-serif;
            font-size: 15px;
            line-height: 1.5;
        }

        a {
            color: var(--admin-primary);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .admin-topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: rgba(255, 255, 255, .96);
            border-bottom: 1px solid var(--admin-border);
            backdrop-filter: blur(10px);
        }

        .admin-topbar-inner {
            max-width: <?php echo htmlspecialchars($adminMaxWidth); ?>;
            margin: 0 auto;
            padding: 14px 24px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 20px;
            align-items: center;
        }

        .admin-brand {
            font-weight: 800;
            color: var(--admin-text);
            white-space: nowrap;
        }

        .admin-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }

        .admin-nav a,
        .admin-userbar a {
            border-radius: 999px;
            color: #334155;
            font-size: 14px;
            padding: 7px 10px;
        }

        .admin-nav a.active {
            background: #eaf1ff;
            color: var(--admin-primary-dark);
            font-weight: 700;
        }

        .admin-userbar {
            display: flex;
            gap: 6px;
            align-items: center;
            color: var(--admin-muted);
            font-size: 14px;
            white-space: nowrap;
        }

        .admin-shell {
            max-width: <?php echo htmlspecialchars($adminMaxWidth); ?>;
            margin: 0 auto;
            padding: 28px 24px 44px;
        }

        .admin-page-head {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 18px;
        }

        .admin-page-title,
        .admin-title {
            margin: 0;
            color: var(--admin-text);
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .admin-page-subtitle,
        .muted,
        .text-muted {
            color: var(--admin-muted);
        }

        .admin-page-subtitle {
            margin-top: 6px;
        }

        .admin-form-lead {
            margin-bottom: 18px;
        }

        .admin-tight-gap {
            margin-top: 6px;
        }

        .admin-section-gap {
            margin-top: 14px;
        }

        .admin-note {
            margin-top: 20px;
            color: var(--admin-muted);
            text-align: center;
        }

        .admin-card,
        .card,
        .form-box,
        .form-container,
        .post-container,
        .admin-container {
            width: 100%;
            margin: 0;
            padding: 24px;
            background: var(--admin-surface);
            border: 1px solid var(--admin-border);
            border-radius: var(--admin-radius);
            box-shadow: var(--admin-shadow);
        }

        .form-box {
            max-width: 720px;
            margin: 0 auto;
        }

        .form-container {
            max-width: 560px;
            margin: 0 auto;
        }

        .post-container {
            max-width: 780px;
            margin: 0 auto;
        }

        .card-body {
            padding: 0;
        }

        .admin-actions,
        .nav-container,
        .btn-group,
        .nav-bar {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            gap: 10px;
            align-items: center;
            margin: 0 0 18px;
            text-align: left;
        }

        .admin-actions-bottom {
            margin-top: 22px;
            margin-bottom: 0;
        }

        .btn,
        .admin-btn,
        .btn-action,
        .save-btn,
        button.btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 8px 14px;
            border: 0;
            border-radius: 6px;
            background: var(--admin-primary);
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
        }

        .btn:hover,
        .admin-btn:hover,
        .btn-action:hover,
        .save-btn:hover {
            text-decoration: none;
            filter: brightness(.96);
        }

        .btn-sm {
            min-height: 30px;
            padding: 5px 10px;
            font-size: 13px;
        }

        .btn-secondary {
            background: #64748b;
        }

        .btn-success,
        .btn-add,
        .btn-pass,
        .bg-success {
            background: var(--admin-success);
        }

        .btn-danger,
        .btn-del,
        .btn-fail,
        .bg-danger {
            background: var(--admin-danger);
        }

        .btn-edit,
        .btn-primary {
            background: var(--admin-primary);
        }

        .admin-table-wrap,
        .table-responsive {
            overflow-x: auto;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 10px 0 16px;
        }

        .filter-btn {
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

        .filter-btn:hover {
            border-color: var(--admin-primary);
            color: var(--admin-primary-dark);
        }

        .filter-btn.active {
            background: var(--admin-primary);
            color: #fff;
            border-color: var(--admin-primary);
        }

        .filter-count {
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

        .filter-btn.active .filter-count {
            background: rgba(255, 255, 255, .22);
            color: #fff;
        }

        .status-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 18px;
            border-bottom: 1px solid var(--admin-border);
        }

        .tab {
            display: inline-flex;
            padding: 10px 14px;
            border-bottom: 3px solid transparent;
            color: var(--admin-muted);
            font-weight: 800;
        }

        .tab.active {
            border-bottom-color: var(--admin-primary);
            color: var(--admin-primary-dark);
        }

        table,
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            background: var(--admin-surface);
        }

        th,
        td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--admin-border);
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-size: 13px;
            font-weight: 800;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        .text-end {
            text-align: right;
        }

        .d-inline {
            display: inline;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: #475569;
            font-weight: 700;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        textarea,
        select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--admin-border);
            border-radius: 6px;
            background: #fff;
            color: var(--admin-text);
            font: inherit;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: var(--admin-primary);
            outline: 3px solid rgba(37, 99, 235, .14);
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-container input {
            margin-bottom: 12px;
        }

        .sub-title {
            margin: 20px 0 12px;
            padding: 8px 10px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #475569;
            font-size: 14px;
            font-weight: 800;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
        }

        .bg-info {
            background: #0891b2;
        }

        .hint {
            margin-top: 6px;
            color: var(--admin-danger);
            font-size: 13px;
        }

        .grid {
            display: grid;
            gap: 14px;
        }

        .kpi {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .post-title {
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--admin-border);
            font-size: 28px;
            font-weight: 800;
        }

        .post-meta {
            margin-bottom: 22px;
            color: var(--admin-muted);
            font-size: 14px;
        }

        .post-content {
            white-space: pre-wrap;
            font-size: 17px;
        }

        .announcement-title-link {
            color: var(--admin-text);
            font-weight: 800;
        }

        @media (max-width: 900px) {
            .admin-topbar-inner {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .admin-userbar {
                white-space: normal;
            }

            .admin-page-head {
                display: block;
            }

            .kpi {
                grid-template-columns: 1fr;
            }

            .app-status-filters .filter-btn {
                flex: 1 1 140px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
<header class="admin-topbar">
    <div class="admin-topbar-inner">
        <a class="admin-brand" href="/scholarship/admin/admin_dashboard.php">獎助學金系統</a>
        <nav class="admin-nav" aria-label="管理員導覽">
            <?php foreach ($navItems as $href => $label): ?>
                <a class="<?php echo $activeNav === $href ? "active" : ""; ?>" href="/scholarship/admin/<?php echo $href; ?>">
                    <?php echo htmlspecialchars($label); ?>
                </a>
            <?php endforeach; ?>
            <a href="/scholarship/profile.php">個人檔案</a>
            <a href="/scholarship/ticket_list.php">回報問題</a>
        </nav>
        <div class="admin-userbar">
            <span><?php echo htmlspecialchars($userName); ?></span>
            <span>|</span>
            <a href="/scholarship/logout.php">登出</a>
        </div>
    </div>
</header>
<main class="admin-shell">
