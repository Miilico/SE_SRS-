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
require __DIR__ . "/../header.php";
?>


<div class="row justify-content-center">
    <div class="col-lg-8 col-md-10">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
            <h1 class="h3 fw-bold mb-4">新增獎助學金</h1>

            <form action="insert_scholarship.php" enctype="multipart/form-data" method="post" class="vstack gap-3">
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

                <div class="card bg-light border-0 mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">🛠️ 學生申請表單——自訂收集項目設定</h5>
                            <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" id="btn-add-custom-field">
                                ＋ 增加審查項目
                            </button>
                        </div>
                
                    <div id="custom-fields-container" class="vstack gap-3">
                    </div>
                    
                    <div class="text-muted small mt-2">
                        💡 提示：您可以根據此獎學金的需求，要求學生填寫特定文字、數字或上傳相關證明文件（如：清寒證明 PDF）。
                    </div>
                    </div>
                </div>
                <div>
                    <label class="form-label fw-semibold">上傳官方附件 (非必填)</label>
                    <input class="form-control" type="file" name="scholarship_attachment" accept=".pdf,.doc,.docx,.zip">
                    <div class="form-text">可上傳獎學金簡章、空白切結書或推薦信公版供學生下載 (限制 PDF/Word/ZIP)。</div>
                </div>
                <div class="d-flex flex-column flex-sm-row gap-2 pt-2">
                    <button type="submit" class="btn btn-primary">新增獎助學金</button>
                </div>
            </form>
            </div>
        </div>
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

// 動態計數器，用來產生唯一的 ID
let fieldIdx = 0;

document.getElementById('btn-add-custom-field').addEventListener('click', function() {
    fieldIdx++;
    const container = document.getElementById('custom-fields-container');
    
    // 建立新的一列項目
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-center bg-white p-3 rounded border position-relative';
    row.id = 'custom-field-row-' + fieldIdx;
    
    row.innerHTML = `
        <div class="col-md-5">
            <label class="form-label small text-secondary fw-semibold">項目名稱 (例如：多益成績單、清寒證明)</label>
            <input type="text" name="custom_labels[]" class="form-control" placeholder="請輸入項目名稱" required>
        </div>
        <div class="col-md-4">
            <label class="form-label small text-secondary fw-semibold">欄位型態</label>
            <select name="custom_types[]" class="form-select">
                <option value="text">單行文字輸入框</option>
                <option value="number">整數輸入框</option>
                <option value="textarea">多行文字區塊</option>
                <option value="file">檔案上傳 (限制 PDF/JPG/PNG 10MB 內)</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small text-secondary fw-semibold">是否必填</label>
            <select name="custom_required[]" class="form-select">
                <option value="1">必填</option>
                <option value="0">選填</option>
            </select>
        </div>
        <div class="col-md-1 text-end mt-4">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="document.getElementById('custom-field-row-${fieldIdx}').remove()">
                移除
            </button>
        </div>
    `;
    
    container.appendChild(row);
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
