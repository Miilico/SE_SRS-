<?php
session_start();
require_once __DIR__ . "/config.php";


$target_id = isset($_SESSION["user"]["id"]) ? $_SESSION["user"]["id"] : null;

if (!$target_id) {
    header("Location: login.php");
    exit;
}

$message = "";

// 處理表單提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pdo->beginTransaction();

        // 1. 更新 users 基本資料
        $sql1 = "UPDATE users SET NAME = ?, TEL = ?, EMAIL = ? WHERE ID = ?";
        $stmt1 = $pdo->prepare($sql1);
        $stmt1->execute([$_POST['NAME'], $_POST['TEL'], $_POST['EMAIL'], $target_id]);

        // 2. 根據角色更新擴展資料
        $role = $_POST['ROLE'];
        if ($role == 1) { // 學生
            $stmt2 = $pdo->prepare("UPDATE students SET SID = ?, DNAME = ? WHERE ID = ?");
            $stmt2->execute([$_POST['SID'], $_POST['DNAME'], $target_id]);
        } else if ($role == 2) { // 教師 (對應原檔邏輯)
            $stmt2 = $pdo->prepare("UPDATE teachers SET DNAME = ? WHERE ID = ?");
            $stmt2->execute([$_POST['DNAME'], $target_id]);
        } else if ($role == 4) { // 獎助單位
            // 更新 organization (CONTACT 是聯絡人姓名)
            $stmt2 = $pdo->prepare("UPDATE organization SET ONAME = ?, CONTACT = ? WHERE ID = ?");
            $stmt2->execute([$_POST['NAME'], $_POST['CONTACT'], $target_id]);

            // 更新 ophone (多重電話)
            $pdo->prepare("DELETE FROM ophone WHERE ID = ?")->execute([$target_id]);
            if (!empty($_POST['ORG_PHONES'])) {
                $phones = explode(',', $_POST['ORG_PHONES']);
                $stmtP = $pdo->prepare("INSERT INTO ophone (ID, TEL) VALUES (?, ?)");
                foreach ($phones as $p) {
                    $p = trim($p);
                    if ($p !== "") $stmtP->execute([$target_id, $p]);
                }
            }
        }

        $pdo->commit();
        $message = "資料更新成功！";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "更新失敗：" . $e->getMessage();
    }
}

// 讀取現有資料以供編輯
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE ID = ?");
    $stmt->execute([$target_id]);
    $user = $stmt->fetch();

    $extra = [];
    $org_phones_str = ""; 

    if ($user['ROLE'] == 1) {
        $st = $pdo->prepare("SELECT * FROM students WHERE ID = ?"); $st->execute([$target_id]);
        $extra = $st->fetch();
    } else if ($user['ROLE'] == 2 || $user['ROLE'] == 3) {
        $st = $pdo->prepare("SELECT * FROM teachers WHERE ID = ?"); $st->execute([$target_id]);
        $extra = $st->fetch();
    } else if ($user['ROLE'] == 4) {
        $st = $pdo->prepare("SELECT * FROM organization WHERE ID = ?"); $st->execute([$target_id]);
        $extra = $st->fetch();
        
        // 抓取多重電話
        $stP = $pdo->prepare("SELECT TEL FROM ophone WHERE ID = ?");
        $stP->execute([$target_id]);
        $org_phones_str = implode(', ', $stP->fetchAll(PDO::FETCH_COLUMN));
    }
} catch (PDOException $e) {
    die("資料讀取錯誤");
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>修改個人資料 - 獎助學金系統</title>
    <style>
        body { background-color: #f4f7f6; font-family: "Microsoft JhengHei", sans-serif; color: #333; }
        .edit-container { width: 700px; margin: 50px auto; padding: 40px; border: 1px solid #ddd; background: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border-radius: 8px; }
        .edit-title { font-size: 28px; font-weight: bold; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 25px; }
        .section-header { background-color: #f8f9fa; font-weight: bold; padding: 10px; margin-top: 20px; border-left: 4px solid #333; font-size: 18px; }
        .form-group { margin: 15px 0; display: flex; align-items: center; }
        .form-group label { width: 30%; font-weight: bold; color: #666; }
        .form-group input { width: 70%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 16px; box-sizing: border-box; }
        .btn-group { margin-top: 30px; display: flex; gap: 15px; }
        .btn { padding: 10px 25px; border-radius: 4px; font-size: 16px; cursor: pointer; transition: 0.3s; border: none; text-decoration: none; }
        .btn-submit { background-color: #333; color: white; }
        .btn-back { background-color: #eee; color: #333; text-align: center; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; background-color: #d4edda; color: #155724; }
    </style>
</head>
<body>

<div class="edit-container">
    <div class="edit-title">修改個人資料</div>

    <?php if ($message): ?>
        <div class="alert"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="ROLE" value="<?php echo $user['ROLE']; ?>">

        <div class="section-header">基本聯絡資料</div>
        <div class="form-group">
            <label>單位/姓名</label>
            <input type="text" name="NAME" value="<?php echo htmlspecialchars($user['NAME']); ?>" required>
        </div>
        <div class="form-group">
            <label>電話</label>
            <input type="text" name="TEL" value="<?php echo htmlspecialchars($user['TEL']); ?>">
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="EMAIL" value="<?php echo htmlspecialchars($user['EMAIL']); ?>">
        </div>

        <?php if ($user['ROLE'] == 1): ?>
            <div class="section-header">學籍資料</div>
            <div class="form-group"><label>學號</label><input type="text" name="SID" value="<?php echo htmlspecialchars($extra['SID']); ?>"></div>
            <div class="form-group"><label>就讀系所</label><input type="text" name="DNAME" value="<?php echo htmlspecialchars($extra['DNAME']); ?>"></div>
        <?php elseif ($user['ROLE'] == 2): ?>
            <div class="section-header">教職資料</div>
            <div class="form-group"><label>所屬系所</label><input type="text" name="DNAME" value="<?php echo htmlspecialchars($extra['DNAME']); ?>"></div>
        <?php elseif ($user['ROLE'] == 4): ?>
            <div class="section-header">獎助單位資訊</div>
            <div class="form-group">
                <label>聯絡人姓名</label>
                <input type="text" name="CONTACT" value="<?php echo htmlspecialchars($extra['CONTACT']); ?>" placeholder="請輸入聯絡人姓名">
            </div>
            <div class="form-group">
                <label>單位電話</label>
                <input type="text" name="ORG_PHONES" value="<?php echo htmlspecialchars($org_phones_str); ?>" placeholder="多筆電話請用逗號隔開">
            </div>
        <?php endif; ?>

        <div class="btn-group">
            <button type="submit" class="btn btn-submit">儲存修改</button>
            <a href="profile.php" class="btn btn-back">取消返回</a>
        </div>
    </form>
</div>
</body>
</html>