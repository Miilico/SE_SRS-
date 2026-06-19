<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "db.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/scholarship_access.php";
// 若有需要引入其他 helper，請確保檔案存在。
// require_once __DIR__ . "/../file_helpers.php";
// ensure_application_files_table($pdo);

organization_require_scholarship_manager();

$isAdmin = organization_is_admin();
$provider_id = $isAdmin ? (isset($_GET['provider_id']) ? $_GET['provider_id'] : null) : organization_current_user_id();

// 2. 取得該單位所有的獎學金清單供下拉選單使用
if ($isAdmin) {
    $providerName = organization_provider_display_expr();
    if ($provider_id) {
        if (!organization_validate_provider($pdo, $provider_id)) {
            die("找不到該獎助單位。");
        }
        $sql = "SELECT s.id, s.NAME, s.provider_id, {$providerName} AS provider_name
                FROM scholarship s
                JOIN users u ON u.ID = s.provider_id AND u.ROLE = 4
                LEFT JOIN organization o ON o.ID = s.provider_id
                WHERE s.provider_id = ?
                ORDER BY s.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($provider_id));
    } else {
        $sql = "SELECT s.id, s.NAME, s.provider_id, {$providerName} AS provider_name
                FROM scholarship s
                JOIN users u ON u.ID = s.provider_id AND u.ROLE = 4
                LEFT JOIN organization o ON o.ID = s.provider_id
                ORDER BY provider_name ASC, s.NAME ASC, s.id ASC";
        $stmt = $pdo->query($sql);
    }
} else {
    $sql = "SELECT id, NAME, provider_id FROM scholarship WHERE provider_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($provider_id));
}
$scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. 取得當前選擇的獎學金 ID
$scholarship_id = isset($_GET['scholarship_id']) ? $_GET['scholarship_id'] : 0;
if ($scholarship_id == 0 && !empty($scholarships)) {
    $scholarship_id = $scholarships[0]['id'];
}

if ($scholarship_id && $isAdmin) {
    $selectedScholarship = organization_fetch_managed_scholarship($pdo, $scholarship_id);
    if (!$selectedScholarship) {
        die("找不到該獎助學金或無權限。");
    }
    $provider_id = $selectedScholarship['provider_id'];
}

$rows = array();
$custom_fields = [];

// 4. 若有選擇獎學金，撈取該獎學金的申請資料與自訂欄位題目
if ($scholarship_id) {
    // 撈取申請名單與基本資料 (加入 ?? '' 以防 null)
    $sql_applicants = "SELECT a.APNO AS app_id, a.STID AS student_id, a.RESULT, u.NAME AS student_name, a.APDATE, a.RANK, a.GRADE, a.AUTOBI, r.teacher_name, r.content AS recommendation_content
            FROM application a 
            JOIN users u ON a.STID = u.ID 
            LEFT JOIN recommendations r ON a.APNO = r.application_id
            WHERE a.SCID = ? 
            ORDER BY a.APDATE DESC";
    $stmt_applicants = $pdo->prepare($sql_applicants);
    $stmt_applicants->execute([$scholarship_id]);
    $rows = $stmt_applicants->fetchAll(PDO::FETCH_ASSOC);

    // 撈取該獎學金設定的自訂欄位題目
    $sql_fields = "SELECT id, field_label, field_type FROM scholarship_fields WHERE scholarship_id = ? ORDER BY id";
    $stmt_fields = $pdo->prepare($sql_fields);
    $stmt_fields->execute([$scholarship_id]);
    $custom_fields = $stmt_fields->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = "申請資料";
$activeNav = "view_applicants.php";
$siteHeaderMaxWidth = "1000px"; // 讓畫面寬一點較好閱讀
$siteHeaderRequiredRole = array(3, 4);
require __DIR__ . "/../header.php";
?>

<div class="container py-4">
    <h1 class="h3 fw-bold mb-4">申請資料</h1>
    
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <form method="get" action="view_applicants.php" class="d-flex gap-2 align-items-center">
            <?php if ($provider_id): ?>
                <input type="hidden" name="provider_id" value="<?php echo htmlspecialchars($provider_id); ?>">
            <?php endif; ?>
            <label for="scholarship_id" class="form-label mb-0 fw-semibold text-nowrap">選擇獎助學金：</label>
            <select name="scholarship_id" id="scholarship_id" class="form-select w-auto" onchange="this.form.submit()">
                <?php foreach ($scholarships as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $scholarship_id ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($isAdmin && isset($s['provider_name']) ? ($s['provider_name'] . " - " . $s['NAME']) : $s['NAME']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($scholarship_id): ?>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#batchUpdateModal">
                    ✏️ 批次處理狀態
                </button>
                <a href="export_applicants.php?scholarship_id=<?php echo urlencode($scholarship_id); ?>" class="btn btn-success d-flex align-items-center gap-2">
                   📊 匯出為 CSV 試算表
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['batch_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>✅ 批次處理完成！</strong> 成功更新了 <?= intval($_GET['count']) ?> 筆申請資料，並寄出了 <?= intval($_GET['mail']) ?> 封通知信。
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>❌ 錯誤：</strong> <?= htmlspecialchars($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (count($rows) === 0): ?>
        <div class="alert alert-warning">目前沒有符合條件的申請資料。</div>
    <?php else: ?>
        <div class="row row-cols-1 g-4">
            <?php foreach ($rows as $row): ?>
                <div class="col">
                    <div class="card shadow-sm border-0 border-start border-4 <?= $row['RESULT'] === '需補件' ? 'border-danger' : 'border-primary' ?>">
                        <div class="card-body">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start mb-3">
                                <div>
                                    <h5 class="card-title fw-bold text-dark mb-1">
                                        申請學生：<?php echo htmlspecialchars($row['student_name'] ?? ''); ?> 
                                        <span class="text-secondary fs-6">(<?php echo htmlspecialchars($row['student_id'] ?? ''); ?>)</span>
                                    </h5>
                                    <p class="text-muted small mb-0">申請時間：<?php echo htmlspecialchars($row['APDATE'] ?? ''); ?></p>
                                </div>
                                <div class="mt-2 mt-md-0">
                                    <span class="fw-semibold me-2">狀態：</span>
                                    <?php if ($row['RESULT'] === '通過' || $row['RESULT'] === '已獲獎'): ?>
                                        <span class="badge bg-success px-3 py-2"><?= htmlspecialchars($row['RESULT']) ?></span>
                                    <?php elseif ($row['RESULT'] === '不通過' || $row['RESULT'] === '未獲獎'): ?>
                                        <span class="badge bg-secondary px-3 py-2"><?= htmlspecialchars($row['RESULT']) ?></span>
                                    <?php elseif ($row['RESULT'] === '需補件'): ?>
                                        <span class="badge bg-danger px-3 py-2"><?= htmlspecialchars($row['RESULT']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark px-3 py-2"><?= htmlspecialchars($row['RESULT']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>成績：</strong> <?php echo htmlspecialchars($row['GRADE'] ?? '未填寫'); ?></p>
                                    <p class="mb-1"><strong>名次：</strong> <?php echo htmlspecialchars($row['RANK'] ?? '未填寫'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>自傳/說明附件：</strong></p>
                                    <?php if (!empty($row['AUTOBI'])): ?>
                                        <a href="<?php echo htmlspecialchars($row['AUTOBI']); ?>" target="_blank" class="btn btn-sm btn-outline-info">📁 查看附件</a>
                                    <?php else: ?>
                                        <span class="text-muted small">未提供</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-3 p-3 bg-light rounded border">
                                <p class="mb-1"><strong>推薦老師：</strong> <?php echo htmlspecialchars($row['teacher_name'] ?? '尚未指定或無'); ?></p>
                                <p class="mb-1"><strong>推薦信內容：</strong><br> 
                                    <span class="text-secondary"><?php echo nl2br(htmlspecialchars($row['recommendation_content'] ?? '尚無推薦信內容')); ?></span>
                                </p>
                            </div>

                            <div class="mt-3 border-top pt-3">
                                <h6 class="fw-bold text-primary mb-3">📋 自訂審查項目</h6>
                                <?php
                                // 撈取該學生的自訂回覆資料 (從 application_custom_answers 取 answer_value)
                                $sql_ans = "SELECT field_id, answer_value FROM application_custom_answers WHERE application_id = ?";
                                $stmt_ans = $pdo->prepare($sql_ans);
                                $stmt_ans->execute([$row['app_id']]);
                                $answers = [];
                                while ($ans = $stmt_ans->fetch(PDO::FETCH_ASSOC)) {
                                    $answers[$ans['field_id']] = $ans['answer_value'];
                                }
                                
                                if (empty($custom_fields)) {
                                    echo "<p class='text-muted small mb-0'>本獎學金無自訂審查項目</p>";
                                } else {
                                    echo "<ul class='list-unstyled mb-0 row g-3'>";
                                    foreach ($custom_fields as $field) {
                                        $f_id = $field['id'];
                                        $label = htmlspecialchars($field['field_label']);
                                        $type = $field['field_type'];
                                        
                                        echo "<li class='col-md-6'><strong class='text-secondary'>{$label}：</strong><br>";
                                        
                                        if (isset($answers[$f_id]) && $answers[$f_id] !== '') {
                                            if ($type === 'file') {
                                                $file_url = htmlspecialchars($answers[$f_id]);
                                                echo "<a href='{$file_url}' target='_blank' class='btn btn-sm btn-outline-info mt-1'>📁 查看附件</a>";
                                            } else {
                                                echo nl2br(htmlspecialchars($answers[$f_id]));
                                            }
                                        } else {
                                            echo "<span class='text-muted small'>未填寫</span>";
                                        }
                                        echo "</li>";
                                    }
                                    echo "</ul>";
                                }
                                ?>
                            </div>

                            <div class="mt-4 p-3 bg-light rounded">
                                <form action="review_application.php" method="post" class="d-flex flex-column gap-2 mb-0">
                                    <div class="d-flex flex-column flex-sm-row gap-2 align-items-sm-center">
                                        <input type="hidden" name="application_id" value="<?php echo $row['app_id']; ?>">
                                        <input type="hidden" name="scholarship_id" value="<?php echo $scholarship_id; ?>">
                                        <input type="hidden" name="provider_id" value="<?php echo htmlspecialchars($provider_id ?? ''); ?>">
                                        
                                        <label class="mb-0 fw-semibold text-nowrap">更新狀態：</label>
                                        <select name="status" class="form-select w-auto" onchange="this.parentElement.nextElementSibling.style.display = (this.value === '需補件') ? 'block' : 'none';">
                                            <option value="審查中" <?= $row['RESULT'] === '審查中' ? 'selected' : '' ?>>維持審查中</option>
                                            <option value="通過" <?= $row['RESULT'] === '通過' ? 'selected' : '' ?>>通過</option>
                                            <option value="不通過" <?= $row['RESULT'] === '不通過' ? 'selected' : '' ?>>不通過</option>
                                            <option value="需補件" <?= $row['RESULT'] === '需補件' ? 'selected' : '' ?>>需補件</option>
                                            <option value="已獲獎" <?= $row['RESULT'] === '已獲獎' ? 'selected' : '' ?>>已獲獎</option>
                                            <option value="未獲獎" <?= $row['RESULT'] === '未獲獎' ? 'selected' : '' ?>>未獲獎</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary text-nowrap">💾 儲存並發送通知</button>
                                    </div>
                                    
                                    <div style="display:none;" class="mt-2">
                                        <input type="text" name="reject_reason" class="form-control border-danger" placeholder="請輸入需補件的具體原因 (系統將自動透過 Email 寄給學生)">
                                    </div>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="batchUpdateModal" tabindex="-1" aria-labelledby="batchUpdateModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="batchUpdateModalLabel">批次處理申請狀態</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="batch_update_status.php" method="post">
          <div class="modal-body">
              <input type="hidden" name="scholarship_id" value="<?php echo htmlspecialchars($scholarship_id); ?>">
              
              <div class="mb-3">
                  <label class="form-label fw-semibold">目標狀態</label>
                  <select name="batch_status" class="form-select" required>
                      <option value="" disabled selected>請選擇要變更為哪種狀態...</option>
                      <option value="通過">通過</option>
                      <option value="不通過">不通過</option>
                      <option value="需補件">需補件</option>

                  </select>
              </div>
              
              <div class="mb-3">
                  <label class="form-label fw-semibold">目標學生學號 (以半形逗號分隔)</label>
                  <textarea name="student_ids" class="form-control" rows="5" placeholder="例如：A1125501,A1125502,A1125503" required></textarea>
                  <div class="form-text text-muted">
                      系統會自動過濾空格。執行完成後會自動寄發 Email 通知這些學生。
                  </div>
              </div>
          </div>
          <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
              <button type="submit" class="btn btn-primary">確認執行</button>
          </div>
      </form>
    </div>
  </div>
</div>

</main>
</body>
</html>
