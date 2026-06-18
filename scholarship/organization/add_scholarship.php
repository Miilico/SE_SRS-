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
            <label class="form-label fw-semibold">獎助學金名稱 <span class="text-danger" aria-label="必填">*</span></label>
            <input class="form-control" type="text" name="scholarship_name" placeholder="請輸入獎助學金名稱" required>
        </div>

        <div>
            <label class="form-label fw-semibold">獎助金額 <span class="text-danger" aria-label="必填">*</span></label>
            <input class="form-control" type="number" name="amount" placeholder="請輸入獎助金額" required>
        </div>

        <div>
            <label class="form-label fw-semibold">申請條件</label>
            <textarea class="form-control" name="conditions" rows="4" placeholder="請輸入申請限制條件"></textarea>
        </div>

        <div>
            <label class="form-label fw-semibold">申請開始日期 <span class="text-danger" aria-label="必填">*</span></label>
            <input class="form-control" type="date" name="start_date" required>
        </div>

        <div>
            <label class="form-label fw-semibold">申請截止日期 <span class="text-danger" aria-label="必填">*</span></label>
            <input class="form-control" type="date" name="deadline" required>
        </div>

        <section class="border-top pt-4 mt-2" aria-labelledby="custom-fields-title">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
                <div>
                    <h2 class="h5 fw-bold mb-1" id="custom-fields-title">學生申請自訂欄位</h2>
                    <div class="text-secondary small">依這項獎助學金需求，新增文字、數字、說明或證明文件欄位。</div>
                </div>
                <button type="button" class="btn btn-outline-primary" id="add-custom-field">新增欄位</button>
            </div>
            <div class="vstack gap-3" id="custom-fields-container"></div>
        </section>

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

document.getElementById("add-custom-field").addEventListener("click", function () {
    const row = document.createElement("div");
    row.className = "border rounded p-3";
    row.innerHTML = `
        <div class="row g-3 align-items-end">
            <div class="col-lg-5">
                <label class="form-label fw-semibold">欄位名稱</label>
                <input class="form-control" name="custom_labels[]" maxlength="255"
                       placeholder="例如：多益成績、清寒證明" required>
            </div>
            <div class="col-lg-3">
                <label class="form-label fw-semibold">欄位型態</label>
                <select class="form-select" name="custom_types[]">
                    <option value="text">單行文字</option>
                    <option value="number">數字</option>
                    <option value="textarea">多行文字</option>
                    <option value="file">檔案上傳</option>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label fw-semibold">填寫要求</label>
                <select class="form-select" name="custom_required[]">
                    <option value="1">必填</option>
                    <option value="0">選填</option>
                </select>
            </div>
            <div class="col-lg-2 text-lg-end">
                <button type="button" class="btn btn-outline-danger remove-custom-field">移除</button>
            </div>
        </div>`;

    row.querySelector(".remove-custom-field").addEventListener("click", function () {
        row.remove();
    });
    document.getElementById("custom-fields-container").appendChild(row);
});

</script>

</main>
</body>
</html>
