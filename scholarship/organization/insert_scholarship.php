<?php

/*echo "<pre>";
var_dump($_POST);
exit;*/

session_start();
require_once "db.php";

// 權限檢查
/*if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'provider') {
    die("無權限");
}*/

// 接收表單
$provider_id = $_SESSION['user']['id'];
$name     = $_POST['scholarship_name'];
$start_date = $_POST['start_date'];
$deadline = $_POST['deadline'];
$condi    = $_POST['conditions'];
$amount   = $_POST['amount'];

$start_date = $_POST['start_date'];
$deadline   = $_POST['deadline'];

// 檢查必填欄位 
if ($name === '' || $amount === '' || $start_date === '' || $deadline === '') { 
    header("Location: add_scholarship.php?error=" . urlencode("請完整填寫必填欄位")); 
    exit; 
}

// 檢查金額必須為正數 
if (!is_numeric($amount) || (float)$amount <= 0) {
    header("Location: add_scholarship.php?error=" . urlencode("金額必須為正數")); 
    exit; 
}

// 檢查日期邏輯 
if ($start_date > $deadline) {
    header("Location: add_scholarship.php?error=" . urlencode("開始日期不能晚於截止日期")); 
    exit; 
} try { 
    $sql = "INSERT INTO scholarship (NAME, provider_id, DEADLINE, CONDI, AMOUNT, start_date) 
            VALUES (?, ?, ?, ?, ?, ?)"; 
            $stmt = $pdo->prepare($sql); 
            $stmt->execute([$name, $provider_id, $deadline, $condi, $amount, $start_date]); 
            header("Location: add_scholarship.php?success=1"); 
            exit; 
} catch (PDOException $e) { 
    header("Location: add_scholarship.php?error=" . urlencode("新增失敗：" . $e->getMessage())); 
    exit; 
}

?>

<!DOCTYPE html> 
<html lang="zh-TW">
    <!-- Bootstrap CSS --> <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<body class="bg-light">
    
<div class="alert alert-success text-center mt-4" role="alert">
    🎉 新增成功！<br>
    獎助學金已成功建立
    <div class="mt-2">
        <a href="add_scholarship.php" class="btn btn-outline-primary">繼續新增</a>
        <a href="org-dashboard.php" class="btn btn-outline-secondary ms-2">返回主頁</a>
    </div>
</div>

<!-- Bootstrap JS --> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body> 
</html>