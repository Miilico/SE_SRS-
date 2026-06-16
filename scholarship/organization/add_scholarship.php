<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";

require_role(4);

// 檢查是否有成功或錯誤訊息
$success = isset($_GET['success']);
$error   = isset($_GET['error']) ? $_GET['error'] : '';

$pageTitle = "新增獎助學金";
$activeNav = "add_scholarship.php";
$siteHeaderRequiredRole = 4;
$siteHeaderMaxWidth = "760px";
require __DIR__ . "/../header.php";
?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4 p-md-5">
    <h1 class="h3 fw-bold mb-4">新增獎助學金</h1>

    <form action="insert_scholarship.php" method="post" class="vstack gap-3">
        <div>
            <label class="form-label fw-semibold">獎助學金名稱</label>
            <input class="form-control" type="text" name="scholarship_name" placeholder="請輸入獎助學金名稱" required>
        </div>

        <div>
            <label class="form-label fw-semibold">獎助金額</label>
            <input class="form-control" type="number" name="amount" placeholder="請輸入獎助金額" required>
        </div>

        <div>
            <label class="form-label fw-semibold">申請條件</label>
            <textarea class="form-control" name="conditions" rows="4" placeholder="請輸入申請限制條件"></textarea>
        </div>

        <div>
            <label class="form-label fw-semibold">申請開始日期</label>
            <input class="form-control" type="date" name="start_date" required>
        </div>

        <div>
            <label class="form-label fw-semibold">申請截止日期</label>
            <input class="form-control" type="date" name="deadline" required>
        </div>

        <div class="d-flex flex-column flex-sm-row gap-2 pt-2">
            <button type="submit" class="btn btn-primary">新增獎助學金</button>
        </div>
    </form>
    </div>
</div>

<!-- Bootstrap Modal -->
<div class="modal fade" id="msgModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">系統提示</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php if ($error): ?>
            <p class="text-danger">錯誤：<?php echo $error; ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="text-success">新增成功！</p>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">關閉</button>
      </div>
    </div>
  </div>
</div>

<!-- 前端檢查 -->
<script>
document.querySelector("form").addEventListener("submit", function(e) {
    const start = new Date(document.querySelector("[name=start_date]").value);
    const end   = new Date(document.querySelector("[name=deadline]").value);
    const amount = document.querySelector("[name=amount]").value;

    if (start > end) {
        e.preventDefault();
        alert("開始日期不能晚於截止日期！");
        return;
    }
    if (!amount || isNaN(amount) || Number(amount) <= 0) {
        e.preventDefault();
        alert("金額必須為正數！");
    }
});

// 顯示 Modal 提示
document.addEventListener("DOMContentLoaded", function() {
    <?php if ($error || $success): ?>
        var myModal = new bootstrap.Modal(document.getElementById('msgModal'));
        myModal.show();
    <?php endif; ?>
});
</script>

</main>
</body>
</html>
