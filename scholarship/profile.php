<?php 
session_start();
require_once __DIR__ . "/config.php";


$target_id = isset($_SESSION["user"]["id"]) ? $_SESSION["user"]["id"] : null;

if (!$target_id) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>個人資料 - 獎助學金系統</title>
    <style>
        body { background-color: #f4f7f6; font-family: "Microsoft JhengHei", sans-serif; color: #333; }
        .info-container { 
            width: 700px; 
            margin: 50px auto; 
            padding: 40px; 
            border: 1px solid #ddd; 
            background: #fff; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border-radius: 8px;
        }
        .info-title { 
            font-size: 28px; 
            font-weight: bold; 
            border-bottom: 2px solid #333; 
            padding-bottom: 10px; 
            margin-bottom: 25px; 
            color: #222;
        }
        .section-header {
            background-color: #f8f9fa;
            font-weight: bold;
            padding: 10px;
            margin-top: 20px;
            border-left: 4px solid #333;
            font-size: 18px;
        }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td { padding: 12px 15px; border-bottom: 1px solid #eee; }
        .label { width: 30%; color: #666; font-weight: bold; }
        .value { width: 70%; color: #333; }
        
        .btn-group { margin-top: 30px; display: flex; gap: 15px; }
        .btn { 
            padding: 10px 20px; 
            text-decoration: none; 
            border-radius: 4px; 
            font-size: 16px; 
            cursor: pointer;
            transition: 0.3s;
            border: none;
        }
        .btn-edit { background-color: #333; color: white; }
        .btn-edit:hover { background-color: #555; }
        .btn-back { background-color: #4682BF; color: white; text-align: center; }
        .btn-back:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="info-container">
    <div class="info-title">個人帳號資訊</div>

    <?php
    try {
        // 取得基本帳號資料
        $stmt1 = $pdo->prepare("SELECT * FROM users WHERE ID = ?");
        $stmt1->execute([$target_id]);
        $row1 = $stmt1->fetch();

        if ($row1) {
            echo '<div class="section-header">基本帳號</div>';
            echo '<table>';
            echo '<tr><td class="label">使用者 ID</td><td class="value">' . htmlspecialchars($row1['ID']) . '</td></tr>';
            
            $role_name = "";
            switch($row1['ROLE']) {
                case 1: $role_name = "學生"; break;
                case 2: $role_name = "教師"; break;
                case 3: $role_name = "系統管理員"; break;
                case 4: $role_name = "獎助單位"; break;
                default: $role_name = "未知身分";
            }
            echo '<tr><td class="label">身分</td><td class="value">' . $role_name . '</td></tr>';
            echo '<tr><td class="label">Email</td><td class="value">' . htmlspecialchars($row1['EMAIL']) . '</td></tr>';
            echo '</table>';

            // 擴充資料區段
            if ($row1['ROLE'] == 1) { // 學生
                echo '<div class="section-header">個人資料</div>';
                $stmt2 = $pdo->prepare("SELECT * FROM students WHERE ID = ?");
                $stmt2->execute([$target_id]);
                $row2 = $stmt2->fetch();
                echo '<table>';
                echo '<tr><td class="label">姓名</td><td class="value">' . htmlspecialchars($row1['NAME']) . '</td></tr>';
                if ($row2) {
                    echo '<tr><td class="label">學號</td><td class="value">' . htmlspecialchars($row2['SID']) . '</td></tr>';
                    echo '<tr><td class="label">就讀系所</td><td class="value">' . htmlspecialchars($row2['DNAME']) . '</td></tr>';
                }
                echo '<tr><td class="label">連絡電話</td><td class="value">' . htmlspecialchars($row1['TEL']) . '</td></tr>';
                echo '</table>';

            } else if ($row1['ROLE'] == 2) { // 教師
                echo '<div class="section-header">個人資料</div>';
                $stmt2 = $pdo->prepare("SELECT * FROM teachers WHERE ID = ?");
                $stmt2->execute([$target_id]);
                $row2 = $stmt2->fetch();
                echo '<table>';
                echo '<tr><td class="label">姓名</td><td class="value">' . htmlspecialchars($row1['NAME']) . '</td></tr>';
                if ($row2) {
                    echo '<tr><td class="label">所屬系所</td><td class="value">' . htmlspecialchars($row2['DNAME']) . '</td></tr>';
                }
                echo '<tr><td class="label">連絡電話</td><td class="value">' . htmlspecialchars($row1['TEL']) . '</td></tr>';
                echo '</table>';
             } else if ($row1['ROLE'] == 3) { // 管理員
                echo '<div class="section-header">個人資料</div>';
                echo '<table>';
                echo '<tr><td class="label">姓名</td><td class="value">' . htmlspecialchars($row1['NAME']) . '</td></tr>';
                echo '<tr><td class="label">連絡電話</td><td class="value">' . htmlspecialchars($row1['TEL']) . '</td></tr>';
                echo '</table>';
            } else if ($row1['ROLE'] == 4) { // 獎助單位
                echo '<div class="section-header">獎助單位資訊</div>';
                
                // 1. 取得單位擴充資訊 (organization)
                $stmt2 = $pdo->prepare("SELECT * FROM organization WHERE ID = ?");
                $stmt2->execute([$target_id]);
                $row2 = $stmt2->fetch();

                // 2. 取得多重電話 (ophone)
                $stmt3 = $pdo->prepare("SELECT TEL FROM ophone WHERE ID = ?");
                $stmt3->execute([$target_id]);
                $phones = $stmt3->fetchAll(PDO::FETCH_COLUMN);
                $phone_str = implode(', ', $phones);

                echo '<table>';
                echo '<tr><td class="label">單位名稱</td><td class="value">' . htmlspecialchars($row1['NAME']) . '</td></tr>';
                if ($row2) {
                    echo '<tr><td class="label">聯絡人姓名</td><td class="value">' . htmlspecialchars($row2['CONTACT']) . '</td></tr>';
                }
                echo '<tr><td class="label">聯絡電話 (多筆)</td><td class="value">' . htmlspecialchars($phone_str ?: '尚未填寫') . '</td></tr>';
                echo '</table>';
            }

        } else {
            echo '<p>找不到該使用者資料，請重新登入。</p>';
        }
    } catch (PDOException $e) {
        echo '<p>資料庫錯誤：' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    ?>

    <div class="btn-group">
        <button onclick="location.href='profile_edit.php'" class="btn btn-edit">修改個人資料</button>
        <a href="
            <?php 
                if($row1['ROLE']==1) echo '/scholarship/student/student-dashboard.php';
                else if($row1['ROLE']==2) echo '/scholarship/professor/tea_dashboard.php';
                else if($row1['ROLE']==3) echo '/scholarship/admin/admin_dashboard.php';
                else echo '/scholarship/organization/org-dashboard.php';
            ?>" class="btn btn-back">← 返回首頁</a>
    </div>
</div>

</body>
</html>