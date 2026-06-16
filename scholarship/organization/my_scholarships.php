<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
date_default_timezone_set('Asia/Taipei');
require_once "db.php";

// 取得 provider_id
if (isset($_SESSION['user']['id'])) {
    $provider_id = $_SESSION['user']['id'];
} else {
    $provider_id = isset($_GET['provider_id']) ? $_GET['provider_id'] : null;
}

if (!$provider_id) {
    die("請先登入或在網址加上 provider_id 參數，例如 ?provider_id=S0000001");
}

// 取得選擇的 scholarship_id
$selected_id = isset($_GET['scholarship_id']) ? $_GET['scholarship_id'] : 'all';

// 依選擇篩選清單
if ($selected_id === 'all') {
    $sql = "SELECT id, NAME, DEADLINE, CONDI, AMOUNT, start_date 
            FROM scholarship 
            WHERE provider_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($provider_id));
} else {
    $sql = "SELECT id, NAME, DEADLINE, CONDI, AMOUNT, start_date 
            FROM scholarship 
            WHERE provider_id = ? AND id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($provider_id, $selected_id));
}
$scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 取得所有獎助學金供選單使用
$stmt = $pdo->prepare("SELECT id, NAME FROM scholarship WHERE provider_id = ?");
$stmt->execute(array($provider_id));
$allOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>我提供的獎助學金</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: "Segoe UI", "Noto Sans TC", sans-serif;
        }
        .card {
            border-radius: 12px;
        }
        .badge {
            font-size: 0.9em;
        }
        .card-title {
            font-weight: 600;
            color: #0d6efd;
        }
        .fixed-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body>
<div class="container-sm mt-5">
    <h2 class="display-5 mb-4 text-primary">🎓 我提供的獎助學金清單</h2>

    <!-- 選擇獎助學金 -->
    <form method="get" action="" class="mb-4 d-flex gap-2 align-items-center">
        <input type="hidden" name="provider_id" value="<?php echo htmlspecialchars($provider_id); ?>">
        <label for="scholarship_id" class="form-label mb-0">選擇獎助學金：</label>
        <select name="scholarship_id" id="scholarship_id" class="form-select w-auto" onchange="this.form.submit()">
            <option value="all" <?php echo ($selected_id === 'all') ? 'selected' : ''; ?>>總覽</option>
            <?php foreach ($allOptions as $opt): ?>
                <option value="<?php echo $opt['id']; ?>" <?php echo ($selected_id == $opt['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($opt['NAME']); ?>
                </option>
            <?php endforeach; ?>
        </select>
       <?php 
        if($selected_id !== 'all' && !empty($selected_id)) {
            $deleteUrl = "delete_scholarship.php?scholarship_id=" . urlencode($selected_id);
            echo "<a href=\"$deleteUrl\" class=\"btn btn-danger\" onclick=\"return confirm('確定要刪除此獎學金嗎？');\">刪除</a>";
        }
    ?>
        <noscript><button type="submit" class="btn btn-primary">瀏覽</button></noscript>
    </form>

    <?php if (count($scholarships) === 0): ?>
        <div class="alert alert-warning">目前沒有獎學金。</div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 g-4">
            <?php foreach ($scholarships as $s): ?>
                <?php
                $today = date('Y-m-d');
                if ($s['start_date'] > $today) {
                    $status = "尚未開始";
                } elseif ($s['start_date'] <= $today && $s['DEADLINE'] >= $today) {
                    $status = "開放中";
                } else {
                    $status = "已截止";
                }
                ?>
                <div class="col">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($s['NAME']); ?></h5>
                            <p class="mb-1"><strong>開始日期：</strong> <?php echo htmlspecialchars($s['start_date']); ?></p>
                            <p class="mb-1"><strong>截止日期：</strong> <?php echo htmlspecialchars($s['DEADLINE']); ?></p>
                            <p class="mb-1"><strong>申請條件：</strong> <?php echo htmlspecialchars($s['CONDI']); ?></p>
                            <p class="mb-1"><strong>金額：</strong> $<?php echo htmlspecialchars($s['AMOUNT']); ?></p>
                            <p class="mb-2"><strong>狀態：</strong>
                                <?php if ($status === '尚未開始'): ?>
                                    <span class="badge bg-warning text-dark">尚未開始</span>
                                <?php elseif ($status === '開放中'): ?>
                                    <span class="badge bg-success">開放中</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">已截止</span>
                                <?php endif; ?>
                            </p>
                            <a href="view_applicants.php?provider_id=<?php echo urlencode($provider_id); ?>&scholarship_id=<?php echo $s['id']; ?>" 
                               class="btn btn-outline-primary btn-sm w-100">
                                📄 瀏覽申請資料
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- 保持原來格式的返回主頁按鈕 -->
<div class="text-center mt-4"> 
    <a href="org-dashboard.php" class="btn btn-secondary fixed-btn">返回主頁</a> 
</div>

</body>
</html>
