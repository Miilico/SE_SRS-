<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../file_helpers.php";
require_once __DIR__ . "/../supplement_note_helpers.php";
require_role(1);

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$user = isset($_SESSION["user"]) ? $_SESSION["user"] : array();
$studentId = !empty($user["stid"]) ? $user["stid"] : (!empty($user["id"]) ? $user["id"] : "");
$studentIds = array_values(array_unique(array_filter(array(
    !empty($user["stid"]) ? (string)$user["stid"] : "",
    !empty($user["id"]) ? (string)$user["id"] : "",
))));
$apno = isset($_GET["apno"]) ? (int)$_GET["apno"] : 0;

if ($studentId === "" || $apno <= 0) {
    header("Location: /scholarship/student/my_applications.php");
    exit;
}

$supplementNoteSelect = table_has_column($pdo, "application", "SUPPLEMENT_NOTE")
    ? ", a.SUPPLEMENT_NOTE"
    : ", NULL AS SUPPLEMENT_NOTE";
$studentPlaceholders = implode(",", array_fill(0, count($studentIds), "?"));
$stmt = $pdo->prepare("
    SELECT a.APNO, a.RESULT, a.SCNAME" . $supplementNoteSelect . ", s.NAME AS scholarship_name
    FROM application a
    LEFT JOIN scholarship s ON a.SCID = s.id
    WHERE a.APNO = ? AND a.STID IN (" . $studentPlaceholders . ")
    LIMIT 1
");
$stmt->execute(array_merge(array($apno), $studentIds));
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$application) {
    http_response_code(404);
    exit("找不到申請資料，或你沒有補件權限。");
}
$supplementNote = supplement_note_get($pdo, $apno, isset($application["SUPPLEMENT_NOTE"]) ? $application["SUPPLEMENT_NOTE"] : null);

if ($application["RESULT"] !== "需補件") {
    header("Location: /scholarship/student/application_detail.php?apno=" . urlencode($apno) . "&err=" . urlencode("此申請目前不在可補件狀態。"));
    exit;
}

$fileStmt = $pdo->prepare("
    SELECT id, original_name, path, file_size, created_at
    FROM application_files
    WHERE COALESCE(application_id, apno) = ?
      AND file_type = 'supplement'
    ORDER BY created_at DESC, id DESC
");
$fileStmt->execute(array($apno));
$supplementFiles = $fileStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($_SESSION["supplement_csrf_token"])) {
    $_SESSION["supplement_csrf_token"] = bin2hex(random_bytes(32));
}

$pageTitle = "上傳補件";
$activeNav = "my_applications.php";
$siteHeaderRequiredRole = 1;
require __DIR__ . "/../header.php";
?>

<h1 class="h3 fw-bold mb-4">上傳補件</h1>

<?php if (!empty($_GET["err"])): ?>
  <div class="alert alert-danger"><?= h($_GET["err"]) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="card-body p-4">
    <dl class="row mb-4">
      <dt class="col-sm-3">申請編號</dt>
      <dd class="col-sm-9"><?= h($application["APNO"]) ?></dd>
      <dt class="col-sm-3">獎學金</dt>
      <dd class="col-sm-9"><?= h($application["scholarship_name"] ?: $application["SCNAME"]) ?></dd>
      <dt class="col-sm-3">目前狀態</dt>
      <dd class="col-sm-9"><?= site_status_badge($application["RESULT"]) ?></dd>
    </dl>

    <?php if ($supplementNote !== ""): ?>
      <div class="alert alert-warning mb-4">
        <div class="fw-semibold mb-1">補件原因</div>
        <div><?= nl2br(h($supplementNote)) ?></div>
      </div>
    <?php endif; ?>

    <?php if (!empty($supplementFiles)): ?>
      <h2 class="h5 fw-bold">已上傳的補件</h2>
      <?php
      $applicationFiles = $supplementFiles;
      $emptyFilesMessage = "目前沒有補件。";
      $allowFileDeletion = false;
      require __DIR__ . "/partials/application_file_list.php";
      ?>
    <?php endif; ?>

    <form method="post"
          action="/scholarship/student/upload_supplement_submit.php"
          enctype="multipart/form-data"
          class="mt-4">
      <input type="hidden" name="apno" value="<?= h($apno) ?>">
      <input type="hidden" name="csrf_token" value="<?= h($_SESSION["supplement_csrf_token"]) ?>">

      <label class="form-label fw-semibold" for="supplement-files">選擇補件檔案</label>
      <input class="form-control"
             id="supplement-files"
             type="file"
             name="SUPPLEMENT_FILES[]"
             accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
             multiple
             required>
      <div class="form-text">可一次選擇多個檔案；每個檔案上限 10 MB。</div>

      <div class="d-flex gap-2 mt-4">
        <button class="btn btn-warning" type="submit">送出補件</button>
        <a class="btn btn-outline-secondary"
           href="/scholarship/student/application_detail.php?apno=<?= h($apno) ?>">取消</a>
      </div>
    </form>
  </div>
</div>

</main>
</body>
</html>
