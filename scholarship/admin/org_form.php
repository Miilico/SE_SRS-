<?php
$adminHeaderBootstrapOnly = true;
require __DIR__ . "/header.php";
unset($adminHeaderBootstrapOnly);

//if (!isset($_SESSION['role']) || $_SESSION['role'] != 3) { die("拒絕存取"); }

$id = isset($_GET['id']) ? $_GET['id'] : '';
$org = [
    'ID' => '',
    'NAME' => '',
    'TEL' => '',
    'EMAIL' => '',
    'CONTACT' => '',
];
$phone_str = '';
$mode = 'add';

if ($id) {
    $stmt = $pdo->prepare("SELECT u.*, o.ONAME, o.CONTACT 
                           FROM users u 
                           LEFT JOIN organization o ON u.ID = o.ID 
                           WHERE u.ID = ? AND u.ROLE = 4");
    $stmt->execute([$id]);
    $found_org = $stmt->fetch();

    if (!$found_org) {
        echo "<script>alert('修改的單位不存在');location.href='org_management.php';</script>";
        exit;
    }

    $org = $found_org;
    $mode = 'edit';

    // 抓取該單位的所有電話
    $stmt_p = $pdo->prepare("SELECT TEL FROM ophone WHERE ID = ?");
    $stmt_p->execute([$id]);
    $phones = $stmt_p->fetchAll(PDO::FETCH_COLUMN);
    $phone_str = implode(', ', $phones); // 轉成字串顯示
}
$pageTitle = ($mode == 'add') ? "新增獎助單位" : "修改獎助單位";
$activeNav = "org_management.php";
?>
<?php require __DIR__ . "/header.php"; ?>

    <div class="form-container">
        <h1 class="admin-page-title"><?php echo ($mode == 'add') ? "新增獎助單位" : "修改單位資料"; ?></h1>
        <div class="admin-page-subtitle admin-form-lead">建立或更新獎助學金單位的登入與聯絡資訊。</div>
        <form action="org_process.php" method="post">
            <input type="hidden" name="mode" value="<?php echo $mode; ?>">
            
            <div class="sub-title">基本帳號資訊</div>
            <label>帳號 (ID):</label>
            <input type="text" name="id" value="<?php echo htmlspecialchars($org['ID']); ?>" <?php echo ($mode == 'edit') ? 'readonly' : 'required'; ?>>
            
            <label>單位名稱 (NAME):</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($org['NAME']); ?>" required>
            
            <label>登入密碼 (PWD):</label>
            <input type="password" name="pwd" <?php echo ($mode == 'add') ? 'required' : ''; ?> placeholder="<?php echo ($mode == 'edit') ? '不修改請留空' : ''; ?>">
            
            
            <input type="hidden" name="tel" value="<?php echo htmlspecialchars($org['TEL']); ?>">
            
            <label>Email:</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($org['EMAIL']); ?>">
            
           <div class="sub-title">單位擴充資訊 </div>
            <label>單位聯絡人姓名:</label>
            <input type="text" name="contact_person" value="<?php echo htmlspecialchars($org['CONTACT']); ?>">
            
            <label>單位電話 (可多筆，請用逗號隔開):</label>
            <input type="text" name="org_phones" value="<?php echo htmlspecialchars($phone_str); ?>" placeholder="例如: 02-123, 0912-345">
            
            <div class="admin-actions admin-actions-bottom">
                <button type="submit" class="btn">儲存送出</button>
                <a href="org_management.php">取消返回</a>
            </div>
        </form>
    </div>
</main>
</body>
</html>
