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
$badgeClass = ($status === "open") ? "text-bg-success" : (($status === "pending") ? "text-bg-warning" : "text-bg-secondary");

$pageTitle = $ticket ? "回覆工單" : "新增工單";
$activeNav = "ticket_list.php";
$siteHeaderRequireLogin = true;
require __DIR__ . "/header.php";
?>

<?php if ($ticket): ?>
  <div class="row justify-content-center">
  <div class="col-12 col-xl-11">
  <div class="vstack gap-3">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
      <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
        <div>
          <h1 class="h3 fw-bold mb-2"><?= h($ticket["TITLE"]) ?></h1>
          <div class="text-secondary">
            工單 #<?= h($ticket["TICKET_ID"]) ?>｜
            開單者 <?= h($ticket["USER_NAME"]) ?>（<?= h($ticket["USER_ID"]) ?>）
          </div>
        </div>
        <span class="badge rounded-pill <?= h($badgeClass) ?> align-self-start"><?= h($statusText) ?></span>
      </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
      <h2 class="h5 fw-bold mb-3">對話紀錄</h2>
      <?php if (!$messages): ?>
        <div class="text-secondary">目前尚無訊息。</div>
      <?php else: ?>
        <?php foreach ($messages as $message): ?>
          <div class="border-bottom py-3">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-2">
              <strong><?= h($message["SENDER_NAME"]) ?>（<?= h($message["SENDER_ID"]) ?>）</strong>
              <span class="text-secondary small"><?= h($message["CREATED_AT"]) ?></span>
            </div>
            <div class="lh-lg"><?= nl2br(h($message["MESSAGE"])) ?></div>
            <?php $messageFiles = isset($filesByMessageId[(int)$message["MESSAGE_ID"]]) ? $filesByMessageId[(int)$message["MESSAGE_ID"]] : []; ?>
            <?php if ($messageFiles): ?>
              <div class="bg-body-tertiary rounded p-3 mt-3">
                <div class="fw-semibold small text-secondary mb-2">附件</div>
                <ul class="mb-0">
                  <?php foreach ($messageFiles as $file): ?>
                    <li>
                      <a href="/scholarship/file_view.php?id=<?= urlencode($file["id"]) ?>">
                        <?= h($file["original_name"]) ?>
                      </a>
                      <span class="text-secondary">（<?= h($file["created_at"]) ?>）</span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
      <h2 class="h5 fw-bold mb-3">回覆</h2>
      <form method="post" action="/scholarship/submit_ticket.php" enctype="multipart/form-data">
        <input type="hidden" name="ticket_id" value="<?= h($ticket["TICKET_ID"]) ?>">

        <div class="mb-3">
          <label class="form-label fw-semibold" for="message">內容</label>
          <textarea class="form-control" id="message" name="message" rows="7" required></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold" for="ticket_file">附件（可空）</label>
          <input class="form-control" type="file" id="ticket_file" name="TICKET_FILE">
        </div>

        <div class="d-flex flex-wrap gap-2">
          <button class="btn btn-primary" type="submit">提交回覆</button>
          <a class="btn btn-outline-secondary" href="/scholarship/ticket_list.php">返回列表</a>
        </div>
      </form>
      </div>
    </div>
  </div>
  </div>
  </div>
<?php else: ?>
  <div class="row g-3">
    <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
      <h1 class="h3 fw-bold mb-3">新增工單</h1>
      <form method="post" action="/scholarship/submit_ticket.php" enctype="multipart/form-data">
        <div class="mb-3">
          <label class="form-label fw-semibold" for="title">標題</label>
          <input class="form-control" type="text" id="title" name="title" maxlength="255" required>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold" for="message">內容</label>
          <textarea class="form-control" id="message" name="message" rows="8" required></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold" for="ticket_file_new">附件（可空）</label>
          <input class="form-control" type="file" id="ticket_file_new" name="TICKET_FILE">
        </div>

        <div class="d-flex flex-wrap gap-2">
          <button class="btn btn-primary" type="submit">提交</button>
          <a class="btn btn-outline-secondary" href="/scholarship/ticket_list.php">返回列表</a>
        </div>
      </form>
      </div>
    </div>
    </div>

    <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
      <h2 class="h5 fw-bold mb-2">對話紀錄</h2>
      <div class="text-secondary">送出後會建立新的工單對話。</div>
      </div>
    </div>
    </div>
  </div>
<?php endif; ?>

</main>
</body>
</html>
