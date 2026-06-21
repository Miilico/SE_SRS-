<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../recommendation_helpers.php";
require_once __DIR__ . "/../file_helpers.php";
require_once __DIR__ . "/../supplement_note_helpers.php";

require_role(1);

ensure_application_files_table($pdo);
tar_auto_reject_overdue_recommendations($pdo);

function h($value)
{
  return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function count_application_status($pdo, $studentId, $status)
{
  $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM application
        WHERE STID = :student_id AND RESULT = :status
    ");
  $stmt->execute(array(
    ":student_id" => $studentId,
    ":status" => $status,
  ));

  return (int)$stmt->fetchColumn();
}

$studentId = $_SESSION["user"]["id"];
$userName = $_SESSION["user"]["name"];

$pending = count_application_status($pdo, $studentId, "審查中");
$needFix = count_application_status($pdo, $studentId, "需補件");
$approvedThisYearStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM application
    WHERE STID = :student_id
      AND RESULT IN ('通過', '已獲獎')
      AND YEAR(APDATE) = YEAR(CURDATE())
");
$approvedThisYearStmt->execute(array(":student_id" => $studentId));
$approvedThisYear = (int)$approvedThisYearStmt->fetchColumn();

$paidStmt = $pdo->prepare("
    SELECT IFNULL(SUM(AMOUNT), 0)
    FROM application
    WHERE STID = :student_id AND RESULT IN ('通過', '已獲獎')
");
$paidStmt->execute(array(":student_id" => $studentId));
$paid = (int)$paidStmt->fetchColumn();

$appsStmt = $pdo->prepare("
    SELECT
        a.APNO,
        a.APDATE,
        a.AMOUNT,
        a.RESULT,
        s.NAME AS SCH_NAME,
        r.content,
        r.draft_content,
        r.status,
        r.rejected_reason,
        r.rejected_source,
        r.submitted_at,
        r.rejected_at
    FROM application a
    JOIN scholarship s ON a.SCID = s.id
    LEFT JOIN recommendations r ON r.application_id = a.APNO
    WHERE a.STID = :student_id
    ORDER BY a.APDATE DESC, a.APNO DESC
    LIMIT 10
");
$appsStmt->execute(array(":student_id" => $studentId));
$apps = $appsStmt->fetchAll(PDO::FETCH_ASSOC);

$supplementNoteSelect = table_has_column($pdo, "application", "SUPPLEMENT_NOTE")
    ? ", SUPPLEMENT_NOTE"
    : ", NULL AS SUPPLEMENT_NOTE";
$notiStmt = $pdo->prepare("
    SELECT APNO, APDATE, RESULT" . $supplementNoteSelect . "
    FROM application
    WHERE STID = :student_id
      AND RESULT IN ('需補件', '不通過', '未獲獎')
    ORDER BY APDATE DESC, APNO DESC
    LIMIT 3
");
$notiStmt->execute(array(":student_id" => $studentId));
$notis = $notiStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($notis as $index => $notification) {
  if ($notification["RESULT"] === "需補件") {
    $notis[$index]["SUPPLEMENT_NOTE"] = supplement_note_get(
      $pdo,
      $notification["APNO"],
      isset($notification["SUPPLEMENT_NOTE"]) ? $notification["SUPPLEMENT_NOTE"] : null
    );
  }
}

$pageTitle = "學生總覽";
$activeNav = "student-dashboard.php";
$siteHeaderRequiredRole = 1;
require __DIR__ . "/../header.php";
?>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body p-4">
    <h1 class="h3 fw-bold mb-1">歡迎回來，<?= h($userName) ?></h1>
    <div class="text-secondary">查看近期申請、推薦信狀態與待處理通知。</div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-secondary">審查中</div>
        <div class="display-6 fw-bold"><?= $pending ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex align-items-center gap-2">
          <div class="text-secondary">需補件</div>
          <?php if ($needFix > 0): ?>
            <span class="badge bg-danger rounded-circle d-inline-flex align-items-center justify-content-center"
                  style="width: 24px; height: 24px; font-size: 0.75rem; padding: 0;">
              <?= $needFix ?>
            </span>
          <?php endif; ?>
        </div>
        <div class="display-6 fw-bold"><?= $needFix ?></div>
        <?php if ($needFix > 0): ?>
          <a class="btn btn-sm btn-warning mt-2" href="/scholarship/student/my_applications.php">前往補件</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-secondary">今年通過</div>
        <div class="display-6 fw-bold"><?= $approvedThisYear ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-secondary">核發總額</div>
        <div class="display-6 fw-bold">NT$ <?= number_format($paid) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-3">通知</h2>
        <?php if (empty($notis)): ?>
          <div class="text-secondary">目前沒有待處理通知。</div>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($notis as $n): ?>
              <div class="list-group-item px-0">
                <div>
                  <?= site_status_badge($n["RESULT"]) ?>
                  申請編號 <?= h($n["APNO"]) ?>
                </div>
                <div class="text-secondary small mt-1"><?= h($n["APDATE"]) ?></div>
                <?php if ($n["RESULT"] === "需補件" && !empty($n["SUPPLEMENT_NOTE"])): ?>
                  <div class="small mt-1"><?= nl2br(h($n["SUPPLEMENT_NOTE"])) ?></div>
                <?php endif; ?>
                <a class="small" href="/scholarship/student/application_detail.php?apno=<?= h($n["APNO"]) ?>">查看申請</a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-3">近期申請</h2>
        <?php if (empty($apps)): ?>
          <div class="text-secondary">目前沒有申請資料。</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>獎助學金</th>
                  <th>申請日期</th>
                  <th>申請金額</th>
                  <th>審核狀態</th>
                  <th>推薦信狀態</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($apps as $a): ?>
                  <tr>
                    <td><?= h($a["SCH_NAME"] ?: ("APNO " . $a["APNO"])) ?></td>
                    <td><?= h($a["APDATE"]) ?></td>
                    <td>NT$ <?= number_format((int)$a["AMOUNT"]) ?></td>
                    <td><?= site_status_badge($a["RESULT"]) ?></td>
                    <td>
                      <?php if (empty($a["status"]) && empty($a["content"])): ?>
                        <span class="text-secondary">尚未建立推薦信請求</span>
                      <?php else: ?>
                        <?= site_status_badge(tar_recommendation_status_label($a), "recommendation") ?>
                        <?php if (($a["status"] ?? "") === "rejected" && !empty($a["rejected_reason"])): ?>
                          <div class="small text-secondary mt-1">
                            <?= h($a["rejected_source"] === "system" ? "系統自動駁回" : "推薦人駁回") ?>：<?= h($a["rejected_reason"]) ?>
                          </div>
                        <?php endif; ?>
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
  </div>
</div>

</main>
</body>

</html>
