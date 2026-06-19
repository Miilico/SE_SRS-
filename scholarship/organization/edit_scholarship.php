<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once "db.php";
require_once __DIR__ . "/scholarship_access.php";

organization_require_scholarship_manager();

$isAdmin = organization_is_admin();
$scholarship_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$scholarship_id) {
    die("❌ 缺少必要的參數。");
}

// 1. 取得獎學金基本資料
$scholarship = organization_fetch_managed_scholarship($pdo, $scholarship_id);

if (!$scholarship) {
    die("❌ 找不到該筆獎學金或您無權限編輯。");
}

$provider_id = $scholarship['provider_id'];

// 2. 取得自訂表單欄位資料
$field_sql = "SELECT * FROM scholarship_fields WHERE scholarship_id = ?";
$field_stmt = $pdo->prepare($field_sql);
$field_stmt->execute([$scholarship_id]);
$custom_fields = $field_stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. 取得目前已上傳的官方附件 (改去新表撈取)
$file_sql = "SELECT * FROM scholarship_attachments WHERE scholarship_id = ? ORDER BY id DESC LIMIT 1";
$file_stmt = $pdo->prepare($file_sql);
$file_stmt->execute([$scholarship_id]);
$current_attachment = $file_stmt->fetch(PDO::FETCH_ASSOC);

$success = isset($_GET['success']);
$error   = isset($_GET['error']) ? $_GET['error'] : '';

$pageTitle = "編輯獎助學金";
$activeNav = "my_scholarships.php"; // 保持側邊欄亮在我的清單
$siteHeaderRequiredRole = array(3, 4);

require __DIR__ . "/../header.php";
?>

<div class="row justify-content-center">
    <div class="col-lg-8 col-md-10">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 fw-bold mb-0">編輯獎助學金</h1>
                    <a href="my_scholarships.php" class="btn btn-outline-secondary btn-sm">返回清單</a>
                </div>
                <?php if ($isAdmin): ?>
                    <div class="alert alert-info">
                        目前編輯發布單位：<?php echo htmlspecialchars($scholarship['provider_name']); ?>（<?php echo htmlspecialchars($provider_id); ?>）
                    </div>
                <?php endif; ?>

                <form action="update_scholarship.php" method="post" class="vstack gap-3" enctype="multipart/form-data">
                    <input type="hidden" name="scholarship_id" value="<?= htmlspecialchars($scholarship_id) ?>">

                    <div>
                        <label class="form-label fw-semibold">獎助學金名稱</label>
                        <input class="form-control" type="text" name="scholarship_name" value="<?= htmlspecialchars($scholarship['NAME']) ?>" required>
                    </div>

                    <div>
                        <label class="form-label fw-semibold">獎助金額</label>
                        <input class="form-control" type="number" name="amount" value="<?= htmlspecialchars($scholarship['AMOUNT']) ?>" required>
                    </div>

                    <div>
                        <label class="form-label fw-semibold">申請條件</label>
                        <textarea class="form-control" name="conditions" rows="4"><?= htmlspecialchars($scholarship['CONDI']) ?></textarea>
                    </div>

                    <div>
                        <label class="form-label fw-semibold">申請開始日期</label>
                        <input class="form-control" type="date" name="start_date" value="<?= htmlspecialchars($scholarship['start_date']) ?>" required>
                    </div>

                    <div>
                        <label class="form-label fw-semibold">申請截止日期</label>
                        <input class="form-control" type="date" name="deadline" value="<?= htmlspecialchars($scholarship['DEADLINE']) ?>" required>
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
                                <?php foreach ($custom_fields as $index => $field): ?>
                                    <div class="row g-2 align-items-center bg-white p-3 rounded border position-relative" id="custom-field-row-<?= $index ?>">
                                        <div class="col-md-5">
                                            <label class="form-label small text-secondary fw-semibold">項目名稱</label>
                                            <input type="text" name="custom_labels[]" class="form-control" value="<?= htmlspecialchars($field['field_label']) ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small text-secondary fw-semibold">欄位型態</label>
                                            <select name="custom_types[]" class="form-select">
                                                <option value="text" <?= $field['field_type'] == 'text' ? 'selected' : '' ?>>單行文字輸入框</option>
                                                <option value="number" <?= $field['field_type'] == 'number' ? 'selected' : '' ?>>數字輸入框</option>
                                                <option value="textarea" <?= $field['field_type'] == 'textarea' ? 'selected' : '' ?>>多行文字區塊</option>
                                                <option value="file" <?= $field['field_type'] == 'file' ? 'selected' : '' ?>>檔案上傳</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small text-secondary fw-semibold">是否必填</label>
                                            <select name="custom_required[]" class="form-select">
                                                <option value="1" <?= $field['is_required'] == 1 ? 'selected' : '' ?>>必填</option>
                                                <option value="0" <?= $field['is_required'] == 0 ? 'selected' : '' ?>>選填</option>
                                            </select>
                                        </div>
                                        <div class="col-md-1 text-end mt-4">
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="document.getElementById('custom-field-row-<?= $index ?>').remove()">移除</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label fw-semibold">上傳官方附件 (簡章/切結書公版)</label>
                        <?php if ($current_attachment): ?>
                            <div class="mb-2">
                                <span class="badge bg-secondary">目前已有附件</span>
                                <a href="<?= htmlspecialchars($current_attachment['file_path']) ?>" target="_blank" class="ms-1 fw-bold text-decoration-none">
                                    📄 <?= htmlspecialchars($current_attachment['original_name']) ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        <input class="form-control" type="file" name="scholarship_attachment" accept=".pdf,.doc,.docx,.zip">
                        <div class="form-text">若不需更換請留空。若上傳新檔案，將會自動覆蓋原本的附件。限制 PDF/Word/ZIP。</div>
                    </div>
                    
                    
                    <div class="d-flex flex-column flex-sm-row gap-2 pt-2">
                        <button type="submit" class="btn btn-success">儲存修改</button>
                    </div>
                </form>
            </div>
        </div>
   </div>
</div>
<div class="modal fade" id="msgModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">系統提示</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php if ($error): ?>
            <p class="text-danger">錯誤：<?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="text-success">更新成功！</p>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">關閉</button>
      </div>
    </div>
  </div>
</div>

<script>
// 前端日期檢查
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

// 動態計數器，確保新增的列 ID 不會重複
let fieldIdx = <?= count($custom_fields) ?>;

document.getElementById('btn-add-custom-field').addEventListener('click', function() {
    fieldIdx++;
    const container = document.getElementById('custom-fields-container');
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-center bg-white p-3 rounded border position-relative';
    row.id = 'custom-field-row-' + fieldIdx;
    
    row.innerHTML = `
        <div class="col-md-5">
            <label class="form-label small text-secondary fw-semibold">項目名稱</label>
            <input type="text" name="custom_labels[]" class="form-control" placeholder="請輸入項目名稱" required>
        </div>
        <div class="col-md-4">
            <label class="form-label small text-secondary fw-semibold">欄位型態</label>
            <select name="custom_types[]" class="form-select">
                <option value="text">單行文字輸入框</option>
                <option value="number">數字輸入框</option>
                <option value="textarea">多行文字區塊</option>
                <option value="file">檔案上傳</option>
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
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="document.getElementById('custom-field-row-${fieldIdx}').remove()">移除</button>
        </div>
    `;
    container.appendChild(row);
});

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
