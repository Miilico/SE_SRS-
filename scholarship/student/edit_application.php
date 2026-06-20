<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../custom_form_helpers.php";
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

if (in_array($app["RESULT"], array("通過", "不通過"), true)) {
    header("Location: application_detail.php?apno=" . $apno . "&err=" . urlencode("此申請已審核完成，不可修改。"));
    exit;
}

$customFields = custom_form_fields_for_scholarship($pdo, $app["SCID"]);
$customValues = array();
if (custom_form_tables_ready($pdo)) {
    $customAnswerStmt = $pdo->prepare("
        SELECT field_id, answer_value
        FROM application_custom_answers
        WHERE application_id = ?
    ");
    $customAnswerStmt->execute(array($apno));
    foreach ($customAnswerStmt->fetchAll(PDO::FETCH_ASSOC) as $answer) {
        $customValues[(int)$answer["field_id"]] = (string)$answer["answer_value"];
    }
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
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
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION["csrf_token"]) ?>">

    <div class="mb-3">
      <label class="form-label">獎學金</label>
      <input class="form-control" value="<?= h($app["scholarship_name"]) ?>" disabled>
    </div>

    <div class="mb-3">
      <label class="form-label">成績</label>
      <input class="form-control" type="number" name="grade"
             min="0" max="100" step="0.01" value="<?= h($app["GRADE"]) ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">排名</label>
      <input class="form-control" name="rank" maxlength="11"
             value="<?= h($app["RANK"]) ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">推薦教師</label>
      <input class="form-control" value="<?= h($app["teacher_name"]) ?>" disabled>
    </div>

    <div class="mb-3">
      <label class="form-label">推薦教師 Email</label>
      <input class="form-control" type="email" name="teacher_email"
             value="<?= h($app["teacher_email"]) ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">與推薦教師關係</label>
      <input class="form-control" name="rec_rel" maxlength="100"
             value="<?= h($app["rec_rel"]) ?>">
    </div>

    <?php require __DIR__ . "/partials/application_custom_fields.php"; ?>

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
