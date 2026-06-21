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

$stmt = $pdo->prepare("
    SELECT a.*, s.NAME AS scholarship_name,
           r.teacher_name, r.teacher_email, r.rec_rel
    FROM application a
    JOIN scholarship s ON a.SCID = s.id
    LEFT JOIN recommendations r ON r.application_id = a.APNO
    WHERE a.APNO = :apno AND a.STID = :stid
    LIMIT 1
");
$stmt->execute(array(":apno" => $apno, ":stid" => $stId));
$app = $stmt->fetch();

if (!$app) {
    http_response_code(404);
    exit("找不到申請資料，或你沒有修改權限。");
}

if (application_status_is_final($app["RESULT"])) {
    header("Location: application_detail.php?apno=" . $apno . "&err=" . urlencode("此申請已審核完成，不可修改。"));
    exit;
}

$customFields = custom_form_fields_for_scholarship($pdo, $app["SCID"]);
$customValues = array();
$customFileDetails = array();
if (custom_form_tables_ready($pdo)) {
    $hasCustomFileId = custom_form_column_exists($pdo, "application_custom_answers", "file_id");
    $answerColumns = $hasCustomFileId ? "field_id, answer_value, file_id" : "field_id, answer_value";
    $customAnswerStmt = $pdo->prepare("
        SELECT " . $answerColumns . "
        FROM application_custom_answers
        WHERE application_id = ?
    ");
    $customAnswerStmt->execute(array($apno));
    foreach ($customAnswerStmt->fetchAll(PDO::FETCH_ASSOC) as $answer) {
        $fieldId = (int)$answer["field_id"];
        $customValues[$fieldId] = (string)$answer["answer_value"];
        $fileId = !empty($answer["file_id"])
            ? (int)$answer["file_id"]
            : custom_form_file_id_from_answer($answer["answer_value"]);
        if ($fileId > 0) {
            $fileStmt = $pdo->prepare("
                SELECT id, original_name, path, file_size, created_at
                FROM application_files
                WHERE id = ? AND COALESCE(application_id, apno) = ?
                LIMIT 1
            ");
            $fileStmt->execute(array($fileId, $apno));
            $file = $fileStmt->fetch(PDO::FETCH_ASSOC);
            if ($file) {
                $customFileDetails[$fieldId] = $file;
            }
        }
    }
}

if (empty($_SESSION["edit_application_csrf_token"])) {
    $_SESSION["edit_application_csrf_token"] = bin2hex(random_bytes(32));
}

$pageTitle = "修改申請資料";
$activeNav = "my_applications.php";
$siteHeaderRequiredRole = 1;
require __DIR__ . "/../header.php";
?>

<h1 class="h3 fw-bold mb-4">修改申請資料</h1>

<?php if (!empty($_GET["err"])): ?>
  <div class="alert alert-danger">
    <?= h($_GET["err"]) ?>
  </div>
<?php endif; ?>

<form class="card border-0 shadow-sm"
      method="post"
      action="/scholarship/student/edit_application_update.php"
      enctype="multipart/form-data">
  <div class="card-body p-4">
    <input type="hidden" name="apno" value="<?= h($apno) ?>">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION["edit_application_csrf_token"]) ?>">

    <div class="mb-3">
      <label class="form-label fw-semibold">獎學金</label>
      <input class="form-control" value="<?= h($app["scholarship_name"]) ?>" disabled>
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold">成績</label>
      <input class="form-control" type="number" name="grade"
             min="0" max="100" step="0.01" value="<?= h($app["GRADE"]) ?>">
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold">排名</label>
      <input class="form-control" name="rank" maxlength="11"
             value="<?= h($app["RANK"]) ?>">
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold">推薦教師</label>
      <input class="form-control" value="<?= h($app["teacher_name"]) ?>" disabled>
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold">推薦教師 Email</label>
      <input class="form-control" type="email" name="teacher_email"
             value="<?= h($app["teacher_email"]) ?>">
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold">與推薦教師關係</label>
      <input class="form-control" name="rec_rel" maxlength="100"
             value="<?= h($app["rec_rel"]) ?>">
    </div>

    <?php
      $allowCustomFileDeletion = true;
      require __DIR__ . "/partials/application_custom_fields.php";
    ?>

    <div class="d-flex gap-2">
      <button class="btn btn-primary" type="submit">確認修改</button>
      <a class="btn btn-outline-secondary"
         href="application_detail.php?apno=<?= h($apno) ?>">取消</a>
    </div>
  </div>
</form>

</main>
</body>
</html>
