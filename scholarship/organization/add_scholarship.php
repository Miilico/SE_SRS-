<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/scholarship_access.php";

organization_require_scholarship_manager();

$isAdmin = organization_is_admin();
$providerOptions = $isAdmin ? organization_provider_options($pdo) : array();

// 檢查是否有成功或錯誤訊息
$success = isset($_GET['success']);
$error   = isset($_GET['error']) ? $_GET['error'] : '';

$pageTitle = "新增獎助學金";
$activeNav = "add_scholarship.php";
$siteHeaderRequiredRole = array(3, 4);
require __DIR__ . "/../header.php";
?>


<div class="row justify-content-center">
    <div class="col-lg-8 col-md-10">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
            <h1 class="h3 fw-bold mb-4">新增獎助學金</h1>

            <form action="insert_scholarship.php" enctype="multipart/form-data" method="post" class="vstack gap-3">
                <?php if ($isAdmin): ?>
                    <div>
                        <label class="form-label fw-semibold">發布獎助單位</label>
                        <select class="form-select" name="provider_id" required>
                            <option value="" selected disabled>請選擇獎助單位</option>
                            <?php foreach ($providerOptions as $provider): ?>
                                <option value="<?php echo htmlspecialchars($provider["ID"]); ?>">
                                    <?php echo htmlspecialchars($provider["provider_name"] . "（" . $provider["ID"] . "）"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">管理員新增時，獎助學金會掛在所選獎助單位名下。</div>
                    </div>
                <?php endif; ?>

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

                    <div class="mb-3">
                        <div class="small fw-semibold text-secondary mb-2">建議欄位</div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-custom-preset data-label="在學證明" data-type="file" data-required="1">在學證明</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-custom-preset data-label="大學期間成績單" data-type="file" data-required="1">大學期間成績單</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-custom-preset data-label="語言能力證明" data-type="file" data-required="0">語言能力證明</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-custom-preset data-label="讀書計畫" data-type="file" data-required="1">讀書計畫</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-custom-preset data-label="自傳" data-type="file" data-required="1">自傳</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-custom-preset data-label="其他有利證明" data-type="file" data-required="0">其他有利證明</button>
                        </div>
                    </div>
                
                    <div id="custom-fields-container" class="vstack gap-3">
                    </div>
                    
                    <div class="text-muted small mt-2">
                        💡 提示：您可以根據此獎學金的需求，要求學生填寫特定文字、數字或上傳相關證明文件（如：清寒證明 PDF）。
                        GPA／成績、班排／系排與推薦信已是系統固定欄位，不需重複新增。
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

// 顯示 Modal 提示
document.addEventListener("DOMContentLoaded", function() {
    <?php if ($error || $success): ?>
        var myModal = new bootstrap.Modal(document.getElementById('msgModal'));
        myModal.show();
    <?php endif; ?>
});


</script>
<script src="/scholarship/organization/custom_field_builder.js"></script>

</main>
</body>
</html>
