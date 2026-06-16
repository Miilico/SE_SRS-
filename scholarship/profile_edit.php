<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

require_login();

$target_id = isset($_SESSION["user"]["id"]) ? $_SESSION["user"]["id"] : null;
$message = "";
$messageType = "success";

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

// 處理表單提交
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $pdo->beginTransaction();

        // 1. 更新 users 基本資料
        $sql1 = "UPDATE users SET NAME = ?, TEL = ?, EMAIL = ? WHERE ID = ?";
        $stmt1 = $pdo->prepare($sql1);
        $stmt1->execute([$_POST["NAME"], $_POST["TEL"], $_POST["EMAIL"], $target_id]);

        // 2. 根據角色更新擴展資料
        $role = $_POST["ROLE"];
        if ($role == 1) {
            $stmt2 = $pdo->prepare("UPDATE students SET SID = ?, DNAME = ? WHERE ID = ?");
            $stmt2->execute([$_POST["SID"], $_POST["DNAME"], $target_id]);
        } elseif ($role == 2) {
            $stmt2 = $pdo->prepare("UPDATE teachers SET DNAME = ? WHERE ID = ?");
            $stmt2->execute([$_POST["DNAME"], $target_id]);
        } elseif ($role == 4) {
            $stmt2 = $pdo->prepare("UPDATE organization SET ONAME = ?, CONTACT = ? WHERE ID = ?");
            $stmt2->execute([$_POST["NAME"], $_POST["CONTACT"], $target_id]);

            $pdo->prepare("DELETE FROM ophone WHERE ID = ?")->execute([$target_id]);
            if (!empty($_POST["ORG_PHONES"])) {
                $phones = explode(",", $_POST["ORG_PHONES"]);
                $stmtP = $pdo->prepare("INSERT INTO ophone (ID, TEL) VALUES (?, ?)");
                foreach ($phones as $phone) {
                    $phone = trim($phone);
                    if ($phone !== "") {
                        $stmtP->execute([$target_id, $phone]);
                    }
                }
            }
        }

        $pdo->commit();
        $message = "資料更新成功！";
        $messageType = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "更新失敗：" . $e->getMessage();
        $messageType = "danger";
    }
}

// 讀取現有資料以供編輯
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE ID = ?");
    $stmt->execute([$target_id]);
    $user = $stmt->fetch();

    if (!$user) {
        header("Location: login.php");
        exit;
    }

    $extra = [];
    $org_phones_str = "";
    $role = (int)$user["ROLE"];

    if ($role === 1) {
        $st = $pdo->prepare("SELECT * FROM students WHERE ID = ?");
        $st->execute([$target_id]);
        $extra = $st->fetch();
    } elseif ($role === 2 || $role === 3) {
        $st = $pdo->prepare("SELECT * FROM teachers WHERE ID = ?");
        $st->execute([$target_id]);
        $extra = $st->fetch();
    } elseif ($role === 4) {
        $st = $pdo->prepare("SELECT * FROM organization WHERE ID = ?");
        $st->execute([$target_id]);
        $extra = $st->fetch();

        $stP = $pdo->prepare("SELECT TEL FROM ophone WHERE ID = ?");
        $stP->execute([$target_id]);
        $org_phones_str = implode(", ", $stP->fetchAll(PDO::FETCH_COLUMN));
    }
} catch (PDOException $e) {
    http_response_code(500);
    exit("資料讀取錯誤");
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>修改個人資料 - 獎助學金系統</title>
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
                            <h1 class="h3 fw-bold mb-1">修改個人資料</h1>
                            <div class="text-secondary">更新您的基本聯絡資料與角色相關資訊。</div>
                        </div>
                        <span class="badge rounded-pill text-bg-primary align-self-start px-3 py-2">
                            <?php echo h(role_name($role)); ?>
                        </span>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo h($messageType); ?>" role="alert">
                            <?php echo h($message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <input type="hidden" name="ROLE" value="<?php echo h($user["ROLE"]); ?>">

                        <section class="mb-4">
                            <div class="border-start border-4 border-primary bg-body-tertiary px-3 py-2 fw-bold mb-3">
                                基本聯絡資料
                            </div>

                            <div class="mb-3">
                                <label for="NAME" class="form-label fw-semibold">單位/姓名</label>
                                <input type="text" id="NAME" name="NAME" class="form-control" value="<?php echo h($user["NAME"]); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="TEL" class="form-label fw-semibold">電話</label>
                                <input type="text" id="TEL" name="TEL" class="form-control" value="<?php echo h($user["TEL"]); ?>">
                            </div>

                            <div class="mb-0">
                                <label for="EMAIL" class="form-label fw-semibold">Email</label>
                                <input type="email" id="EMAIL" name="EMAIL" class="form-control" value="<?php echo h($user["EMAIL"]); ?>">
                            </div>
                        </section>

                        <?php if ($role === 1): ?>
                            <section class="mb-4">
                                <div class="border-start border-4 border-primary bg-body-tertiary px-3 py-2 fw-bold mb-3">
                                    學籍資料
                                </div>

                                <div class="mb-3">
                                    <label for="SID" class="form-label fw-semibold">學號</label>
                                    <input type="text" id="SID" name="SID" class="form-control" value="<?php echo h(isset($extra["SID"]) ? $extra["SID"] : ""); ?>">
                                </div>

                                <div class="mb-0">
                                    <label for="DNAME" class="form-label fw-semibold">就讀系所</label>
                                    <input type="text" id="DNAME" name="DNAME" class="form-control" value="<?php echo h(isset($extra["DNAME"]) ? $extra["DNAME"] : ""); ?>">
                                </div>
                            </section>
                        <?php elseif ($role === 2): ?>
                            <section class="mb-4">
                                <div class="border-start border-4 border-primary bg-body-tertiary px-3 py-2 fw-bold mb-3">
                                    教職資料
                                </div>

                                <div class="mb-0">
                                    <label for="DNAME" class="form-label fw-semibold">所屬系所</label>
                                    <input type="text" id="DNAME" name="DNAME" class="form-control" value="<?php echo h(isset($extra["DNAME"]) ? $extra["DNAME"] : ""); ?>">
                                </div>
                            </section>
                        <?php elseif ($role === 4): ?>
                            <section class="mb-4">
                                <div class="border-start border-4 border-primary bg-body-tertiary px-3 py-2 fw-bold mb-3">
                                    獎助單位資訊
                                </div>

                                <div class="mb-3">
                                    <label for="CONTACT" class="form-label fw-semibold">聯絡人姓名</label>
                                    <input type="text" id="CONTACT" name="CONTACT" class="form-control" value="<?php echo h(isset($extra["CONTACT"]) ? $extra["CONTACT"] : ""); ?>" placeholder="請輸入聯絡人姓名">
                                </div>

                                <div class="mb-0">
                                    <label for="ORG_PHONES" class="form-label fw-semibold">單位電話</label>
                                    <input type="text" id="ORG_PHONES" name="ORG_PHONES" class="form-control" value="<?php echo h($org_phones_str); ?>" placeholder="多筆電話請用逗號隔開">
                                </div>
                            </section>
                        <?php endif; ?>

                        <div class="d-flex flex-column flex-sm-row gap-2 pt-2">
                            <button type="submit" class="btn btn-primary">儲存修改</button>
                            <a href="profile.php" class="btn btn-outline-secondary">取消返回</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
