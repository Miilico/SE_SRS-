<?php
$adminHeaderBootstrapOnly = true;
require __DIR__ . "/../header.php";
unset($adminHeaderBootstrapOnly);

function account_role_name($role)
{
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

$role_filter_options = [
    "all" => "全部",
    "1" => "學生",
    "2" => "教師",
    "4" => "獎助單位",
];
$role_counts = [
    "1" => 0,
    "2" => 0,
    "4" => 0,
];

try {
    $sql = "SELECT u.ID, u.NAME, u.ROLE, u.TEL, u.EMAIL, u.status, u.created_at,
                   s.SID, s.DNAME AS STUDENT_DEPT,
                   t.DNAME AS TEACHER_DEPT,
                   o.ONAME, o.CONTACT,
                   GROUP_CONCAT(p.TEL SEPARATOR ', ') AS ORG_PHONES
            FROM users u
            LEFT JOIN students s ON u.ID = s.ID
            LEFT JOIN teachers t ON u.ID = t.ID
            LEFT JOIN organization o ON u.ID = o.ID
            LEFT JOIN ophone p ON u.ID = p.ID
            WHERE u.ROLE <> 3
            GROUP BY u.ID, u.NAME, u.ROLE, u.TEL, u.EMAIL, u.status, u.created_at,
                     s.SID, s.DNAME, t.DNAME, o.ONAME, o.CONTACT
            ORDER BY u.ROLE ASC, u.ID ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $accounts = $stmt->fetchAll();

    foreach ($accounts as $account) {
        $roleKey = (string)$account["ROLE"];
        if (isset($role_counts[$roleKey])) {
            $role_counts[$roleKey]++;
        }
    }
} catch (PDOException $e) {
    die("查詢失敗：" . $e->getMessage());
}

$pageTitle = "帳號管理";
$activeNav = "account_management.php";
?>
<?php require __DIR__ . "/../header.php"; ?>

<div class="admin-page-head">
    <div>
        <h1 class="admin-page-title">帳號管理</h1>
        <div class="admin-page-subtitle">管理學生、教師與獎助單位帳號；管理員帳號不在此頁開放操作。</div>
    </div>
</div>

<div class="admin-actions">
    <a href="account_form.php" class="btn btn-add">＋ 新增帳號</a>
</div>

<div class="filters account-role-filters" aria-label="帳號身分篩選">
    <?php foreach ($role_filter_options as $roleValue => $label): ?>
        <button
            type="button"
            class="filter-btn <?php echo $roleValue === "all" ? "active" : ""; ?>"
            data-role="<?php echo htmlspecialchars($roleValue); ?>">
            <span><?php echo htmlspecialchars($label); ?></span>
            <span class="filter-count"><?php echo $roleValue === "all" ? count($accounts) : $role_counts[$roleValue]; ?></span>
        </button>
    <?php endforeach; ?>
</div>

<div class="admin-card admin-table-wrap">
    <table>
        <thead>
            <tr>
                <th>帳號 ID</th>
                <th>身分</th>
                <th>姓名 / 單位</th>
                <th>角色資料</th>
                <th>電話</th>
                <th>Email</th>
                <th>狀態</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($accounts as $account): ?>
                <?php
                $role = (int)$account["ROLE"];
                $displayName = ($role === 4 && !empty($account["ONAME"])) ? $account["ONAME"] : $account["NAME"];
                if ($role === 1) {
                    $roleInfo = "學號：" . ($account["SID"] ?: "未填") . " / 系所：" . ($account["STUDENT_DEPT"] ?: "未填");
                } elseif ($role === 2) {
                    $roleInfo = "系所：" . ($account["TEACHER_DEPT"] ?: "未填");
                } elseif ($role === 4) {
                    $roleInfo = "聯絡人：" . ($account["CONTACT"] ?: "未填");
                } else {
                    $roleInfo = "未填";
                }
                $phoneText = ($role === 4 && !empty($account["ORG_PHONES"])) ? $account["ORG_PHONES"] : $account["TEL"];
                ?>
                <tr data-role="<?php echo htmlspecialchars((string)$role); ?>">
                    <td><?php echo htmlspecialchars($account["ID"]); ?></td>
                    <td><?php echo htmlspecialchars(account_role_name($role)); ?></td>
                    <td><?php echo htmlspecialchars($displayName); ?></td>
                    <td><?php echo htmlspecialchars($roleInfo); ?></td>
                    <td><?php echo htmlspecialchars($phoneText ?: "未填"); ?></td>
                    <td><?php echo htmlspecialchars($account["EMAIL"] ?: "未填"); ?></td>
                    <td><?php echo site_status_badge($account["status"] ?: "未填"); ?></td>
                    <td>
                        <a href="account_form.php?id=<?php echo urlencode($account["ID"]); ?>" class="btn btn-edit">修改</a>
                        <a href="account_process.php?action=delete&id=<?php echo urlencode($account["ID"]); ?>"
                            class="btn btn-del" data-confirm="警告：將刪除此帳號與其角色相關資料，確定嗎？">刪除</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
    document.querySelectorAll(".account-role-filters .filter-btn").forEach(function(button) {
        button.addEventListener("click", function() {
            var role = button.getAttribute("data-role");

            document.querySelectorAll(".account-role-filters .filter-btn").forEach(function(item) {
                item.classList.toggle("active", item === button);
            });

            document.querySelectorAll("tbody tr[data-role]").forEach(function(row) {
                row.style.display = (role === "all" || row.getAttribute("data-role") === role) ? "" : "none";
            });
        });
    });

    var activeFilter = document.querySelector(".account-role-filters .filter-btn.active");
    if (activeFilter) {
        activeFilter.click();
    }
</script>
</main>
</body>

</html>