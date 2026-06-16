<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
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

<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>我提供的獎助學金</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    body{
      font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans TC",sans-serif;
      margin:24px;
      background:#f6f7fb
    }
    .card{
      background:#fff;
      border-radius:14px;
      padding:16px;
      box-shadow:0 1px 8px rgba(0,0,0,.06)
    }
    .grid{display:grid;gap:14px}
    .kpi{grid-template-columns:repeat(3,minmax(0,1fr))}
    .topbar{
      display:flex;
      align-items:center;
      justify-content:space-between;
      margin-bottom:14px
    }
    .tabs a{
      margin-right:14px;
      text-decoration:none;
      color:#111
    }
    .tabs a.active{
      font-weight:700;
      color:#2563eb
    }
    .muted{color:#667085}
    .btn{
      display:inline-block;
      padding:8px 14px;
      border-radius:999px;
      background:#2563eb;
      color:#fff;
      text-decoration:none;
      font-size:14px
    }
    .topbar {
  display:flex;
  align-items:center;
  justify-content:space-between;
  margin-bottom:14px;
  background:#fff;       /* 白色背景 */
  padding:10px 20px;     /* 內距 */
  box-shadow:0 1px 6px rgba(0,0,0,.1); /* 陰影 */
  border-radius:8px;     /* 圓角 */
    }

  </style>
</head>
<body>

<!-- ====== Topbar（跟學生頁一致） ====== -->
<div class="topbar">
  <div><strong>獎助學金系統｜獎助單位端</strong></div>

  <div class="tabs">
    <a class="active" href="/scholarship/organization/org-dashboard.php">總覽</a>
    <a href="/scholarship/organization/my_scholarships.php">瀏覽我提供的獎助學金</a>
    <a href="/scholarship/organization/view_applicants.php">瀏覽申請資料</a>
    <a href="/scholarship/organization/add_scholarship.php">新增獎助學金</a>
    <a href="/scholarship/profile.php">個人檔案</a>
  </div>

  <div>
    <?= htmlspecialchars($userName) ?>｜
    <a href="/scholarship/logout.php">登出</a>
  </div>
</div>

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


</body>
</html>
