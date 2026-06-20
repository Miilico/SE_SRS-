<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../custom_form_helpers.php";
require_once __DIR__ . "/../application_helpers.php";
require_role(1);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$user = isset($_SESSION["user"]) ? $_SESSION["user"] : array();
$stId = !empty($user["stid"]) ? $user["stid"] : (!empty($user["id"]) ? $user["id"] : "");
$apno = isset($_GET["apno"]) ? (int)$_GET["apno"] : 0;

if ($stId === "" || $apno <= 0) {
    header("Location: /scholarship/student/my_applications.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT a.*, s.NAME AS scholarship_name, s.CONDI, s.DEADLINE
    FROM application a
    JOIN scholarship s ON a.SCID = s.id
    WHERE a.APNO = :apno AND a.STID = :stid
    LIMIT 1
");
$stmt->execute(array(":apno" => $apno, ":stid" => $stId));
$app = $stmt->fetch();

if (!$app) {
    http_response_code(404);
    exit("找不到申請資料，或你沒有查看權限。");
}

$stmt = $pdo->prepare("
    SELECT id, file_type, original_name, path
    FROM application_files
    WHERE COALESCE(application_id, apno) = :apno
      AND file_type IN ('autobi', 'support', 'supplement')
    ORDER BY id
");
$stmt->execute(array(":apno" => $apno));
$files = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT teacher_name, teacher_email, content, created_at
    FROM recommendations
    WHERE application_id = :apno
    LIMIT 1
");
$stmt->execute(array(":apno" => $apno));
$recommendation = $stmt->fetch();

$customAnswers = array();
if (custom_form_tables_ready($pdo)) {
    $stmt = $pdo->prepare("
        SELECT f.field_label, f.field_type, a.answer_value
        FROM scholarship_fields f
        LEFT JOIN application_custom_answers a
          ON a.field_id = f.id AND a.application_id = :apno
        WHERE f.scholarship_id = :scid
        ORDER BY f.sort_order, f.id
    ");
    $stmt->execute(array(":apno" => $apno, ":scid" => $app["SCID"]));
    $customAnswers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$status = $app["RESULT"];
$canEdit = application_status_can_edit($status);
$canSupplement = ($status === "需補件");

$pageTitle = "申請詳細資料";
$activeNav = "my_applications.php";
$siteHeaderRequiredRole = 1;
require __DIR__ . "/../header.php";
?>

<h1 class="h3 fw-bold mb-4">申請詳細資料</h1>

<?php if (!empty($_GET["msg"])): ?>
  <div class="alert alert-success">
    <?= h($_GET["msg"]) ?>
  </div>
<?php endif; ?>

<?php if (!empty($_GET["err"])): ?>
  <div class="alert alert-danger">
    <?= h($_GET["err"]) ?>
  </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="card-body p-4">
    <table class="table">
      <tr><th>申請編號</th><td><?= h($app["APNO"]) ?></td></tr>
      <tr><th>獎學金</th><td><?= h($app["scholarship_name"]) ?></td></tr>
      <tr><th>申請日期</th><td><?= h($app["APDATE"]) ?></td></tr>
      <tr><th>申請金額</th><td>NT$ <?= number_format((int)$app["AMOUNT"]) ?></td></tr>
      <tr><th>成績</th><td><?= h($app["GRADE"]) ?></td></tr>
      <tr><th>排名</th><td><?= h($app["RANK"]) ?></td></tr>
      <tr><th>審核狀態</th><td><span class="badge bg-secondary"><?= h($status) ?></span></td></tr>
      <tr><th>申請條件</th><td><?= nl2br(h($app["CONDI"])) ?></td></tr>
    </table>

    <?php if (!empty($app["SUPPLEMENT_NOTE"])): ?>
      <div class="alert alert-warning">
        <div class="fw-semibold mb-1">補件要求</div>
        <?= nl2br(h($app["SUPPLEMENT_NOTE"])) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($customAnswers)): ?>
      <h2 class="h5 fw-bold mt-4">獎助單位自訂申請資料</h2>
      <div class="list-group">
        <?php foreach ($customAnswers as $answer): ?>
          <div class="list-group-item">
            <div class="small text-secondary mb-1"><?= h($answer["field_label"]) ?></div>
            <?php if ($answer["field_type"] === "file" && !empty($answer["answer_value"])): ?>
              <a class="btn btn-sm btn-outline-primary" href="<?= h($answer["answer_value"]) ?>" target="_blank" rel="noopener">查看檔案</a>
            <?php else: ?>
              <div><?= $answer["answer_value"] !== null && $answer["answer_value"] !== "" ? nl2br(h($answer["answer_value"])) : "未填寫" ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h2 class="h5 fw-bold mt-4">申請文件</h2>
    <?php if (empty($files)): ?>
      <p class="text-secondary">目前沒有上傳文件。</p>
    <?php else: ?>
      <div class="list-group">
        <?php foreach ($files as $file): ?>
          <a class="list-group-item list-group-item-action"
             href="<?= h($file["path"]) ?>" target="_blank">
            <?= h($file["original_name"]) ?>（<?= h($file["file_type"]) ?>）
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($recommendation): ?>
      <h2 class="h5 fw-bold mt-4">推薦信</h2>
      <p>推薦教師：<?= h($recommendation["teacher_name"]) ?></p>
      <p>Email：<?= h($recommendation["teacher_email"]) ?></p>
      <p><?= $recommendation["content"] !== "" ? nl2br(h($recommendation["content"])) : "教師尚未填寫推薦信。" ?></p>
    <?php endif; ?>

    <div class="d-flex gap-2 mt-4">
      <a class="btn btn-outline-secondary" href="/scholarship/student/my_applications.php">返回我的申請</a>

      <?php if ($canEdit): ?>
        <a class="btn btn-primary" href="/scholarship/student/edit_application.php?apno=<?= h($apno) ?>">修改申請</a>
      <?php endif; ?>

      <?php if ($canSupplement): ?>
        <a class="btn btn-warning" href="/scholarship/student/upload_supplement.php?apno=<?= h($apno) ?>">上傳補件</a>
      <?php endif; ?>
    </div>
  </div>
</div>

</main>
</body>
</html>
