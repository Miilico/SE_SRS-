<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "db.php";
require_once __DIR__ . "/../file_helpers.php";
ensure_application_files_table($pdo);

// 取得 provider_id
if (isset($_SESSION['user']['id'])) {
    $provider_id = $_SESSION['user']['id'];
} else {
    $provider_id = isset($_GET['provider_id']) ? $_GET['provider_id'] : null;
}

if (!$provider_id) {
    die("請先登入或在網址加上 provider_id 參數，例如 ?provider_id=S0000001");
}

// 取得獎學金清單
$sql = "SELECT id, NAME FROM scholarship WHERE provider_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute(array($provider_id));
$scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 使用者選擇的獎學金
$scholarship_id = isset($_GET['scholarship_id']) ? $_GET['scholarship_id'] : 0;
if ($scholarship_id == 0 && !empty($scholarships)) {
    $scholarship_id = $scholarships[0]['id'];
}
$rows = array();

if ($scholarship_id) {
    $sql = "SELECT a.APNO AS app_id, a.AUTOBI, a.RANK, a.GRADE, a.RESULT,
                   u.name AS student_name, s.NAME AS scholarship_name,
                   r.content AS recommendation,
                   tu.name AS teacher_name, t.DNAME AS department_name
            FROM application a
            JOIN scholarship s ON a.SCID = s.id
            JOIN users u ON a.STID = u.ID
            LEFT JOIN recommendations r ON a.APNO = r.application_id
            LEFT JOIN users tu ON r.teacher_id = tu.ID
            LEFT JOIN teachers t ON r.teacher_id = t.ID
            WHERE s.id = ? AND s.provider_id = ?";
    $stmt = $pdo->prepare($sql);
    if (!$stmt->execute(array($scholarship_id, $provider_id))) {
        print_r($stmt->errorInfo());
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $custom_fields = [];
    $sql_fields = "SELECT id, field_label, field_type FROM scholarship_fields WHERE scholarship_id = ? ORDER BY id";
    $stmt_fields = $pdo->prepare($sql_fields);
    $stmt_fields->execute([$scholarship_id]);
    $custom_fields = $stmt_fields->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = "瀏覽申請資料";
$activeNav = "view_applicants.php";
require __DIR__ . "/../header.php";
?>
    <h1 class="h3 fw-bold mb-4">瀏覽申請資料</h1>
    <h1 class="h3 fw-bold mb-4">申請資料</h1>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <form method="get" action="view_applicants.php" class="d-flex gap-2 align-items-center">
        <?php if (isset($_GET['provider_id'])): ?>
            <input type="hidden" name="provider_id" value="<?php echo htmlspecialchars($_GET['provider_id'] ?? ''); ?>">
        <?php endif; ?>
        <label for="scholarship_id" class="form-label mb-0 fw-semibold text-nowrap">選擇獎助學金：</label>
        <select name="scholarship_id" id="scholarship_id" class="form-select w-auto" onchange="this.form.submit()">
            <?php foreach ($scholarships as $s): ?>
                <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $scholarship_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($s['NAME'] ?? ''); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($scholarship_id): ?>
        <button type="button" class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#batchUpdateModal">
                ✏️ 批次處理狀態
        </button>
        <a href="export_applicants.php?scholarship_id=<?php echo urlencode($scholarship_id); ?>" 
           class="btn btn-success d-flex align-items-center gap-2">
           📊 匯出為 CSV 試算表
        </a>
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

    <!-- 顯示申請資料 -->
    <?php if ($scholarship_id && count($rows) === 0): ?>
        <div class="alert alert-warning">目前沒有申請資料。</div>
    <?php elseif ($scholarship_id): ?>
        <div class="row row-cols-1 row-cols-md-2 g-4">
            <?php foreach ($rows as $row): ?>
                <?php 
                // 查詢該申請的自傳
                $autobiStmt = $pdo->prepare("
                    SELECT original_name, path
                    FROM application_files
                    WHERE apno = ? AND file_type = 'autobi'
                ");
                $autobiStmt->execute([$row['app_id']]);
                $autobiFiles = $autobiStmt->fetchAll(PDO::FETCH_ASSOC);

                // 查詢該申請的其他檔案 
                $fileStmt = $pdo->prepare(" 
                    SELECT original_name, file_type, path
                    FROM application_files
                    WHERE apno = ? AND file_type = 'support'
                "); 
                $fileStmt->execute([$row['app_id']]); 
                $files = $fileStmt->fetchAll(PDO::FETCH_ASSOC); 

                $recFileStmt = $pdo->prepare("
                    SELECT id, original_name
                    FROM application_files
                    WHERE application_id = ? AND file_category = 4
                    ORDER BY created_at ASC, id ASC
                ");
                $recFileStmt->execute([$row['app_id']]);
                $recommendationFiles = $recFileStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <div class="col">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title">申請學生：<?php echo htmlspecialchars($row['student_name'] ?? ''); ?></h5>
                            <!--<p><strong>自傳：</strong><br>?php echo nl2br(htmlspecialchars($row['AUTOBI'])); ?></p>-->
                            <p><strong>排名：</strong> <?php echo htmlspecialchars($row['RANK'] ?? ''); ?></p>
                            <p><strong>成績：</strong> <?php echo htmlspecialchars($row['GRADE'] ?? ''); ?></p>
                            <p class="mb-1"><strong>成績：</strong> <?php echo htmlspecialchars($row['GRADE'] ?? ''); ?></p>
                            <p class="mb-1"><strong>名次：</strong> <?php echo htmlspecialchars($row['RANK'] ?? ''); ?></p>
                            <p class="mb-0"><strong>自傳/說明：</strong></p>
                            <p class="mb-0 text-muted small mt-1"><?php echo nl2br(htmlspecialchars($row['AUTOBI'] ?? '')); ?></p>

                            <?php
                            // 去資料庫撈這個學生的所有客製化答案
                            $ans_sql = "SELECT f.field_label, f.field_type, ans.answer_value 
                                        FROM application_custom_answers ans
                                        JOIN scholarship_fields f ON ans.field_id = f.id
                                        WHERE ans.application_id = ?";
                            $ans_stmt = $pdo->prepare($ans_sql);
                            $ans_stmt->execute([$row['app_id']]);
                            $custom_answers = $ans_stmt->fetchAll(PDO::FETCH_ASSOC);

                            if (!empty($custom_answers)): 
                            ?>
                                <hr class="my-3 border-secondary opacity-25">
                                <h6 class="fw-bold text-primary mb-2">📌 單位自訂要求項目：</h6>
                                <ul class="list-unstyled mb-0">
                                    <?php foreach ($custom_answers as $ans): ?>
                                        <li class="mb-3">
                                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($ans['field_label'] ?? ''); ?>：</span><br>
                                            
                                            <?php if ($ans['field_type'] === 'file'): ?>
                                                <?php if (!empty($ans['answer_value'])): ?>
                                                    <a href="/scholarship/<?php echo htmlspecialchars($ans['answer_value'] ?? ''); ?>" target="_blank" class="btn btn-sm btn-outline-secondary mt-1">
                                                        📄 點此查看/下載檔案
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">學生未上傳檔案</span>
                                                <?php endif; ?>
                                                
                                            <?php else: ?>
                                                <span class="text-muted small">
                                                    <?php echo nl2br(htmlspecialchars($ans['answer_value'] ?? '')); ?>
                                                </span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <p><strong>推薦老師：</strong> <?php echo htmlspecialchars($row['teacher_name'] ?? ''); ?> <?php echo htmlspecialchars($row['department_name'] ?? ''); ?></p>
                            <p><strong>推薦信內容：</strong><br><?php echo nl2br(htmlspecialchars($row['recommendation'] ?? '')); ?></p>
                            <?php if (!empty($recommendationFiles)): ?>
                            <p><strong>推薦信附件：</strong></p>
                            <ul class="list-group mb-3">
                                <?php foreach ($recommendationFiles as $f): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?= htmlspecialchars($f['original_name'] ?? '') ?>
                                        <a href="/scholarship/file_view.php?id=<?= urlencode($f['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">下載</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                            <?php if (!empty($autobiFiles)): ?>
                                    
                            <p><strong>自傳檔案：</strong></p>
                                <ul class="list-group mb-3">
                                <?php foreach ($autobiFiles as $f): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($f['original_name'] ?? '') ?>
                                <?php if (strpos($f['path'], '/scholarship/file_view.php?id=') === 0): ?>
                                <a href="<?= htmlspecialchars($f['path'] ?? '') ?>" target="_blank" class="btn btn-sm btn-outline-secondary">下載</a>
                                <?php else: ?>
                                <span class="text-muted small">舊附件需重新上傳</span>
                                <?php endif; ?>
                                </li>
                                <?php endforeach; ?>
                                </ul>
                                <?php else: ?>
                            <p class="text-muted">尚未上傳自傳</p>
                            <?php endif; ?>

                            <!-- 顯示其他檔案 -->
                          <?php if (!empty($files)): ?>
                            <p><strong>其他上傳資料：</strong></p>
                            <ul class="list-group mb-3">
                                <?php foreach ($files as $f): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <!--?= htmlspecialchars($f['file_type']) ?>-->
                                        <?= htmlspecialchars($f['original_name'] ?? '') ?>
                                        <?php if (strpos($f['path'], '/scholarship/file_view.php?id=') === 0): ?>
                                        <a href="<?= htmlspecialchars($f['path'] ?? '') ?>" target="_blank" class="btn btn-sm btn-outline-secondary">下載</a>
                                        <?php else: ?>
                                        <span class="text-muted small">舊附件需重新上傳</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                                <?php else: ?>
                            <p class="text-muted">尚未上傳其他資料</p>
                          <?php endif; ?>
                            <div class="mt-3 border-top pt-3 bg-light p-3 rounded">
    <h6 class="fw-bold text-primary mb-3">📋 自訂審查項目</h6>
    <?php
    // 撈取該學生的回覆資料 (更新為正確的資料表與欄位名稱)
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
        echo "<ul class='list-unstyled mb-0 vstack gap-2'>";
        foreach ($custom_fields as $field) {
            $f_id = $field['id'];
            $label = htmlspecialchars($field['field_label']);
            $type = $field['field_type'];
            
            echo "<li><strong class='text-secondary'>{$label}：</strong><br>";
            
            if (isset($answers[$f_id]) && $answers[$f_id] !== '') {
                if ($type === 'file') {
                    // 如果是檔案類型，將 answer_value 當作檔案路徑顯示
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
                            <p><strong>目前狀態：</strong>
                                <?php if ($row['RESULT'] === '通過'): ?>
                                    <span class="badge bg-success">通過</span>
                                <?php elseif ($row['RESULT'] === '不通過'): ?>
                                    <span class="badge bg-danger">不通過</span>
                                <?php elseif ($row['RESULT'] === '需補件'): ?>
                                    <span class="badge bg-info text-dark">需補件</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">審查中</span>
                                <?php endif; ?>
                            </p>

                            <!-- 審查表單 -->
                            <form action="review_application.php" method="post" class="d-flex flex-column gap-2">
                                <div class="d-flex gap-2">
                                    <input type="hidden" name="application_id" value="<?php echo $row['app_id']; ?>">
                                    <input type="hidden" name="scholarship_id" value="<?php echo $scholarship_id; ?>">
                                    <input type="hidden" name="provider_id" value="<?php echo htmlspecialchars($provider_id ?? ''); ?>">
                                    
                                    <!-- 當選擇需補件時，顯示下方的輸入框 -->
                                    <select name="status" class="form-select w-auto" onchange="this.parentElement.nextElementSibling.style.display = (this.value === '需補件') ? 'block' : 'none';">
                                        <option value="審查中">維持審查中</option>
                                        <option value="通過">通過</option>
                                        <option value="不通過">不通過</option>
                                        <option value="需補件">需補件</option>
                                    </select>
                                    <button type="submit" class="btn btn-outline-primary text-nowrap">更新結果</button>
                                </div>
                                
                                <!-- 補件理由輸入框 (預設隱藏) -->
                                <input type="text" name="reject_reason" class="form-control" style="display:none;" placeholder="請輸入需補件的具體原因 (將透過 Email 寄給學生)">
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="modal fade" id="batchUpdateModal" tabindex="-1" aria-labelledby="batchUpdateModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="batchUpdateModalLabel">批次處理申請狀態</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="batch_update_status.php" method="post">
          <div class="modal-body">
              <input type="hidden" name="scholarship_id" value="<?php echo htmlspecialchars($scholarship_id ?? ''); ?>">
              
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


