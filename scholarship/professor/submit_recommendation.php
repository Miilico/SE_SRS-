<?php
// submit_recommendation.php

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../file_helpers.php";

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
    SELECT a.APNO, a.STID, a.SCID, a.OID, r.id AS recommendation_id, r.teacher_id, r.token, s.provider_id
    FROM recommendations r
    JOIN application a ON r.application_id = a.APNO
    JOIN scholarship s ON a.SCID = s.id
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

if (!empty($_FILES["RECOMMENDATION_FILE"]) && $_FILES["RECOMMENDATION_FILE"]["error"] !== UPLOAD_ERR_NO_FILE) {
    try {
        if (empty($app["teacher_id"])) {
            die("❌ 無法儲存附件：缺少導師帳號");
        }

        store_uploaded_file($pdo, $_FILES["RECOMMENDATION_FILE"], 4, $app["teacher_id"], array(
            "application_id" => $app["APNO"],
            "scholarship_id" => $app["SCID"],
            "scholarship_provider_id" => $app["provider_id"],
            "recommendation_id" => $app["recommendation_id"],
            "allowed_ext" => array("pdf", "doc", "docx", "jpg", "jpeg", "png")
        ));
    } catch (Exception $e) {
        die("❌ 推薦信內容已儲存，但附件儲存失敗：" . htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8"));
    }
}


echo "✅ 推薦信已成功送出，感謝您的協助！";
?>
