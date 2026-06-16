<?php
session_start();
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";

require_role(3);

//if (!isset($_SESSION['role']) || $_SESSION['role'] != 3) { die("拒絕存取"); }

$id = isset($_GET['id']) ? $_GET['id'] : '';
$org = null;
$mode = 'add';

if ($id) {
    $stmt = $pdo->prepare("SELECT u.*, o.ONAME, o.CONTACT 
                           FROM users u 
                           LEFT JOIN organization o ON u.ID = o.ID 
                           WHERE u.ID = ? AND u.ROLE = 4");
    $stmt->execute([$id]);
    $org = $stmt->fetch();
    
    // 抓取該單位的所有電話
    $stmt_p = $pdo->prepare("SELECT TEL FROM ophone WHERE ID = ?");
    $stmt_p->execute([$id]);
    $phones = $stmt_p->fetchAll(PDO::FETCH_COLUMN);
    $phone_str = implode(', ', $phones); // 轉成字串顯示
    
    if ($org) $mode = 'edit';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo ($mode == 'add') ? "新增獎助單位" : "修改教學單位"; ?></title>
    <style>
        .form-container { width: 400px; margin: 30px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; }
        input { width: 100%; padding: 8px; margin: 8px 0; box-sizing: border-box; }
        label { font-weight: bold; color: #555; }
        .sub-title { background: #eee; padding: 5px; margin-top: 15px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="form-container">
        <h3><?php echo ($mode == 'add') ? "＋ 新增獎助單位" : "📝 修改單位資料"; ?></h3>
        <form action="org_process.php" method="post">
            <input type="hidden" name="mode" value="<?php echo $mode; ?>">
            
            <div class="sub-title">基本帳號資訊</div>
            <label>帳號 (ID):</label>
            <input type="text" name="id" value="<?php echo htmlspecialchars($org['ID']); ?>" <?php echo ($mode == 'edit') ? 'readonly' : 'required'; ?>>
            
            <label>單位名稱 (NAME):</label>
            <input type="text" name="name" value="<?php if($org['NAME']) echo htmlspecialchars($org['NAME']);?>" required>
            
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
            
            <button type="submit" style="width:100%; padding:10px; background:#007bff; color:white; border:none; cursor:pointer;">儲存送出</button>
            <p style="text-align:center;"><a href="org_management.php">取消返回</a></p>
        </form>
    </div>
</body>
</html>