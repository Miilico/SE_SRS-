<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../recommendation_helpers.php";

if (empty($_SESSION["user"]) || !in_array((int)$_SESSION["user"]["role"], array(2, 3), true)) {
  http_response_code(403);
  exit("Forbidden: role required");
}

ensure_application_files_table($pdo);
tar_auto_reject_overdue_recommendations($pdo);

function h($value)
{
  return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function mask_private_id($value)
{
  $value = trim((string)$value);
  $len = strlen($value);
  if ($len <= 4) {
    return str_repeat("*", $len);
  }

  return substr($value, 0, 2) . str_repeat("*", max(3, $len - 4)) . substr($value, -2);
}

function mask_phone($value)
{
  $digits = preg_replace("/\D+/", "", (string)$value);
  $len = strlen($digits);
  if ($len === 0) {
    return "";
  }
  if ($len <= 6) {
    return substr($digits, 0, 1) . str_repeat("*", max(0, $len - 2)) . substr($digits, -1);
  }

  return substr($digits, 0, 3) . str_repeat("*", max(3, $len - 6)) . substr($digits, -3);
}

$sid = isset($_GET["sid"]) ? trim($_GET["sid"]) : "";
$student = null;
$applications = array();
$accessDenied = false;
$currentRole = isset($_SESSION["user"]["role"]) ? (int)$_SESSION["user"]["role"] : 0;
$currentUserId = isset($_SESSION["user"]["id"]) ? $_SESSION["user"]["id"] : "";

if ($sid !== "") {
  $studentStmt = $pdo->prepare("
        SELECT u.ID, u.NAME, u.EMAIL, u.TEL, s.SID, s.DNAME
        FROM users u
        JOIN students s ON u.ID = s.ID
        WHERE u.ROLE = 1 AND (u.ID = :sid OR s.SID = :sid)
        LIMIT 1
    ");
  $studentStmt->execute(array(":sid" => $sid));
  $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

  if ($student && $currentRole !== 3) {
    $accessStmt = $pdo->prepare("
            SELECT 1
            FROM recommendations r
            JOIN application a ON r.application_id = a.APNO
            WHERE a.STID = :student_id AND r.teacher_id = :teacher_id
            LIMIT 1
        ");
    $accessStmt->execute(array(
      ":student_id" => $student["ID"],
      ":teacher_id" => $currentUserId,
    ));
    $accessDenied = !$accessStmt->fetchColumn();
  }

  if ($student && !$accessDenied) {
    if ($currentRole === 3) {
      $sql = "
                SELECT
                    a.APNO,
                    a.APDATE,
                    a.GRADE,
                    a.RANK,
                    a.AMOUNT,
                    a.RESULT,
                    a.SCNAME,
                    sc.NAME AS scholarship_name,
                    o.ONAME AS organization_name,
                    r.id AS recommendation_id,
                    r.content,
                    r.draft_content,
                    r.status,
                    r.rejected_reason,
                    r.rejected_source,
                    r.submitted_at
                FROM application a
                LEFT JOIN scholarship sc ON a.SCID = sc.id
                LEFT JOIN organization o ON a.OID = o.ID
                LEFT JOIN recommendations r ON r.application_id = a.APNO
                WHERE a.STID = :student_id
                ORDER BY a.APDATE DESC, a.APNO DESC
            ";
      $appStmt = $pdo->prepare($sql);
      $appStmt->execute(array(":student_id" => $student["ID"]));
    } else {
      $sql = "
                SELECT
                    a.APNO,
                    a.APDATE,
                    a.GRADE,
                    a.RANK,
                    a.AMOUNT,
                    a.RESULT,
                    a.SCNAME,
                    sc.NAME AS scholarship_name,
                    o.ONAME AS organization_name,
                    r.id AS recommendation_id,
                    r.content,
                    r.draft_content,
                    r.status,
                    r.rejected_reason,
                    r.rejected_source,
                    r.submitted_at
                FROM application a
                LEFT JOIN scholarship sc ON a.SCID = sc.id
                LEFT JOIN organization o ON a.OID = o.ID
                JOIN recommendations r ON r.application_id = a.APNO
                    AND r.teacher_id = :teacher_id
                WHERE a.STID = :student_id
                ORDER BY a.APDATE DESC, a.APNO DESC
            ";
      $appStmt = $pdo->prepare($sql);
      $appStmt->execute(array(
        ":teacher_id" => $currentUserId,
        ":student_id" => $student["ID"],
      ));
    }

    $applications = $appStmt->fetchAll(PDO::FETCH_ASSOC);
  }
}

$pageTitle = "學生資料檢視";
$activeNav = "tea_dashboard.php";
$siteHeaderRequiredRole = array(2, 3);
$siteHeaderMaxWidth = "980px";
require __DIR__ . "/../header.php";
?>

<div class="d-flex justify-content-between align-items-center gap-3 mb-4">
  <div>
    <p class="text-secondary mb-1">導師子系統</p>
    <h1 class="h3 fw-bold mb-0">學生資料檢視</h1>
  </div>
  <a class="btn btn-outline-secondary" href="tea_dashboard.php">返回導師首頁</a>
</div>

<?php if ($sid === ""): ?>
  <div class="alert alert-info">請從導師首頁選擇一位學生。</div>
<?php elseif (!$student): ?>
  <div class="alert alert-warning">找不到學生資料：<?= h($sid) ?></div>
<?php elseif ($accessDenied): ?>
  <div class="alert alert-warning">您只能檢視自己被邀請撰寫推薦信的學生資料。</div>
<?php else: ?>
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
      <h2 class="h5 fw-bold mb-3">基本資料</h2>
      <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
          <tbody>
            <tr>
              <th class="table-light" style="width: 180px;">姓名</th>
              <td><?= h($student["NAME"]) ?></td>
            </tr>
            <tr>
              <th class="table-light">系統帳號</th>
              <td><?= h(mask_private_id($student["ID"])) ?></td>
            </tr>
            <tr>
              <th class="table-light">學號</th>
              <td><?= h($student["SID"]) ?></td>
            </tr>
            <tr>
              <th class="table-light">科系</th>
              <td><?= h($student["DNAME"]) ?></td>
            </tr>
            <tr>
              <th class="table-light">Email</th>
              <td><?= h($student["EMAIL"]) ?></td>
            </tr>
            <tr>
              <th class="table-light">電話</th>
              <td><?= h(mask_phone($student["TEL"])) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <h2 class="h5 fw-bold mb-3">申請書內容</h2>
      <?php if (empty($applications)): ?>
        <p class="text-secondary mb-0">目前沒有可檢視的申請資料。</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>申請日期</th>
                <th>獎助學金</th>
                <th>提供單位</th>
                <th>成績</th>
                <th>排名</th>
                <th>申請金額</th>
                <th>審核狀態</th>
                <th>推薦信狀態</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($applications as $app): ?>
                <tr>
                  <td><?= h($app["APDATE"]) ?></td>
                  <td><?= h($app["scholarship_name"] ?: $app["SCNAME"]) ?></td>
                  <td><?= h($app["organization_name"] ?? "") ?></td>
                  <td><?= h($app["GRADE"] ?? "") ?></td>
                  <td><?= h($app["RANK"] ?? "") ?></td>
                  <td>NT$ <?= number_format((int)$app["AMOUNT"]) ?></td>
                  <td><?= site_status_badge($app["RESULT"]) ?></td>
                  <td>
                    <?= site_status_badge(tar_recommendation_status_label($app), "recommendation") ?>
                    <?php if (($app["status"] ?? "") === "rejected" && !empty($app["rejected_reason"])): ?>
                      <div class="small text-secondary mt-1">
                        <?= h($app["rejected_source"] === "system" ? "系統自動" : "導師手動") ?>：<?= h($app["rejected_reason"]) ?>
                      </div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

</main>
</body>

</html>