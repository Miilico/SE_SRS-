<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";

require_role(4);

$pageTitle = "新增獎助學金";
$activeNav = "add_scholarship.php";
$siteHeaderRequiredRole = 4;
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

</script>

</main>
</body>
</html>
