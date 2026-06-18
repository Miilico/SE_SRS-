<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "db.php";

$application_id = isset($_POST['application_id']) ? $_POST['application_id'] : null;
$scholarship_id = isset($_POST['scholarship_id']) ? $_POST['scholarship_id'] : null;
$provider_id    = isset($_POST['provider_id']) ? $_POST['provider_id'] : null;
$new_status     = isset($_POST['status']) ? $_POST['status'] : null;

if (!$application_id || !$scholarship_id || !$provider_id || !$new_status) {
    die("❌ 缺少必要的參數，請回到上一頁重試。");
}

$sql = "UPDATE application a
        JOIN scholarship s ON a.SCID = s.id
        SET a.RESULT = ?
        WHERE a.APNO = ? AND s.provider_id = ?";
$stmt = $pdo->prepare($sql);
$ok = $stmt->execute(array($new_status, $application_id, $provider_id));

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
    var seconds = 5; // 你可以改成任何秒數
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
