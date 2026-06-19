<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "db.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/scholarship_access.php";
require_once __DIR__ . "/../mail_helpers.php"; // 引入寄信引擎模組

organization_require_scholarship_manager();

$application_id = isset($_POST['application_id']) ? $_POST['application_id'] : null;
$scholarship_id = isset($_POST['scholarship_id']) ? $_POST['scholarship_id'] : null;
$new_status     = isset($_POST['status']) ? $_POST['status'] : null;
$reject_reason  = isset($_POST['reject_reason']) ? trim($_POST['reject_reason']) : '';

if (!$application_id || !$scholarship_id || !$new_status) {
    die("❌ 缺少必要的參數，請回到上一頁重試。");
}

$managedScholarship = organization_fetch_managed_scholarship($pdo, $scholarship_id);
if (!$managedScholarship) {
    die("❌ 找不到該獎助學金或您無權限處理。");
}
$provider_id = $managedScholarship['provider_id'];

// 1. 更新申請狀態
$sql = "UPDATE application a
        JOIN scholarship s ON a.SCID = s.id
        SET a.RESULT = ?
        WHERE a.APNO = ? AND s.id = ? AND s.provider_id = ?";
$stmt = $pdo->prepare($sql);
$ok = $stmt->execute(array($new_status, $application_id, $scholarship_id, $provider_id));

// 2. 寄發 Email 通知邏輯
$mailSent = false;
if ($ok) {
    // 撈取學生 Email、姓名與獎學金名稱
    $info_sql = "SELECT u.EMAIL, u.NAME AS student_name, s.NAME AS scholarship_name
                 FROM application a
                 JOIN users u ON a.STID = u.ID
                 JOIN scholarship s ON a.SCID = s.id
                 WHERE a.APNO = ?";
    $info_stmt = $pdo->prepare($info_sql);
    $info_stmt->execute([$application_id]);
    $info = $info_stmt->fetch(PDO::FETCH_ASSOC);

    // 確認有撈到資料且 Email 不為空
    if ($info && !empty($info['EMAIL'])) {
        $toEmail = $info['EMAIL'];
        $toName = $info['student_name'];
        $scholarshipName = $info['scholarship_name'];
        
        $subject = "【系統通知】獎助學金申請狀態更新：" . $scholarshipName;
        
        // 根據不同狀態組合對應的信件內容
        $htmlBody = "<p>親愛的 {$toName} 同學您好：</p>";
        $htmlBody .= "<p>您申請的獎助學金 <strong>{$scholarshipName}</strong> 狀態已更新為：<span style='color:#0d6efd; font-weight:bold;'>{$new_status}</span></p>";
        
        if ($new_status === '需補件') {
            $htmlBody .= "<p>請您盡速登入系統完成補件作業，以免影響您的申請權益。</p>";
           if ($reject_reason !== '') {
                $htmlBody .= "<div style='padding: 12px; background-color: #f8d7da; color: #842029; border-left: 4px solid #dc3545; border-radius: 4px; margin-top: 15px;'>";
                $htmlBody .= "<strong>📝 單位補件要求／留言：</strong><br>";
                $htmlBody .= nl2br(htmlspecialchars($reject_reason));
                $htmlBody .= "</div>";
            }
        } elseif ($new_status === '通過') {
            $htmlBody .= "<p>恭喜您通過審查！後續發放事宜將依單位公告為準。</p>";
        } elseif ($new_status === '不通過') {
            $htmlBody .= "<p>感謝您的申請，很遺憾本次未獲通過，祝您學業順利。</p>";
        }
        
        $htmlBody .= "<p><br>系統自動發送，請勿直接回覆本信件。</p>";

        // 呼叫 mail_helpers.php 裡的函式執行寄信
        $mailSent = scholarship_send_mail($toEmail, $toName, $subject, $htmlBody);
    }
}

$pageTitle = "更新結果";
$activeNav = "view_applicants.php";
require __DIR__ . "/../header.php";
?>
    <h1 class="h3 fw-bold mb-4">更新結果</h1>

    <?php if ($ok): ?>
        <div class="card border-success shadow-sm mb-4">
            <div class="card-body text-success">
                <h5 class="card-title">更新成功</h5>
                <p class="card-text">
                    申請編號 <strong><?php echo htmlspecialchars($application_id); ?></strong> 的狀態已更新為「<?php echo htmlspecialchars($new_status); ?>」。
                </p>
                
                <?php if ($mailSent): ?>
                    <p class="text-primary small mb-3">📧 已同步發送 Email 通知給申請學生。</p>
                <?php else: ?>
                    <p class="text-warning small mb-3">⚠️ 狀態已更新，但 Email 通知發送失敗或學生未設定信箱。</p>
                <?php endif; ?>

                <p id="countdown" class="text-muted">
                    將在 <span id="seconds">5</span> 秒後返回申請清單...
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-danger shadow-sm mb-4">
            <div class="card-body text-danger">
                <h5 class="card-title">更新失敗</h5>
                <p class="card-text">請檢查資料或稍後再試。</p>
                <pre class="text-muted"><?php print_r($stmt->errorInfo()); ?></pre>
            </div>
        </div>
    <?php endif; ?>

    <a href="view_applicants.php?provider_id=<?php echo urlencode($provider_id); ?>&scholarship_id=<?php echo urlencode($scholarship_id); ?>" 
       class="btn btn-outline-primary">立即返回</a>

<script>
    // 倒數秒數
    var seconds = 5; 
    var countdownElem = document.getElementById("seconds");

    var timer = setInterval(function() {
        seconds--;
        countdownElem.textContent = seconds;
        if (seconds <= 0) {
            clearInterval(timer);
            window.location.href = "view_applicants.php?provider_id=<?php echo urlencode($provider_id); ?>&scholarship_id=<?php echo urlencode($scholarship_id); ?>";
        }
    }, 1000);
</script>
</main>
</body>
</html>
