<?php
session_start();
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";

require_role(3);

// 刪除模式
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE ID=? AND ROLE=4");
        $stmt->execute([$id]);
        echo "<script>alert('刪除完成');location.href='org_management.php';</script>";
    } catch (PDOException $e) {
        die("刪除失敗：" . $e->getMessage());
    }
    exit;
}

// 新增或修改
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $mode   = $_POST['mode'];
    $id     = trim($_POST['id']);
    $name   = trim($_POST['name']);
    $pwd = isset($_POST['pwd']) ? trim($_POST['pwd']) : "";
    $email  = trim($_POST['email']);
    $c_name = trim($_POST['contact_person']);
    $phones = isset($_POST['org_phones']) ? explode(',', $_POST['org_phones']) : [];

    // 密碼雜湊
    $hash = null;
    if ($pwd !== "") {
        $hash = password_hash($pwd, PASSWORD_DEFAULT);
    }

    // 主要電話（取第一筆）
    $mainTel = "";
    if (!empty($phones)) {
        $mainTel = trim($phones[0]);
    }

    // 建立時間 
    $createAt = date("Y-m-d H:i:s");

    try {
        $pdo->beginTransaction();

        if ($mode === 'add') {
            // 新增 users
            $sql1 = "INSERT INTO users (ID, NAME, ROLE, PWD, TEL, EMAIL, STATUS, created_at) 
                     VALUES (?, ?, 4, ?, ?, ?, 'active', ?)";
            $pdo->prepare($sql1)->execute([$id, $name, $hash, $mainTel, $email, $createAt]);

            // 新增 organization
            $sql2 = "INSERT INTO organization (ID, ONAME, CONTACT) VALUES (?, ?, ?)";
            $pdo->prepare($sql2)->execute([$id, $name, $c_name]);

            // 新增多筆電話
            $stmtP = $pdo->prepare("INSERT INTO ophone (ID, TEL) VALUES (?, ?)");
            foreach ($phones as $p) {
                $p = trim($p);
                if ($p !== "") $stmtP->execute([$id, $p]);
            }

            $msg = "新增獎助單位成功！";

        } else {
            // 修改 users
            if ($hash) {
                $sql1 = "UPDATE users SET NAME=?, PWD=?, TEL=?, EMAIL=? WHERE ID=? AND ROLE=4";
                $pdo->prepare($sql1)->execute([$name, $hash, $mainTel, $email, $id]);
            } else {
                $sql1 = "UPDATE users SET NAME=?, TEL=?, EMAIL=? WHERE ID=? AND ROLE=4";
                $pdo->prepare($sql1)->execute([$name, $mainTel, $email, $id]);
            }

            // 修改 organization
            $sql2 = "INSERT INTO organization (ID, ONAME, CONTACT) 
                     VALUES (?, ?, ?) 
                     ON DUPLICATE KEY UPDATE ONAME=VALUES(ONAME), CONTACT=VALUES(CONTACT)";
            $pdo->prepare($sql2)->execute([$id, $name, $c_name]);

            // 更新多筆電話
            $pdo->prepare("DELETE FROM ophone WHERE ID=?")->execute([$id]);
            $stmtP = $pdo->prepare("INSERT INTO ophone (ID, TEL) VALUES (?, ?)");
            foreach ($phones as $p) {
                $p = trim($p);
                if ($p !== "") $stmtP->execute([$id, $p]);
            }

            $msg = "修改單位資料成功！";
        }

        $pdo->commit();
        echo "<script>alert('$msg');location.href='org_management.php';</script>";

    } catch (Exception $e) {
        $pdo->rollBack();
        die("操作失敗：" . $e->getMessage());
    }
}
