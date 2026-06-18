<?php
$adminHeaderBootstrapOnly = true;
require __DIR__ . "/../header.php";
unset($adminHeaderBootstrapOnly);
require_once __DIR__ . "/../file_helpers.php";

ensure_teachers_table($pdo);

function account_form_role_name($role) {
    switch ((int)$role) {
        case 1:
            return "學生";
        case 2:
            return "推薦人";
        case 4:
            return "獎助單位";
        default:
            return "未知身分";
    }
}

$id = isset($_GET["id"]) ? trim($_GET["id"]) : "";
$account = [
    "ID" => "",
    "NAME" => "",
    "ROLE" => 1,
    "TEL" => "",
    "EMAIL" => "",
    "status" => "active",
    "SID" => "",
    "STUDENT_DEPT" => "",
    "TEACHER_DEPT" => "",
    "TEACHER_UNIT" => "",
    "TEACHER_TITLE" => "",
    "CONTACT" => "",
];
$orgPhones = "";
$mode = "add";

if ($id !== "") {
    $stmt = $pdo->prepare("SELECT u.ID, u.NAME, u.ROLE, u.TEL, u.EMAIL, u.status,
                                  s.SID, s.DNAME AS STUDENT_DEPT,
                                  t.DNAME AS TEACHER_DEPT, t.UNIT_NAME AS TEACHER_UNIT, t.JOB_TITLE AS TEACHER_TITLE,
                                  o.CONTACT
                           FROM users u
                           LEFT JOIN students s ON u.ID = s.ID
                           LEFT JOIN teachers t ON u.ID = t.ID
                           LEFT JOIN organization o ON u.ID = o.ID
                           WHERE u.ID = ? AND u.ROLE <> 3");
    $stmt->execute([$id]);
    $found = $stmt->fetch();

    if (!$found) {
        site_flash_redirect("account_management.php", "修改的帳號不存在或不可管理", "warning");
    }

    $account = array_merge($account, $found);
    $mode = "edit";

    if ((int)$account["ROLE"] === 4) {
        $stmtP = $pdo->prepare("SELECT TEL FROM ophone WHERE ID = ?");
        $stmtP->execute([$id]);
        $orgPhones = implode(", ", $stmtP->fetchAll(PDO::FETCH_COLUMN));
    }
}

$pageTitle = ($mode === "add") ? "新增帳號" : "修改帳號";
$activeNav = "account_management.php";
?>
<?php require __DIR__ . "/../header.php"; ?>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
        <h1 class="h3 fw-bold mb-1"><?php echo ($mode === "add") ? "新增帳號" : "修改帳號"; ?></h1>
        <div class="text-secondary mb-4">建立或更新學生、推薦人與獎助單位帳號資料。</div>
        <form action="account_process.php" method="post" class="vstack gap-3">
            <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">
            <?php if ($mode === "edit"): ?>
                <input type="hidden" name="role" value="<?php echo htmlspecialchars($account["ROLE"]); ?>">
            <?php endif; ?>

            <div class="border-start border-4 border-primary bg-body-tertiary px-3 py-2 fw-bold">基本帳號資訊</div>
            <div>
                <label class="form-label fw-semibold">帳號 ID: <?php if ($mode === "add"): ?><span class="text-danger" aria-label="必填">*</span><?php endif; ?></label>
                <input class="form-control" type="text" name="id" value="<?php echo htmlspecialchars($account["ID"]); ?>" <?php echo ($mode === "edit") ? "readonly" : "required"; ?>>
            </div>

            <div>
            <label class="form-label fw-semibold">身分: <?php if ($mode === "add"): ?><span class="text-danger" aria-label="必填">*</span><?php endif; ?></label>
            <?php if ($mode === "add"): ?>
                <select class="form-select" name="role" id="accountRole" required>
                    <option value="1" <?php echo ((int)$account["ROLE"] === 1) ? "selected" : ""; ?>>學生</option>
                    <option value="2" <?php echo ((int)$account["ROLE"] === 2) ? "selected" : ""; ?>>推薦人</option>
                    <option value="4" <?php echo ((int)$account["ROLE"] === 4) ? "selected" : ""; ?>>獎助單位</option>
                </select>
            <?php else: ?>
                <input class="form-control" type="text" value="<?php echo htmlspecialchars(account_form_role_name($account["ROLE"])); ?>" readonly>
            <?php endif; ?>
            </div>

            <div>
                <label class="form-label fw-semibold">姓名 / 單位名稱: <span class="text-danger" aria-label="必填">*</span></label>
                <input class="form-control" type="text" name="name" value="<?php echo htmlspecialchars($account["NAME"]); ?>" required>
            </div>

            <div>
                <label class="form-label fw-semibold">登入密碼: <?php if ($mode === "add"): ?><span class="text-danger" aria-label="必填">*</span><?php endif; ?></label>
                <input class="form-control" type="password" name="pwd" <?php echo ($mode === "add") ? "required" : ""; ?> placeholder="<?php echo ($mode === "edit") ? "不修改請留空" : "至少 6 碼"; ?>">
            </div>

            <div>
                <label class="form-label fw-semibold">電話:</label>
                <input class="form-control" type="text" name="tel" value="<?php echo htmlspecialchars($account["TEL"]); ?>">
            </div>

            <div>
                <label class="form-label fw-semibold">Email:</label>
                <input class="form-control" type="email" name="email" value="<?php echo htmlspecialchars($account["EMAIL"]); ?>">
            </div>

            <div>
            <label class="form-label fw-semibold">帳號狀態:</label>
            <select class="form-select" name="status">
                <option value="active" <?php echo ($account["status"] === "active") ? "selected" : ""; ?>>active</option>
                <option value="pending" <?php echo ($account["status"] === "pending") ? "selected" : ""; ?>>pending</option>
            </select>
            </div>

            <div class="border-start border-4 border-primary bg-body-tertiary px-3 py-2 fw-bold" data-role-block="1">學生資料</div>
            <div data-role-block="1">
                <label class="form-label fw-semibold">學號:</label>
                <input class="form-control" type="text" name="sid" value="<?php echo htmlspecialchars($account["SID"]); ?>" placeholder="未填時預設使用帳號 ID">

                <label class="form-label fw-semibold mt-3">就讀系所: <span class="text-danger" aria-label="必填">*</span></label>
                <input class="form-control" type="text" name="student_dept" value="<?php echo htmlspecialchars($account["STUDENT_DEPT"]); ?>">
            </div>

            <div class="border-start border-4 border-primary bg-body-tertiary px-3 py-2 fw-bold" data-role-block="2">推薦人資料</div>
            <div data-role-block="2">
                <label class="form-label fw-semibold">單位名稱: <span class="text-danger" aria-label="必填">*</span></label>
                <input class="form-control" type="text" name="teacher_unit" value="<?php echo htmlspecialchars($account["TEACHER_UNIT"]); ?>" placeholder="例如：國立成功大學、XX科技股份有限公司">

                <label class="form-label fw-semibold mt-3">職稱: <span class="text-danger" aria-label="必填">*</span></label>
                <input class="form-control" type="text" name="teacher_title" value="<?php echo htmlspecialchars($account["TEACHER_TITLE"]); ?>" placeholder="例如：副教授、講師、高級工程師">

                <label class="form-label fw-semibold mt-3">系所 / 部門:</label>
                <input class="form-control" type="text" name="teacher_dept" value="<?php echo htmlspecialchars($account["TEACHER_DEPT"]); ?>">
            </div>

            <div class="border-start border-4 border-primary bg-body-tertiary px-3 py-2 fw-bold" data-role-block="4">獎助單位資料</div>
            <div data-role-block="4">
                <label class="form-label fw-semibold">單位聯絡人姓名: <span class="text-danger" aria-label="必填">*</span></label>
                <input class="form-control" type="text" name="contact_person" value="<?php echo htmlspecialchars($account["CONTACT"]); ?>">

                <label class="form-label fw-semibold mt-3">單位電話（可多筆，請用逗號隔開）:</label>
                <input class="form-control" type="text" name="org_phones" value="<?php echo htmlspecialchars($orgPhones); ?>" placeholder="例如: 02-123456, 0912345678">
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 pt-2">
                <button type="submit" class="btn btn-primary">儲存送出</button>
                <a href="account_management.php" class="btn btn-outline-secondary">取消返回</a>
            </div>
        </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var roleSelect = document.getElementById("accountRole");
            var currentRole = "<?php echo htmlspecialchars((string)$account["ROLE"]); ?>";
            var blocks = document.querySelectorAll("[data-role-block]");

            function syncRoleBlocks() {
                var role = roleSelect ? roleSelect.value : currentRole;
                blocks.forEach(function (block) {
                    block.style.display = block.getAttribute("data-role-block") === role ? "" : "none";
                });
            }

            if (roleSelect) {
                roleSelect.addEventListener("change", syncRoleBlocks);
            }
            syncRoleBlocks();
        }());
    </script>
</main>
</body>
</html>
