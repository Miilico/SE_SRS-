<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

require_login();

$target_id = isset($_SESSION["user"]["id"]) ? $_SESSION["user"]["id"] : null;

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function role_name($role) {
    switch ((int)$role) {
        case 1:
            return "學生";
        case 2:
            return "教師";
        case 3:
            return "系統管理員";
        case 4:
            return "獎助單位";
        default:
            return "未知身分";
    }
}

function dashboard_url($role) {
    switch ((int)$role) {
        case 1:
            return "/scholarship/student/student-dashboard.php";
        case 2:
            return "/scholarship/professor/tea_dashboard.php";
        case 3:
            return "/scholarship/admin/admin_dashboard.php";
        default:
            return "/scholarship/organization/org-dashboard.php";
    }
}

$user = null;
$sections = [];
$errorMessage = "";

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE ID = ?");
    $stmt->execute([$target_id]);
    $user = $stmt->fetch();

    if ($user) {
        $role = (int)$user["ROLE"];

        $sections[] = [
            "title" => "基本帳號",
            "items" => [
                "使用者 ID" => $user["ID"],
                "身分" => role_name($role),
                "Email" => $user["EMAIL"],
            ],
        ];

        if ($role === 1) {
            $stmt = $pdo->prepare("SELECT * FROM students WHERE ID = ?");
            $stmt->execute([$target_id]);
            $student = $stmt->fetch();

            $items = [
                "姓名" => $user["NAME"],
                "學號" => $student ? $student["SID"] : "尚未填寫",
                "就讀系所" => $student ? $student["DNAME"] : "尚未填寫",
                "連絡電話" => $user["TEL"],
            ];

            $sections[] = ["title" => "個人資料", "items" => $items];
        } elseif ($role === 2) {
            $stmt = $pdo->prepare("SELECT * FROM teachers WHERE ID = ?");
            $stmt->execute([$target_id]);
            $teacher = $stmt->fetch();

            $items = [
                "姓名" => $user["NAME"],
                "所屬系所" => $teacher ? $teacher["DNAME"] : "尚未填寫",
                "連絡電話" => $user["TEL"],
            ];

            $sections[] = ["title" => "個人資料", "items" => $items];
        } elseif ($role === 3) {
            $sections[] = [
                "title" => "個人資料",
                "items" => [
                    "姓名" => $user["NAME"],
                    "連絡電話" => $user["TEL"],
                ],
            ];
        } elseif ($role === 4) {
            $stmt = $pdo->prepare("SELECT * FROM organization WHERE ID = ?");
            $stmt->execute([$target_id]);
            $organization = $stmt->fetch();

            $stmt = $pdo->prepare("SELECT TEL FROM ophone WHERE ID = ?");
            $stmt->execute([$target_id]);
            $phones = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $phoneText = implode(", ", $phones);

            $items = [
                "單位名稱" => $user["NAME"],
                "聯絡人姓名" => $organization ? $organization["CONTACT"] : "尚未填寫",
                "聯絡電話 (多筆)" => $phoneText !== "" ? $phoneText : "尚未填寫",
            ];

            $sections[] = ["title" => "獎助單位資訊", "items" => $items];
        }
    } else {
        $errorMessage = "找不到該使用者資料，請重新登入。";
    }
} catch (PDOException $e) {
    $errorMessage = "資料庫錯誤：" . $e->getMessage();
}

$dashboardUrl = $user ? dashboard_url($user["ROLE"]) : "/scholarship/login.php";
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>個人資料 - 獎助學金系統</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
                        <div>
                            <h1 class="h3 fw-bold mb-1">個人帳號資訊</h1>
                            <div class="text-secondary">查看您的登入帳號、身分與角色相關資料。</div>
                        </div>
                        <?php if ($user): ?>
                            <span class="badge rounded-pill text-bg-primary align-self-start px-3 py-2">
                                <?php echo h(role_name($user["ROLE"])); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($errorMessage): ?>
                        <div class="alert alert-danger mb-0"><?php echo h($errorMessage); ?></div>
                    <?php else: ?>
                        <?php foreach ($sections as $section): ?>
                            <section class="mb-4">
                                <div class="border-start border-4 border-primary bg-body-tertiary px-3 py-2 fw-bold mb-2">
                                    <?php echo h($section["title"]); ?>
                                </div>

                                <div class="list-group list-group-flush border rounded">
                                    <?php foreach ($section["items"] as $label => $value): ?>
                                        <div class="list-group-item">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-sm-4 text-secondary fw-semibold"><?php echo h($label); ?></div>
                                                <div class="col-sm-8"><?php echo h($value !== "" && $value !== null ? $value : "尚未填寫"); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>

                        <div class="d-flex flex-column flex-sm-row gap-2 pt-2">
                            <a href="profile_edit.php" class="btn btn-primary">修改個人資料</a>
                            <a href="<?php echo h($dashboardUrl); ?>" class="btn btn-outline-secondary">返回首頁</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
