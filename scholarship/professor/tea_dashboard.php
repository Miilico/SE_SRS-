<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../recommendation_helpers.php";

if (empty($_SESSION["user"]) || !in_array((int)$_SESSION["user"]["role"], array(2, 3), true)) {
  header("Location: /scholarship/login.php");
  exit;
}

$userId = $_SESSION["user"]["id"];
$userName = $_SESSION["user"]["name"];
$statusFilter = isset($_GET["status"]) ? $_GET["status"] : "all";
if (!in_array($statusFilter, array("all", "pending", "draft", "completed", "rejected"), true)) {
  $statusFilter = "all";
}

ensure_application_files_table($pdo);
tar_auto_reject_overdue_recommendations($pdo);

function h($value)
{
  return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_requests,
        SUM(CASE WHEN COALESCE(status, 'pending') = 'pending' THEN 1 ELSE 0 END) AS pending_requests,
        SUM(CASE WHEN COALESCE(status, 'pending') = 'draft' THEN 1 ELSE 0 END) AS draft_requests,
        SUM(CASE WHEN COALESCE(status, 'pending') = 'submitted' OR (content IS NOT NULL AND content <> '') THEN 1 ELSE 0 END) AS completed_requests,
        SUM(CASE WHEN COALESCE(status, 'pending') = 'rejected' THEN 1 ELSE 0 END) AS rejected_requests
    FROM recommendations
    WHERE teacher_id = :teacher_id
");
$statsStmt->execute(array(":teacher_id" => $userId));
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$studentsStmt = $pdo->prepare("
    SELECT
        u.ID,
        u.NAME,
        u.EMAIL,
        s.SID,
        s.DNAME,
        COUNT(r.id) AS recommendation_count,
        MAX(r.created_at) AS latest_recommendation_at
    FROM recommendations r
    JOIN application a ON r.application_id = a.APNO
    JOIN users u ON a.STID = u.ID
    LEFT JOIN students s ON u.ID = s.ID
    WHERE r.teacher_id = :teacher_id
    GROUP BY u.ID, u.NAME, u.EMAIL, s.SID, s.DNAME
    ORDER BY latest_recommendation_at DESC, u.NAME ASC
");
$studentsStmt->execute(array(":teacher_id" => $userId));
$myStudents = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

$requestWhere = "r.teacher_id = :teacher_id";
if ($statusFilter === "pending") {
  $requestWhere .= " AND COALESCE(r.status, 'pending') = 'pending'";
} elseif ($statusFilter === "draft") {
  $requestWhere .= " AND COALESCE(r.status, 'pending') = 'draft'";
} elseif ($statusFilter === "completed") {
  $requestWhere .= " AND (COALESCE(r.status, 'pending') = 'submitted' OR (r.content IS NOT NULL AND r.content <> ''))";
} elseif ($statusFilter === "rejected") {
  $requestWhere .= " AND COALESCE(r.status, 'pending') = 'rejected'";
}

$requestsStmt = $pdo->prepare("
    SELECT
        r.id,
        r.token,
        r.content,
        r.draft_content,
        r.status,
        r.created_at,
        r.expires_at,
        r.submitted_at,
        r.rejected_reason,
        r.rejected_source,
        a.APNO,
        a.APDATE,
        a.GRADE,
        a.RANK,
        u.ID AS student_id,
        u.NAME AS student_name,
        s.DNAME,
        sc.NAME AS scholarship_name
    FROM recommendations r
    JOIN application a ON r.application_id = a.APNO
    JOIN users u ON a.STID = u.ID
    LEFT JOIN students s ON u.ID = s.ID
    JOIN scholarship sc ON a.SCID = sc.id
    WHERE $requestWhere
    ORDER BY FIELD(COALESCE(r.status, 'pending'), 'pending', 'draft', 'submitted', 'rejected'),
             r.created_at DESC,
             a.APDATE DESC
    LIMIT 12
");
$requestsStmt->execute(array(":teacher_id" => $userId));
$recentRequests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "推薦人子系統";
$activeNav = "tea_dashboard.php";
$siteHeaderRequiredRole = array(2, 3);
require __DIR__ . "/../header.php";
?>

<div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-end mb-4">
  <div>
    <p class="text-secondary mb-1">TAR 推薦人與推薦信子系統</p>
    <h1 class="h3 fw-bold mb-1"><?= h($userName) ?>，您好</h1>
    <p class="text-secondary mb-0">查看學生申請資料，並管理推薦信草稿、提交與駁回。</p>
  </div>
  <a class="btn btn-outline-secondary" href="/scholarship/profile.php">個人檔案</a>
</div>

<div class="row g-3 mb-4">
  <?php
  $statCards = array(
    array("推薦邀請", "total_requests"),
    array("待填寫", "pending_requests"),
    array("草稿", "draft_requests"),
    array("已提交", "completed_requests"),
    array("已駁回", "rejected_requests"),
  );
  ?>
  <?php foreach ($statCards as $card): ?>
    <div class="col-md">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-secondary small mb-1"><?= h($card[0]) ?></div>
          <div class="h3 fw-bold mb-0"><?= (int)($stats[$card[1]] ?? 0) ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-4">
    <h2 class="h5 fw-bold mb-3">查詢學生申請資料</h2>
    <form action="student_view.php" method="get" class="row g-2 align-items-end">
      <div class="col-md-8 col-lg-5">
        <label class="form-label fw-semibold" for="sid">學生帳號或學號 <span class="text-danger" aria-label="必填">*</span></label>
        <input class="form-control" id="sid" type="text" name="sid" required>
      </div>
      <div class="col-md-auto">
        <button type="submit" class="btn btn-primary">查詢</button>
      </div>
    </form>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-3">推薦信請求</h2>
        <div class="btn-group btn-group-sm mb-3" role="group" aria-label="推薦信狀態篩選">
          <a class="btn <?= $statusFilter === "all" ? "btn-primary" : "btn-outline-primary" ?>" href="tea_dashboard.php">全部</a>
          <a class="btn <?= $statusFilter === "pending" ? "btn-primary" : "btn-outline-primary" ?>" href="tea_dashboard.php?status=pending">待填寫</a>
          <a class="btn <?= $statusFilter === "draft" ? "btn-primary" : "btn-outline-primary" ?>" href="tea_dashboard.php?status=draft">草稿</a>
          <a class="btn <?= $statusFilter === "completed" ? "btn-primary" : "btn-outline-primary" ?>" href="tea_dashboard.php?status=completed">已提交</a>
          <a class="btn <?= $statusFilter === "rejected" ? "btn-primary" : "btn-outline-primary" ?>" href="tea_dashboard.php?status=rejected">已駁回</a>
        </div>

        <?php if (empty($recentRequests)): ?>
          <p class="text-secondary mb-0">目前沒有符合條件的推薦信請求。</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>學生</th>
                  <th>獎助學金</th>
                  <th>狀態</th>
                  <th class="text-end">操作</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentRequests as $request): ?>
                  <?php $statusLabel = tar_recommendation_status_label($request); ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h($request["student_name"]) ?></div>
                      <div class="small text-secondary"><?= h($request["student_id"]) ?> <?= h($request["DNAME"] ?? "") ?></div>
                    </td>
                    <td><?= h($request["scholarship_name"]) ?></td>
                    <td>
                      <?= site_status_badge($statusLabel, "recommendation") ?>
                    </td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary" href="recommendation.php?token=<?= urlencode($request["token"]) ?>">
                        <?= $statusLabel === "已提交" || $statusLabel === "已駁回" ? "查看" : "處理" ?>
                      </a>
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

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-3">推薦關聯學生</h2>
        <?php if (empty($myStudents)): ?>
          <p class="text-secondary mb-0">尚無推薦請求關聯學生。</p>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($myStudents as $student): ?>
              <a class="list-group-item list-group-item-action px-0 d-flex justify-content-between gap-3 align-items-center" href="student_view.php?sid=<?= urlencode($student["ID"]) ?>">
                <span>
                  <span class="fw-semibold d-block"><?= h($student["NAME"]) ?></span>
                  <span class="small text-secondary"><?= h($student["ID"]) ?> <?= h($student["DNAME"] ?? "") ?></span>
                </span>
                <span class="badge text-bg-light border"><?= (int)$student["recommendation_count"] ?> 件</span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

</main>
</body>

</html>
