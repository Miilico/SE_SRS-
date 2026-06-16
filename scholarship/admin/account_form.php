<?php
$adminHeaderBootstrapOnly = true;
require __DIR__ . "/header.php";
unset($adminHeaderBootstrapOnly);

function account_form_role_name($role) {
    switch ((int)$role) {
        case 1:
            return "學生";
        case 2:
            return "教師";
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
    "CONTACT" => "",
];
$orgPhones = "";
$mode = "add";

if ($id !== "") {
    $stmt = $pdo->prepare("SELECT u.ID, u.NAME, u.ROLE, u.TEL, u.EMAIL, u.status,
                                  s.SID, s.DNAME AS STUDENT_DEPT,
                                  t.DNAME AS TEACHER_DEPT,
                                  o.CONTACT
                           FROM users u
                           LEFT JOIN students s ON u.ID = s.ID
                           LEFT JOIN teachers t ON u.ID = t.ID
                           LEFT JOIN organization o ON u.ID = o.ID
                           WHERE u.ID = ? AND u.ROLE <> 3");
    $stmt->execute([$id]);
    $found = $stmt->fetch();

    if (!$found) {
        header("Location: account_management.php?msg=" . urlencode("修改的帳號不存在或不可管理"));
        exit;
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
<?php require __DIR__ . "/header.php"; ?>

    <div class="form-container">
        <h1 class="admin-page-title"><?php echo ($mode === "add") ? "新增帳號" : "修改帳號"; ?></h1>
        <div class="admin-page-subtitle admin-form-lead">建立或更新學生、教師與獎助單位帳號資料。</div>
        <form action="account_process.php" method="post">
            <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">
            <?php if ($mode === "edit"): ?>
                <input type="hidden" name="role" value="<?php echo htmlspecialchars($account["ROLE"]); ?>">
            <?php endif; ?>

            <div class="sub-title">基本帳號資訊</div>
            <label>帳號 ID:</label>
            <input type="text" name="id" value="<?php echo htmlspecialchars($account["ID"]); ?>" <?php echo ($mode === "edit") ? "readonly" : "required"; ?>>

            <label>身分:</label>
            <?php if ($mode === "add"): ?>
                <select name="role" id="accountRole" required>
                    <option value="1" <?php echo ((int)$account["ROLE"] === 1) ? "selected" : ""; ?>>學生</option>
                    <option value="2" <?php echo ((int)$account["ROLE"] === 2) ? "selected" : ""; ?>>教師</option>
                    <option value="4" <?php echo ((int)$account["ROLE"] === 4) ? "selected" : ""; ?>>獎助單位</option>
                </select>
            <?php else: ?>
                <input type="text" value="<?php echo htmlspecialchars(account_form_role_name($account["ROLE"])); ?>" readonly>
            <?php endif; ?>

            <label>姓名 / 單位名稱:</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($account["NAME"]); ?>" required>

            <label>登入密碼:</label>
            <input type="password" name="pwd" <?php echo ($mode === "add") ? "required" : ""; ?> placeholder="<?php echo ($mode === "edit") ? "不修改請留空" : "至少 6 碼"; ?>">

            <label>電話:</label>
            <input type="text" name="tel" value="<?php echo htmlspecialchars($account["TEL"]); ?>">

            <label>Email:</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($account["EMAIL"]); ?>">

            <label>帳號狀態:</label>
            <select name="status">
                <option value="active" <?php echo ($account["status"] === "active") ? "selected" : ""; ?>>active</option>
                <option value="pending" <?php echo ($account["status"] === "pending") ? "selected" : ""; ?>>pending</option>
            </select>

            <div class="sub-title" data-role-block="1">學生資料</div>
            <div data-role-block="1">
                <label>學號:</label>
                <input type="text" name="sid" value="<?php echo htmlspecialchars($account["SID"]); ?>" placeholder="未填時預設使用帳號 ID">

                <label>就讀系所:</label>
                <input type="text" name="student_dept" value="<?php echo htmlspecialchars($account["STUDENT_DEPT"]); ?>">
            </div>

            <div class="sub-title" data-role-block="2">教師資料</div>
            <div data-role-block="2">
                <label>所屬系所:</label>
                <input type="text" name="teacher_dept" value="<?php echo htmlspecialchars($account["TEACHER_DEPT"]); ?>">
            </div>

            <div class="sub-title" data-role-block="4">獎助單位資料</div>
            <div data-role-block="4">
                <label>單位聯絡人姓名:</label>
                <input type="text" name="contact_person" value="<?php echo htmlspecialchars($account["CONTACT"]); ?>">

                <label>單位電話（可多筆，請用逗號隔開）:</label>
                <input type="text" name="org_phones" value="<?php echo htmlspecialchars($orgPhones); ?>" placeholder="例如: 02-123456, 0912345678">
            </div>

            <div class="admin-actions admin-actions-bottom">
                <button type="submit" class="btn">儲存送出</button>
                <a href="account_management.php">取消返回</a>
            </div>
        </form>
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
