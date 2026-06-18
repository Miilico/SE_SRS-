<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once "db.php";

require_role(4);

$provider_id = $_SESSION['user']['id'];
$scholarship_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$scholarship_id) {
    die("缺少獎助學金參數。");
}

// 驗證並取得獎學金名稱
$sql_sc = "SELECT NAME FROM scholarship WHERE id = ? AND provider_id = ?";
$stmt_sc = $pdo->prepare($sql_sc);
$stmt_sc->execute([$scholarship_id, $provider_id]);
$scholarship = $stmt_sc->fetch(PDO::FETCH_ASSOC);

if (!$scholarship) {
    die("找不到該獎助學金或無權限。");
}

// 取得所有系所清單供篩選 (排除空白)
$sql_dept = "SELECT DISTINCT DNAME FROM students WHERE DNAME IS NOT NULL AND DNAME != '' ORDER BY DNAME";
$stmt_dept = $pdo->query($sql_dept);
$departments = $stmt_dept->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = "發送廣播通知";
$activeNav = "my_scholarships.php";
$siteHeaderRequiredRole = 4;
$siteHeaderMaxWidth = "760px";
require __DIR__ . "/../header.php";
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4 p-md-5">
        <h1 class="h3 fw-bold mb-4">📣 發送新獎助學金廣播</h1>
        <div class="alert alert-info">
            您即將為 <strong><?= htmlspecialchars($scholarship['NAME']) ?></strong> 發送開放申請通知。<br>請選擇要通知的目標學生群體。
        </div>

        <form action="send_broadcast.php" method="post">
            <input type="hidden" name="scholarship_id" value="<?= htmlspecialchars($scholarship_id) ?>">

            <div class="mb-4">
                <label class="form-label fw-semibold">目標對象篩選</label>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="target_type" id="targetAll" value="all" checked onchange="document.getElementById('dept-selection').style.display='none';">
                    <label class="form-check-label" for="targetAll">
                        全校學生 (發送給系統內所有啟用帳號的學生)
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="target_type" id="targetDept" value="dept" onchange="document.getElementById('dept-selection').style.display='block';">
                    <label class="form-check-label" for="targetDept">
                        特定系所 (僅發送給勾選的系所)
                    </label>
                </div>
            </div>

            <div id="dept-selection" class="mb-4 p-3 border rounded bg-light" style="display:none;">
                <label class="form-label fw-semibold">請勾選目標系所：</label>
                <div class="row row-cols-2 row-cols-md-3 g-2">
                    <?php foreach ($departments as $dept): ?>
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="departments[]" value="<?= htmlspecialchars($dept) ?>" id="dept_<?= htmlspecialchars($dept) ?>">
                                <label class="form-check-label" for="dept_<?= htmlspecialchars($dept) ?>">
                                    <?= htmlspecialchars($dept) ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <a href="my_scholarships.php" class="btn btn-outline-secondary">取消</a>
                <button type="submit" class="btn btn-primary" onclick="this.innerHTML='發送中，請勿關閉視窗...'; this.disabled=true; this.form.submit();">
                    🚀 確認發送廣播信件
                </button>
            </div>
        </form>
    </div>
</div>
</main>
</body>
</html>