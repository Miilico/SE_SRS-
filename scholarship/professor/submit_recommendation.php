<?php
// submit_recommendation.php

require_once __DIR__ . "/../config.php";

// 檢查表單是否有 token 與推薦內容
if (!isset($_POST['token']) || empty($_POST['token'])) {
    die("❌ 無效的提交：缺少 token");
}
if (!isset($_POST['content']) || empty($_POST['content'])) {
    die("❌ 無效的提交：缺少推薦內容");
}

$token   = $_POST['token'];
$content = $_POST['content'];

// 查詢 token 是否存在
/*$stmt = $pdo->prepare("SELECT * FROM recommendations WHERE token = :token LIMIT 1");
$stmt->execute([':token' => $token]);
$recommendation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recommendation) {
    die("❌ 無效的 token");
}*/

// 更新推薦信內容與提交時間
/*$stmt = $pdo->prepare("UPDATE recommendations 
                       SET content = :content, created_at = NOW() 
                       WHERE token = :token");
$stmt->execute([
    ':content' => $content,
    ':token'   => $token
]);*/

// 查詢 token 對應的申請 
$stmt = $pdo->prepare("
    SELECT a.APNO, a.STID, a.SCID, r.token
    FROM recommendations r
    JOIN application a ON r.application_id = a.APNO
    WHERE r.token = :token
    LIMIT 1"); 
$stmt->execute([':token' => $token]); 
$app = $stmt->fetch(PDO::FETCH_ASSOC); 

if (!$app) { die("❌ 無效的 token"); } 

// 新增推薦信 
$insert = $pdo->prepare(" 
    UPDATE recommendations
    SET content = :content,
        created_at = NOW()
    WHERE token = :token 
"); 
$insert->execute([ 
    ':content' => $content, 
    //':teacher_name' => isset($_POST['teacher_name']) ? $_POST['teacher_name'] : '', 
    //':teacher_email' => isset($_POST['teacher_email']) ? $_POST['teacher_email'] : '', 
    //':rec_rel' => isset($_POST['rec_rel']) ? $_POST['rec_rel'] : '', 
    ':token' => $token 
]);


echo "✅ 推薦信已成功送出，感謝您的協助！";
?>
