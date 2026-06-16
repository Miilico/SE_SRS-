<?php
// recommendation.php

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../file_helpers.php";

if (!isset($_GET['token']) || empty($_GET['token'])) {
    die("❌ 無效的連結");
}

$token = $_GET['token'];

//$stmt = $pdo->prepare("SELECT * FROM recommendations WHERE token = :token LIMIT 1");
$stmt = $pdo->prepare(" 
    SELECT r.id AS rec_id, r.application_id, r.teacher_id, r.teacher_name, r.teacher_email, r.rec_rel,
         a.GRADE, a.RANK, a.STID, a.SCID,
         u.NAME AS student_name,
         s.NAME AS scholarship_name
  FROM recommendations r
  JOIN application a ON r.application_id = a.APNO
  JOIN users u ON a.STID = u.ID
  JOIN scholarship s ON a.SCID = s.id
  WHERE r.token = :token
  LIMIT 1
");
$stmt->execute([':token' => $token]);
$recommendation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recommendation) {
    die("❌ 無效的連結或已過期");
}

if (!empty($recommendation['content'])) {
    echo "<div style='margin: 50px auto; max-width: 600px; font-family: sans-serif; text-align: center;'>
            <h3 style='color: green;'>✅ 您已經提交過推薦信</h3>
            <p>感謝您的協助！</p>
          </div>";
    exit;
}

// 自傳檔案
$autobiStmt = $pdo->prepare("
  SELECT original_name, path
  FROM application_files
  WHERE apno = ? AND file_type = 'autobi'
");
$autobiStmt->execute([$recommendation['application_id']]);
$autobiFiles = $autobiStmt->fetchAll(PDO::FETCH_ASSOC);

// 其他檔案
$otherStmt = $pdo->prepare("
  SELECT original_name, path
  FROM application_files
  WHERE apno = ? AND file_type = 'support'
");
$otherStmt->execute([$recommendation['application_id']]);
$otherFiles = $otherStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!--
// 顯示學生資料 
echo "<h3>學生姓名：" . htmlspecialchars($row['student_name']) . "</h3>"; 
echo "<p>申請獎助學金：" . htmlspecialchars($row['scholarship_name']) . "</p>";  
echo "<p>成績：" . htmlspecialchars($row['GRADE']) . "</p>"; 
echo "<p>班排：" . htmlspecialchars($row['RANK']) . "</p>"; 
echo "<p>自傳：" . nl2br(htmlspecialchars($row['AUTOBI'])) . "</p>";-->

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>填寫推薦信 - 獎助學金系統</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; font-family: "Segoe UI","Noto Sans TC",sans-serif; } 
        .card { border-radius: 12px; }
        /*
        body {
            background-color: #f0f2f5;
            font-family: "Segoe UI", "Noto Sans TC", sans-serif;
        }
        .form-card {
            max-width: 600px;
            margin: 60px auto;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .form-title {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 20px;
            color: #0d6efd;
        }
        .form-label {
            font-weight: 500;
        }
        .btn-primary {
            font-size: 1.1rem;
            padding: 10px;
        }*/
    </style>
</head>
<body>
    <div class="container mt-5">
        <!-- 標題在卡片外 -->
        <div class="text-center mb-4">
            <h2 class="text-dark fw-bold">填寫推薦信</h2>
        </div>

        <!-- 卡片區塊 -->
        <div class="card shadow mx-auto" style="max-width: 700px;">
            <div class="card-body">
                <!-- 顯示學生資料 -->
            <!--<p><strong>學生姓名：</strong>?= htmlspecialchars($row['student_name']) ?></p>
            <p><strong>成績：</strong>?= htmlspecialchars($row['GRADE']) ?></p>
            <p><strong>班排：</strong>?= htmlspecialchars($row['RANK']) ?></p>
            <p><strong>申請獎助學金：</strong>?= htmlspecialchars($row['scholarship_name']) ?></p>-->
            <!-- 顯示學生資料 -->
<p><strong>學生姓名：</strong><?= htmlspecialchars($recommendation['student_name']) ?></p>
<p><strong>學生學號：</strong><?= htmlspecialchars($recommendation['STID']) ?></p>
<p><strong>成績：</strong><?= htmlspecialchars($recommendation['GRADE']) ?></p>
<p><strong>班排：</strong><?= htmlspecialchars($recommendation['RANK']) ?></p>
<p><strong>申請獎助學金：</strong><?= htmlspecialchars($recommendation['scholarship_name']) ?></p>


            <?php if (!empty($autobiFiles)): ?>
              <p><strong>自傳檔案：</strong></p>
              <ul>
                <?php foreach ($autobiFiles as $f): ?>
                  <li><?= htmlspecialchars($f['original_name']) ?>
                      <?php if (strpos($f['path'], '/scholarship/file_view.php?id=') === 0): ?>
                        <a href="<?= htmlspecialchars($f['path']) ?>" target="_blank">下載</a>
                      <?php else: ?>
                        <span class="text-muted">舊附件需重新上傳後才可下載</span>
                      <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p class="text-muted">尚未上傳自傳</p>
            <?php endif; ?>

            <?php if (!empty($otherFiles)): ?>
              <p><strong>其他有利資料：</strong></p>
              <ul>
                <?php foreach ($otherFiles as $f): ?>
                  <li><?= htmlspecialchars($f['original_name']) ?>
                      <?php if (strpos($f['path'], '/scholarship/file_view.php?id=') === 0): ?>
                        <a href="<?= htmlspecialchars($f['path']) ?>" target="_blank">下載</a>
                      <?php else: ?>
                        <span class="text-muted">舊附件需重新上傳後才可下載</span>
                      <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <!-- 推薦信表單 -->
            <form method="post" action="submit_recommendation.php" enctype="multipart/form-data">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="mb-3">
                    <label for="content" class="form-label">推薦內容</label>
                    <textarea name="content" id="content" class="form-control" rows="6" required></textarea>
                </div>
                <div class="mb-3">
                    <label for="recommendation_file" class="form-label">推薦信附件（可空）</label>
                    <input type="file" name="RECOMMENDATION_FILE" id="recommendation_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">送出推薦信</button>
                </div>
            </form>
                <!-- 提示文字放在卡片內 -->
                <!--<p class="mb-4 text-dark">
                    請為學生 <strong><?php echo htmlspecialchars($recommendation['student_name']); ?></strong> 填寫推薦內容。
                </p>

                <form method="post" action="submit_recommendation.php">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <div class="mb-3">
                        <label for="content" class="form-label">推薦內容</label>
                        <textarea name="content" id="content" class="form-control" rows="6" placeholder="請輸入推薦內容…" required></textarea>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">送出推薦信</button>
                    </div>
                </form>-->
            </div>
        </div>

        <p class="text-center text-muted mt-3">若有問題，請聯絡系辦或管理員</p>
    </div>
</body>
</html>

