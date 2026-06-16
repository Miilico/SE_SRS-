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
    2 => "教師",
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
        WHERE t.USER_ID = :user_id
           OR t.ADMIN_ID = :user_id
           OR EXISTS (
               SELECT 1
               FROM ticket_messages own_tm
               WHERE own_tm.TICKET_ID = t.TICKET_ID
                 AND own_tm.SENDER_ID = :user_id
           )
        GROUP BY t.TICKET_ID, t.USER_ID, u.NAME, u.ROLE, t.ADMIN_ID, au.NAME, t.TITLE, t.STATUS, t.CREATED_AT, t.UPDATED_AT
        ORDER BY t.UPDATED_AT DESC, t.TICKET_ID DESC
    ");
    $stmt->execute([":user_id" => $userId]);
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
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>回報問題｜工單列表</title>
  <style>
    body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans TC",sans-serif;margin:24px;background:#f6f7fb;color:#111827}
    .topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:16px}
    .tabs a{margin-right:14px;text-decoration:none;color:#111}
    .tabs a.active{font-weight:700;color:#2563eb}
    .card{background:#fff;border-radius:14px;padding:18px;box-shadow:0 1px 8px rgba(0,0,0,.06)}
    .header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
    .muted{color:#667085}
    .btn{display:inline-block;padding:8px 14px;border-radius:999px;background:#2563eb;color:#fff;text-decoration:none;font-size:14px;border:0;cursor:pointer}
    .btn.secondary{background:#f1f5f9;color:#475569}
    .filters{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 16px}
    .filter-btn{padding:8px 12px;border:1px solid #d0d5dd;border-radius:999px;background:#fff;color:#344054;cursor:pointer}
    .filter-btn.active{background:#2563eb;color:#fff;border-color:#2563eb}
    table{width:100%;border-collapse:collapse}
    th,td{padding:12px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}
    th{color:#667085;font-weight:600;font-size:14px}
    .badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;background:#f1f5f9;color:#344054}
    .badge.open{background:#dcfce7;color:#166534}
    .badge.pending{background:#fef9c3;color:#854d0e}
    .badge.closed{background:#e5e7eb;color:#374151}
    .empty{padding:34px;text-align:center;color:#667085}
    @media (max-width: 760px){
      body{margin:14px}
      .topbar,.header{align-items:flex-start;flex-direction:column}
      table,thead,tbody,tr,th,td{display:block}
      thead{display:none}
      tr{border-bottom:1px solid #eee;padding:8px 0}
      td{border-bottom:0;padding:7px 0}
      td::before{content:attr(data-label);display:block;color:#667085;font-size:12px;margin-bottom:3px}
    }
  </style>
</head>
<body>

<div class="topbar">
  <div><strong>獎助學金系統｜回報問題</strong></div>
  <div class="tabs">
    <a href="<?= h(dashboard_url($role)) ?>">回首頁</a>
    <a class="active" href="/scholarship/ticket_list.php">回報問題</a>
    <a href="/scholarship/profile.php">個人檔案</a>
  </div>
  <div>
    <?= h($user["name"]) ?>｜
    <a href="/scholarship/logout.php">登出</a>
  </div>
</div>

<div class="card">
  <div class="header">
    <div>
      <h2 style="margin:0;">工單列表</h2>
      <div class="muted" style="margin-top:6px;">
        <?= $isAdmin ? "目前顯示所有使用者工單。" : "目前顯示與您帳號相關的工單。" ?>
      </div>
    </div>
    <a class="btn" href="/scholarship/ticket.php">新增工單</a>
  </div>

  <?php if ($isAdmin): ?>
    <div class="filters" aria-label="工單狀態篩選">
      <button type="button" class="filter-btn active" data-status="all">全部</button>
      <button type="button" class="filter-btn" data-status="open">已開啟</button>
      <button type="button" class="filter-btn" data-status="pending">待處理</button>
      <button type="button" class="filter-btn" data-status="closed">已關閉</button>
    </div>
  <?php endif; ?>

  <?php if (!$tickets): ?>
    <div class="empty">目前沒有工單。</div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>編號</th>
          <th>標題</th>
          <?php if ($isAdmin): ?><th>開單者</th><?php endif; ?>
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
          ?>
          <tr data-status="<?= h($status) ?>">
            <td data-label="編號">#<?= h($ticket["TICKET_ID"]) ?></td>
            <td data-label="標題"><?= h($ticket["TITLE"]) ?></td>
            <?php if ($isAdmin): ?>
              <td data-label="開單者">
                <?= h($ticket["USER_NAME"]) ?>（<?= h($ticket["USER_ID"]) ?> / <?= h($roleText) ?>）
              </td>
            <?php endif; ?>
            <td data-label="狀態"><span class="badge <?= h($status) ?>"><?= h($statusText) ?></span></td>
            <td data-label="訊息數"><?= h($ticket["MESSAGE_COUNT"]) ?></td>
            <td data-label="更新時間"><?= h($ticket["UPDATED_AT"]) ?></td>
            <td data-label="操作">
              <a class="btn secondary" href="/scholarship/ticket.php?id=<?= urlencode($ticket["TICKET_ID"]) ?>">查看 / 回覆</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
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

</body>
</html>
