<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "db.php";
require_once __DIR__ . "/../file_helpers.php";
ensure_application_files_table($pdo);

// 取得 provider_id
if (isset($_SESSION['user']['id'])) {
    $provider_id = $_SESSION['user']['id'];
} else {
    $provider_id = isset($_GET['provider_id']) ? $_GET['provider_id'] : null;
}

if (!$provider_id) {
    die("請先登入或在網址加上 provider_id 參數，例如 ?provider_id=S0000001");
}

// 取得獎學金清單
$sql = "SELECT id, NAME FROM scholarship WHERE provider_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute(array($provider_id));
$scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 使用者選擇的獎學金
$scholarship_id = isset($_GET['scholarship_id']) ? $_GET['scholarship_id'] : 0;
if ($scholarship_id == 0 && !empty($scholarships)) {
    $scholarship_id = $scholarships[0]['id'];
}
$rows = array();

if ($scholarship_id) {
    $sql = "SELECT a.APNO AS app_id, a.AUTOBI, a.RANK, a.GRADE, a.RESULT,
                   u.name AS student_name, s.NAME AS scholarship_name,
                   r.content AS recommendation,
                   tu.name AS teacher_name, t.DNAME AS department_name
            FROM application a
            JOIN scholarship s ON a.SCID = s.id
            JOIN users u ON a.STID = u.ID
            LEFT JOIN recommendations r ON a.APNO = r.application_id
            LEFT JOIN users tu ON r.teacher_id = tu.ID
            LEFT JOIN teachers t ON r.teacher_id = t.ID
            WHERE s.id = ? AND s.provider_id = ?";
    $stmt = $pdo->prepare($sql);
    if (!$stmt->execute(array($scholarship_id, $provider_id))) {
        print_r($stmt->errorInfo());
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = "瀏覽申請資料";
$activeNav = "view_applicants.php";
require __DIR__ . "/../header.php";
?>
    <h1 class="h3 fw-bold mb-4">瀏覽申請資料</h1>

    <!-- 獎學金選單 -->
    <form method="get" action="view_applicants.php" class="mb-4 d-flex gap-2 align-items-center">
        <input type="hidden" name="provider_id" value="<?php echo htmlspecialchars($provider_id); ?>">
        <label for="scholarship_id" class="form-label mb-0">選擇獎助學金：</label>
        <select name="scholarship_id" id="scholarship_id" class="form-select w-auto" onchange="this.form.submit()">
    <?php foreach ($scholarships as $s): ?>
        <option value="<?php echo $s['id']; ?>" <?php echo ($s['id'] == $scholarship_id) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($s['NAME']); ?>
        </option>
    <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn-primary">瀏覽</button></noscript>

    </form>

    <!-- 顯示申請資料 -->
    <?php if ($scholarship_id && count($rows) === 0): ?>
        <div class="alert alert-warning">目前沒有申請資料。</div>
    <?php elseif ($scholarship_id): ?>
        <div class="row row-cols-1 row-cols-md-2 g-4">
            <?php foreach ($rows as $row): ?>
                <?php 
                // 查詢該申請的自傳
                $autobiStmt = $pdo->prepare("
                    SELECT original_name, path
                    FROM application_files
                    WHERE apno = ? AND file_type = 'autobi'
                ");
                $autobiStmt->execute([$row['app_id']]);
                $autobiFiles = $autobiStmt->fetchAll(PDO::FETCH_ASSOC);

                // 查詢該申請的其他檔案 
                $fileStmt = $pdo->prepare(" 
                    SELECT original_name, file_type, path
                    FROM application_files
                    WHERE apno = ? AND file_type = 'support'
                "); 
                $fileStmt->execute([$row['app_id']]); 
                $files = $fileStmt->fetchAll(PDO::FETCH_ASSOC); 

                $recFileStmt = $pdo->prepare("
                    SELECT id, original_name
                    FROM application_files
                    WHERE application_id = ? AND file_category = 4
                    ORDER BY created_at ASC, id ASC
                ");
                $recFileStmt->execute([$row['app_id']]);
                $recommendationFiles = $recFileStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <div class="col">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title">申請學生：<?php echo htmlspecialchars($row['student_name']); ?></h5>
                            <!--<p><strong>自傳：</strong><br>?php echo nl2br(htmlspecialchars($row['AUTOBI'])); ?></p>-->
                            <p><strong>排名：</strong> <?php echo htmlspecialchars($row['RANK']); ?></p>
                            <p><strong>成績：</strong> <?php echo htmlspecialchars($row['GRADE']); ?></p>
                            <p><strong>推薦老師：</strong> <?php echo htmlspecialchars($row['teacher_name']); ?> <?php echo htmlspecialchars($row['department_name']); ?></p>
                            <p><strong>推薦信內容：</strong><br><?php echo nl2br(htmlspecialchars($row['recommendation'])); ?></p>
                            <?php if (!empty($recommendationFiles)): ?>
                            <p><strong>推薦信附件：</strong></p>
                            <ul class="list-group mb-3">
                                <?php foreach ($recommendationFiles as $f): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?= htmlspecialchars($f['original_name']) ?>
                                        <a href="/scholarship/file_view.php?id=<?= urlencode($f['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">下載</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                            <?php if (!empty($autobiFiles)): ?>
  
                            <p><strong>自傳檔案：</strong></p>
                                <ul class="list-group mb-3">
                                <?php foreach ($autobiFiles as $f): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($f['original_name']) ?>
                                <?php if (strpos($f['path'], '/scholarship/file_view.php?id=') === 0): ?>
                                <a href="<?= htmlspecialchars($f['path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">下載</a>
                                <?php else: ?>
                                <span class="text-muted small">舊附件需重新上傳</span>
                                <?php endif; ?>
                                </li>
                                <?php endforeach; ?>
                                </ul>
                                <?php else: ?>
                            <p class="text-muted">尚未上傳自傳</p>
                            <?php endif; ?>

                            <!-- 顯示其他檔案 -->
                          <?php if (!empty($files)): ?>
                            <p><strong>其他上傳資料：</strong></p>
                            <ul class="list-group mb-3">
                                <?php foreach ($files as $f): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <!--?= htmlspecialchars($f['file_type']) ?>-->
                                        <?= htmlspecialchars($f['original_name']) ?>
                                        <?php if (strpos($f['path'], '/scholarship/file_view.php?id=') === 0): ?>
                                        <a href="<?= htmlspecialchars($f['path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">下載</a>
                                        <?php else: ?>
                                        <span class="text-muted small">舊附件需重新上傳</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                                <?php else: ?>
                            <p class="text-muted">尚未上傳其他資料</p>
                          <?php endif; ?>

                            <p><strong>目前狀態：</strong>
                                <?php if ($row['RESULT'] === '通過'): ?>
                                    <span class="badge bg-success">通過</span>
                                <?php elseif ($row['RESULT'] === '不通過'): ?>
                                    <span class="badge bg-danger">不通過</span>
                                <?php elseif ($row['RESULT'] === '需補件'): ?>
                                    <span class="badge bg-info text-dark">需補件</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">審查中</span>
                                <?php endif; ?>
                            </p>

                            <!-- 審查表單 -->
                            <form action="review_application.php" method="post" class="d-flex gap-2">
                                <input type="hidden" name="application_id" value="<?php echo $row['app_id']; ?>">
                                <input type="hidden" name="scholarship_id" value="<?php echo $scholarship_id; ?>">
                                <input type="hidden" name="provider_id" value="<?php echo htmlspecialchars($provider_id); ?>">
                                <select name="status" class="form-select w-auto">
                                    <option value="通過">通過</option>
                                    <option value="不通過">不通過</option>
                                    <option value="需補件">需補件</option>
                                </select>
                                <button type="submit" class="btn btn-outline-primary">更新結果</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>
</body>
</html>


