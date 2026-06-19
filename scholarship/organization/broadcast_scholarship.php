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

// 🔽 修改 1：原本只撈 NAME，現在把 AMOUNT 和 DEADLINE 也撈出來供預覽信件使用
$sql_sc = "SELECT NAME, AMOUNT, DEADLINE FROM scholarship WHERE id = ? AND provider_id = ?";
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

        <form id="broadcastForm" action="send_broadcast.php" method="post">
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
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#previewModal">
                    👁️ 預覽信件內容
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="previewModalLabel">👁️ 廣播信件預覽</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="border rounded p-4 shadow-sm bg-white" style="font-family: sans-serif;">
                    <p>親愛的 <strong>{學生姓名}</strong> 同學您好：</p>
                    <p>系統已發佈新的獎助學金：<strong style='color:#0d6efd;'><?= htmlspecialchars($scholarship['NAME']) ?></strong>，歡迎符合資格的同學踴躍申請！</p>
                    <ul style='background-color:#f8f9fa; padding: 15px 30px; border-radius: 5px;'>
                        <li><strong>獎助金額：</strong> <?= htmlspecialchars($scholarship['AMOUNT']) ?> 元</li>
                        <li><strong>申請截止日期：</strong> <?= htmlspecialchars($scholarship['DEADLINE']) ?></li>
                    </ul>
                    <p>詳細申請條件與辦法，請登入「高大獎助學金系統」查看並線上提交申請表。</p>
                    <p><br>系統自動發送，請勿直接回覆本信件。</p>
                </div>
                <div class="alert alert-warning mt-4 mb-0 small">
                    ⚠️ <strong>注意：</strong> 系統將會自動替換 <strong>{學生姓名}</strong> 為實際收件者。批次發送可能需要一些時間，點擊確認後請耐心等候，勿關閉視窗。
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">返回修改</button>
                <button type="button" class="btn btn-success" onclick="this.innerHTML='發送中，請稍候...'; this.disabled=true; document.getElementById('broadcastForm').submit();">
                    🚀 確認內容無誤並發送
                </button>
            </div>
        </div>
    </div>
</div>

</main>