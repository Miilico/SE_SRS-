<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

require_login();

$user = $_SESSION["user"];
$userId = $user["id"];
$role = (int)$user["role"];
$isAdmin = ($role === 3);

$roleMap = [
    1 => "學生",
    2 => "推薦人",
    3 => "管理員",
    4 => "獎助單位"
];

$statusMap = [
    "open" => "已開啟",
    "pending" => "待處理",
    "closed" => "已關閉"
];

if ($isAdmin) {
    $stmt = $pdo->prepare("
        SELECT
            t.TICKET_ID,
            t.USER_ID,
            u.NAME AS USER_NAME,
            u.ROLE AS USER_ROLE,
            t.ADMIN_ID,
            au.NAME AS ADMIN_NAME,
            t.TITLE,
            t.STATUS,
            t.CREATED_AT,
            t.UPDATED_AT,
            COUNT(tm.MESSAGE_ID) AS MESSAGE_COUNT
        FROM tickets t
        JOIN users u ON t.USER_ID = u.ID
        LEFT JOIN users au ON t.ADMIN_ID = au.ID
        LEFT JOIN ticket_messages tm ON t.TICKET_ID = tm.TICKET_ID
        GROUP BY t.TICKET_ID, t.USER_ID, u.NAME, u.ROLE, t.ADMIN_ID, au.NAME, t.TITLE, t.STATUS, t.CREATED_AT, t.UPDATED_AT
        ORDER BY t.UPDATED_AT DESC, t.TICKET_ID DESC
    ");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("
        SELECT
            t.TICKET_ID,
            t.USER_ID,
            u.NAME AS USER_NAME,
            u.ROLE AS USER_ROLE,
            t.ADMIN_ID,
            au.NAME AS ADMIN_NAME,
            t.TITLE,
            t.STATUS,
            t.CREATED_AT,
            t.UPDATED_AT,
            COUNT(tm.MESSAGE_ID) AS MESSAGE_COUNT
        FROM tickets t
        JOIN users u ON t.USER_ID = u.ID
        LEFT JOIN users au ON t.ADMIN_ID = au.ID
        LEFT JOIN ticket_messages tm ON t.TICKET_ID = tm.TICKET_ID
        WHERE t.USER_ID = :owner_user_id
           OR t.ADMIN_ID = :assigned_user_id
           OR EXISTS (
               SELECT 1
               FROM ticket_messages own_tm
               WHERE own_tm.TICKET_ID = t.TICKET_ID
                 AND own_tm.SENDER_ID = :message_user_id
           )
        GROUP BY t.TICKET_ID, t.USER_ID, u.NAME, u.ROLE, t.ADMIN_ID, au.NAME, t.TITLE, t.STATUS, t.CREATED_AT, t.UPDATED_AT
        ORDER BY t.UPDATED_AT DESC, t.TICKET_ID DESC
    ");
    $stmt->execute(array(
        ":owner_user_id" => $userId,
        ":assigned_user_id" => $userId,
        ":message_user_id" => $userId,
    ));
}

$tickets = $stmt->fetchAll();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function dashboard_url($role) {
    if ((int)$role === 1) {
        return "/scholarship/student/student-dashboard.php";
    }
    if ((int)$role === 2) {
        return "/scholarship/professor/tea_dashboard.php";
    }
    if ((int)$role === 3) {
        return "/scholarship/admin/admin_dashboard.php";
    }
    return "/scholarship/organization/org-dashboard.php";
}
$pageTitle = "工單列表";
$activeNav = "ticket_list.php";
$siteHeaderRequireLogin = true;
require __DIR__ . "/header.php";
?>

<div class="card border-0 shadow-sm">
  <div class="card-body p-4">
  <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
    <div>
      <h1 class="h3 fw-bold mb-1">工單列表</h1>
      <div class="text-secondary">
        <?= $isAdmin ? "目前顯示所有使用者工單。" : "目前顯示與您帳號相關的工單。" ?>
      </div>
    </div>
    <a class="btn btn-primary align-self-start" href="/scholarship/ticket.php">新增工單</a>
  </div>

  <?php if ($isAdmin): ?>
    <div class="d-flex flex-wrap gap-2 mb-3" aria-label="工單狀態篩選">
      <button type="button" class="btn btn-outline-primary filter-btn active" data-status="all">全部</button>
      <button type="button" class="btn btn-outline-primary filter-btn" data-status="open">已開啟</button>
      <button type="button" class="btn btn-outline-primary filter-btn" data-status="pending">待處理</button>
      <button type="button" class="btn btn-outline-primary filter-btn" data-status="closed">已關閉</button>
    </div>
  <?php endif; ?>

  <?php if (!$tickets): ?>
    <div class="text-center text-secondary py-5">目前沒有工單。</div>
  <?php else: ?>
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>編號</th>
          <th>標題</th>
          <?php if ($isAdmin): ?><th>相關人</th><?php endif; ?>
          <th>狀態</th>
          <th>訊息數</th>
          <th>更新時間</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tickets as $ticket): ?>
          <?php
            $status = $ticket["STATUS"];
            $statusText = isset($statusMap[$status]) ? $statusMap[$status] : $status;
            $roleText = isset($roleMap[(int)$ticket["USER_ROLE"]]) ? $roleMap[(int)$ticket["USER_ROLE"]] : "未知";
            $badgeClass = ($status === "open") ? "text-bg-success" : (($status === "pending") ? "text-bg-warning" : "text-bg-secondary");
          ?>
          <tr data-status="<?= h($status) ?>">
            <td data-label="編號">#<?= h($ticket["TICKET_ID"]) ?></td>
            <td data-label="標題"><?= h($ticket["TITLE"]) ?></td>
            <?php if ($isAdmin): ?>
              <td data-label="相關人">
                <?= h($ticket["USER_NAME"]) ?>（<?= h($ticket["USER_ID"]) ?> / <?= h($roleText) ?>）
              </td>
            <?php endif; ?>
            <td data-label="狀態"><span class="badge rounded-pill <?= h($badgeClass) ?>"><?= h($statusText) ?></span></td>
            <td data-label="訊息數"><?= h($ticket["MESSAGE_COUNT"]) ?></td>
            <td data-label="更新時間"><?= h($ticket["UPDATED_AT"]) ?></td>
            <td data-label="操作">
              <a class="btn btn-outline-secondary btn-sm" href="/scholarship/ticket.php?id=<?= urlencode($ticket["TICKET_ID"]) ?>">查看 / 回覆</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
  </div>
</div>

<?php if ($isAdmin): ?>
<script>
document.querySelectorAll(".filter-btn").forEach(function(button) {
  button.addEventListener("click", function() {
    var status = button.getAttribute("data-status");

    document.querySelectorAll(".filter-btn").forEach(function(item) {
      item.classList.toggle("active", item === button);
    });

    document.querySelectorAll("tbody tr[data-status]").forEach(function(row) {
      row.style.display = (status === "all" || row.getAttribute("data-status") === status) ? "" : "none";
    });
  });
});
</script>
<?php endif; ?>

</main>
</body>
</html>
