<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../recommendation_helpers.php";

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$token = isset($_GET["token"]) ? trim($_GET["token"]) : "";
if ($token === "") {
    http_response_code(400);
    exit("缺少推薦信 token。");
}

ensure_application_files_table($pdo);
tar_auto_reject_overdue_recommendations($pdo);

$stmt = $pdo->prepare("
    SELECT
        r.id AS recommendation_id,
        r.content,
        r.draft_content,
        r.status,
        r.teacher_id,
        r.teacher_name,
        r.teacher_email,
        r.rec_rel,
        r.created_at,
        r.expires_at,
        r.submitted_at,
        r.rejected_reason,
        r.rejected_source,
        r.rejected_at,
        r.application_id,
        a.APNO,
        a.GRADE,
        a.RANK,
        a.APDATE,
        a.AUTOBI,
        a.STID,
        a.SCID,
        u.NAME AS student_name,
        u.EMAIL AS student_email,
        st.SID,
        st.DNAME,
        sc.NAME AS scholarship_name,
        sc.AMOUNT AS scholarship_amount,
        sc.CONDI AS scholarship_condition,
        sc.DEADLINE AS scholarship_deadline
    FROM recommendations r
    JOIN application a ON r.application_id = a.APNO
    JOIN users u ON a.STID = u.ID
    LEFT JOIN students st ON u.ID = st.ID
    JOIN scholarship sc ON a.SCID = sc.id
    WHERE r.token = :token
    LIMIT 1
");
$stmt->execute(array(":token" => $token));
$recommendation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recommendation) {
    http_response_code(404);
    exit("推薦信連結不存在或已失效。");
}

$fileStmt = $pdo->prepare("
    SELECT id, file_type, original_name, path
    FROM application_files
    WHERE apno = :apno
    ORDER BY id ASC
");
$fileStmt->execute(array(":apno" => $recommendation["application_id"]));
$files = $fileStmt->fetchAll(PDO::FETCH_ASSOC);

$autobiFiles = array();
$supportFiles = array();
foreach ($files as $file) {
    if ($file["file_type"] === "autobi") {
        $autobiFiles[] = $file;
    } else {
        $supportFiles[] = $file;
    }
}

$statusLabel = tar_recommendation_status_label($recommendation);
$canWrite = in_array($statusLabel, array("待填寫", "草稿"), true);
$draftText = $recommendation["draft_content"] !== null && $recommendation["draft_content"] !== ""
    ? $recommendation["draft_content"]
    : "";

$pageTitle = "推薦信";
$siteHeaderMaxWidth = "860px";
require __DIR__ . "/../header.php";
?>

<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
  <div>
    <p class="text-secondary mb-1">TAR 推薦信</p>
    <h1 class="h3 fw-bold mb-0">推薦信處理</h1>
  </div>
  <?php if (!empty($_SESSION["user"])): ?>
    <a class="btn btn-outline-secondary" href="tea_dashboard.php">返回導師總覽</a>
  <?php endif; ?>
</div>

<?php if (!empty($_GET["err"])): ?>
  <div class="alert alert-danger"><?= h($_GET["err"]) ?></div>
<?php endif; ?>
<?php if (!empty($_GET["saved"])): ?>
  <div class="alert alert-success">草稿已暫存。</div>
<?php endif; ?>
<?php if (!empty($_GET["submitted"])): ?>
  <div class="alert alert-success">推薦信已提交。提交後不可再次編輯。</div>
<?php endif; ?>
<?php if (!empty($_GET["rejected"])): ?>
  <div class="alert alert-success">推薦信撰寫請求已駁回。</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
      <h2 class="h5 fw-bold mb-0">學生與申請資料</h2>
      <span class="badge rounded-pill <?= $statusLabel === "已提交" ? "text-bg-success" : ($statusLabel === "已駁回" ? "text-bg-danger" : ($statusLabel === "草稿" ? "text-bg-info" : "text-bg-warning")) ?>">
        <?= h($statusLabel) ?>
      </span>
    </div>
    <div class="table-responsive">
      <table class="table table-bordered align-middle mb-0">
        <tbody>
          <tr><th class="table-light" style="width: 180px;">學生姓名</th><td><?= h($recommendation["student_name"]) ?></td></tr>
          <tr><th class="table-light">學生帳號</th><td><?= h($recommendation["STID"]) ?></td></tr>
          <tr><th class="table-light">學號</th><td><?= h($recommendation["SID"] ?? "") ?></td></tr>
          <tr><th class="table-light">系所</th><td><?= h($recommendation["DNAME"] ?? "") ?></td></tr>
          <tr><th class="table-light">申請獎助學金</th><td><?= h($recommendation["scholarship_name"]) ?></td></tr>
          <tr><th class="table-light">申請日期</th><td><?= h($recommendation["APDATE"]) ?></td></tr>
          <tr><th class="table-light">成績</th><td><?= h($recommendation["GRADE"] ?? "") ?></td></tr>
          <tr><th class="table-light">名次</th><td><?= h($recommendation["RANK"] ?? "") ?></td></tr>
          <tr><th class="table-light">推薦關係</th><td><?= h($recommendation["rec_rel"] ?? "") ?></td></tr>
          <tr><th class="table-light">請求期限</th><td><?= h($recommendation["expires_at"] ?? "") ?></td></tr>
          <?php if ($statusLabel === "已駁回"): ?>
            <tr><th class="table-light">駁回來源</th><td><?= h($recommendation["rejected_source"] === "system" ? "系統自動" : "導師手動") ?></td></tr>
            <tr><th class="table-light">駁回原因</th><td><?= nl2br(h($recommendation["rejected_reason"])) ?></td></tr>
            <tr><th class="table-light">駁回時間</th><td><?= h($recommendation["rejected_at"]) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-4">
    <h2 class="h5 fw-bold mb-3">申請附件</h2>
    <?php if (empty($files)): ?>
      <p class="text-secondary mb-0">此申請尚未提供附件。</p>
    <?php else: ?>
      <?php $groups = array("自傳" => $autobiFiles, "其他佐證資料" => $supportFiles); ?>
      <?php foreach ($groups as $label => $groupFiles): ?>
        <?php if (!empty($groupFiles)): ?>
          <h3 class="h6 fw-bold mt-3"><?= h($label) ?></h3>
          <ul class="mb-0">
            <?php foreach ($groupFiles as $file): ?>
              <li>
                <a href="/scholarship/file_view.php?id=<?= urlencode($file["id"]) ?>&rec_token=<?= urlencode($token) ?>" target="_blank">
                  <?= h($file["original_name"]) ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-4">
    <?php if ($statusLabel === "已提交"): ?>
      <h2 class="h5 fw-bold mb-3">已提交推薦信</h2>
      <p class="text-secondary">提交時間：<?= h($recommendation["submitted_at"]) ?></p>
      <div class="border rounded p-3 bg-light"><?= nl2br(h($recommendation["content"])) ?></div>
    <?php elseif ($statusLabel === "已駁回"): ?>
      <h2 class="h5 fw-bold mb-3">推薦信已駁回</h2>
      <p class="text-secondary mb-0">此請求已被駁回，不可再填寫推薦信。</p>
    <?php else: ?>
      <h2 class="h5 fw-bold mb-3">推薦內容</h2>
      <form method="post" action="submit_recommendation.php" enctype="multipart/form-data">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <div class="mb-3">
          <label for="content" class="form-label">推薦信內容</label>
          <textarea name="content" id="content" class="form-control" rows="8" required><?= h($draftText) ?></textarea>
          <div class="form-text">可先暫存草稿；正式提交後不可再次編輯。</div>
        </div>
        <div class="mb-3">
          <label for="recommendation_file" class="form-label">推薦信附件（選填，提交時上傳）</label>
          <input type="file" name="RECOMMENDATION_FILE" id="recommendation_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button type="submit" name="action" value="save_draft" class="btn btn-outline-primary">暫存草稿</button>
          <button type="submit" name="action" value="submit" class="btn btn-primary">提交推薦信</button>
        </div>
      </form>

      <hr class="my-4">

      <h2 class="h5 fw-bold mb-3">駁回撰寫請求</h2>
      <form method="post" action="reject_recommendation.php" class="vstack gap-3">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <div>
          <label for="reason" class="form-label">駁回原因</label>
          <textarea name="reason" id="reason" class="form-control" rows="3" required></textarea>
        </div>
        <button type="submit" class="btn btn-outline-danger align-self-start">駁回請求</button>
      </form>
    <?php endif; ?>
  </div>
</div>

</main>
</body>
</html>
