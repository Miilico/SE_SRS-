<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/file_helpers.php";

require_login();

$user = $_SESSION["user"];
$userId = $user["id"];
$role = (int)$user["role"];
$isAdmin = ($role === 3);
$ticketId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$error = isset($_GET["error"]) ? trim($_GET["error"]) : "";
$ticket = null;
$messages = [];
$ticketFiles = [];
$filesByMessageId = [];

$statusMap = [
    "open" => "已開啟",
    "pending" => "待處理",
    "closed" => "已關閉"
];

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

function build_ticket_file_map($messages, $files) {
    $filesByMessageId = [];

    foreach ($messages as $message) {
        $filesByMessageId[(int)$message["MESSAGE_ID"]] = [];
    }

    foreach ($files as $file) {
        $bestMessageId = null;
        $bestTime = null;
        $fileTime = !empty($file["created_at"]) ? strtotime($file["created_at"]) : false;

        foreach ($messages as $message) {
            if ((string)$message["SENDER_ID"] !== (string)$file["uploader_id"]) {
                continue;
            }

            $messageTime = !empty($message["CREATED_AT"]) ? strtotime($message["CREATED_AT"]) : false;
            if ($fileTime !== false && $messageTime !== false && $messageTime > $fileTime) {
                continue;
            }

            if ($bestTime === null || ($messageTime !== false && $messageTime >= $bestTime)) {
                $bestMessageId = (int)$message["MESSAGE_ID"];
                $bestTime = $messageTime !== false ? $messageTime : 0;
            }
        }

        if ($bestMessageId === null) {
            foreach ($messages as $message) {
                if ((string)$message["SENDER_ID"] === (string)$file["uploader_id"]) {
                    $bestMessageId = (int)$message["MESSAGE_ID"];
                    break;
                }
            }
        }

        if ($bestMessageId !== null) {
            $filesByMessageId[$bestMessageId][] = $file;
        }
    }

    return $filesByMessageId;
}

if ($ticketId > 0) {
    $stmt = $pdo->prepare("
        SELECT
            t.TICKET_ID,
            t.USER_ID,
            u.NAME AS USER_NAME,
            t.ADMIN_ID,
            au.NAME AS ADMIN_NAME,
            t.TITLE,
            t.STATUS,
            t.CREATED_AT,
            t.UPDATED_AT
        FROM tickets t
        JOIN users u ON t.USER_ID = u.ID
        LEFT JOIN users au ON t.ADMIN_ID = au.ID
        WHERE t.TICKET_ID = :ticket_id
        LIMIT 1
    ");
    $stmt->execute([":ticket_id" => $ticketId]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        http_response_code(404);
        exit("找不到工單。");
    }

    if (!$isAdmin && $ticket["USER_ID"] !== $userId && $ticket["ADMIN_ID"] !== $userId) {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM ticket_messages
            WHERE TICKET_ID = :ticket_id
              AND SENDER_ID = :user_id
            LIMIT 1
        ");
        $stmt->execute([
            ":ticket_id" => $ticketId,
            ":user_id" => $userId
        ]);
        if (!$stmt->fetchColumn()) {
            http_response_code(403);
            exit("您沒有權限查看此工單。");
        }
    }

    $stmt = $pdo->prepare("
        SELECT
            tm.MESSAGE_ID,
            tm.SENDER_ID,
            u.NAME AS SENDER_NAME,
            u.ROLE AS SENDER_ROLE,
            tm.MESSAGE,
            tm.CREATED_AT
        FROM ticket_messages tm
        JOIN users u ON tm.SENDER_ID = u.ID
        WHERE tm.TICKET_ID = :ticket_id
        ORDER BY tm.CREATED_AT ASC, tm.MESSAGE_ID ASC
    ");
    $stmt->execute([":ticket_id" => $ticketId]);
    $messages = $stmt->fetchAll();
    $ticketFiles = fetch_uploaded_files($pdo, 3, "ticket_id", $ticketId);
    $filesByMessageId = build_ticket_file_map($messages, $ticketFiles);
}

$status = $ticket ? $ticket["STATUS"] : "open";
$statusText = isset($statusMap[$status]) ? $statusMap[$status] : $status;
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $ticket ? "回覆工單" : "新增工單" ?>｜回報問題</title>
  <style>
    body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans TC",sans-serif;margin:24px;background:#f6f7fb;color:#111827}
    .topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:16px}
    .tabs a{margin-right:14px;text-decoration:none;color:#111}
    .tabs a.active{font-weight:700;color:#2563eb}
    .layout{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(320px,.8fr);gap:14px}
    .single-layout{display:grid;gap:14px;max-width:980px;margin:0 auto}
    .card{background:#fff;border-radius:14px;padding:18px;box-shadow:0 1px 8px rgba(0,0,0,.06)}
    .muted{color:#667085}
    label{display:block;font-weight:700;margin-bottom:8px}
    input[type="text"],textarea{width:100%;box-sizing:border-box;border:1px solid #d0d5dd;border-radius:8px;padding:10px 12px;font:inherit;background:#fff}
    textarea{min-height:220px;resize:vertical}
    .field{margin-bottom:14px}
    .btn{display:inline-block;padding:9px 16px;border-radius:999px;background:#2563eb;color:#fff;text-decoration:none;font-size:14px;border:0;cursor:pointer}
    .btn.secondary{background:#f1f5f9;color:#475569}
    .badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;background:#f1f5f9;color:#344054}
    .badge.open{background:#dcfce7;color:#166534}
    .badge.pending{background:#fef9c3;color:#854d0e}
    .badge.closed{background:#e5e7eb;color:#374151}
    .message{border-bottom:1px solid #eee;padding:12px 0}
    .message:last-child{border-bottom:0}
    .message-head{display:flex;justify-content:space-between;gap:10px;margin-bottom:6px}
    .message-body{white-space:pre-wrap;line-height:1.55}
    .message-files{margin-top:10px;padding:10px 12px;background:#f8fafc;border-radius:8px}
    .message-files-title{font-size:13px;font-weight:700;margin-bottom:6px;color:#475569}
    .file-list{margin:0;padding-left:18px}
    .ticket-title{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px}
    .actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .alert{background:#fee2e2;color:#991b1b;border-radius:8px;padding:10px 12px;margin-bottom:14px}
    @media (max-width: 900px){
      body{margin:14px}
      .topbar{align-items:flex-start;flex-direction:column}
      .layout{grid-template-columns:1fr}
    }
  </style>
</head>
<body>

<div class="topbar">
  <div><strong>獎助學金系統｜回報問題</strong></div>
  <div class="tabs">
    <a href="<?= h(dashboard_url($role)) ?>">回首頁</a>
    <a class="active" href="/scholarship/ticket_list.php">工單列表</a>
    <a href="/scholarship/profile.php">個人檔案</a>
  </div>
  <div>
    <?= h($user["name"]) ?>｜
    <a href="/scholarship/logout.php">登出</a>
  </div>
</div>

<?php if ($ticket): ?>
  <div class="single-layout">
    <div class="card">
      <div class="ticket-title">
        <div>
          <h2 style="margin:0 0 6px;"><?= h($ticket["TITLE"]) ?></h2>
          <div class="muted">
            工單 #<?= h($ticket["TICKET_ID"]) ?>｜
            開單者 <?= h($ticket["USER_NAME"]) ?>（<?= h($ticket["USER_ID"]) ?>）
          </div>
        </div>
        <span class="badge <?= h($status) ?>"><?= h($statusText) ?></span>
      </div>
    </div>

    <div class="card">
      <h3 style="margin:0 0 12px;">對話紀錄</h3>
      <?php if (!$messages): ?>
        <div class="muted">目前尚無訊息。</div>
      <?php else: ?>
        <?php foreach ($messages as $message): ?>
          <div class="message">
            <div class="message-head">
              <strong><?= h($message["SENDER_NAME"]) ?>（<?= h($message["SENDER_ID"]) ?>）</strong>
              <span class="muted"><?= h($message["CREATED_AT"]) ?></span>
            </div>
            <div class="message-body"><?= h($message["MESSAGE"]) ?></div>
            <?php $messageFiles = isset($filesByMessageId[(int)$message["MESSAGE_ID"]]) ? $filesByMessageId[(int)$message["MESSAGE_ID"]] : []; ?>
            <?php if ($messageFiles): ?>
              <div class="message-files">
                <div class="message-files-title">附件</div>
                <ul class="file-list">
                  <?php foreach ($messageFiles as $file): ?>
                    <li>
                      <a href="/scholarship/file_view.php?id=<?= urlencode($file["id"]) ?>">
                        <?= h($file["original_name"]) ?>
                      </a>
                      <span class="muted">（<?= h($file["created_at"]) ?>）</span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3 style="margin:0 0 12px;">回覆</h3>
      <?php if ($error !== ""): ?>
        <div class="alert"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" action="/scholarship/submit_ticket.php" enctype="multipart/form-data">
        <input type="hidden" name="ticket_id" value="<?= h($ticket["TICKET_ID"]) ?>">

        <div class="field">
          <label for="message">內容</label>
          <textarea id="message" name="message" required></textarea>
        </div>

        <div class="field">
          <label for="ticket_file">附件（可空）</label>
          <input type="file" id="ticket_file" name="TICKET_FILE">
        </div>

        <div class="actions">
          <button class="btn" type="submit">提交回覆</button>
          <a class="btn secondary" href="/scholarship/ticket_list.php">返回列表</a>
        </div>
      </form>
    </div>
  </div>
<?php else: ?>
  <div class="layout">
    <div class="card">
      <h2 style="margin:0 0 14px;">新增工單</h2>
      <?php if ($error !== ""): ?>
        <div class="alert"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" action="/scholarship/submit_ticket.php" enctype="multipart/form-data">
        <div class="field">
          <label for="title">標題</label>
          <input type="text" id="title" name="title" maxlength="255" required>
        </div>

        <div class="field">
          <label for="message">內容</label>
          <textarea id="message" name="message" required></textarea>
        </div>

        <div class="field">
          <label for="ticket_file_new">附件（可空）</label>
          <input type="file" id="ticket_file_new" name="TICKET_FILE">
        </div>

        <div class="actions">
          <button class="btn" type="submit">提交</button>
          <a class="btn secondary" href="/scholarship/ticket_list.php">返回列表</a>
        </div>
      </form>
    </div>

    <div class="card">
      <h3 style="margin:0 0 12px;">對話紀錄</h3>
      <div class="muted">送出後會建立新的工單對話。</div>
    </div>
  </div>
<?php endif; ?>

</body>
</html>
