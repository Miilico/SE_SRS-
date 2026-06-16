<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

require_login();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /scholarship/ticket_list.php");
    exit;
}

$user = $_SESSION["user"];
$userId = $user["id"];
$isAdmin = ((int)$user["role"] === 3);
$ticketId = isset($_POST["ticket_id"]) ? (int)$_POST["ticket_id"] : 0;
$title = isset($_POST["title"]) ? trim($_POST["title"]) : "";
$message = isset($_POST["message"]) ? trim($_POST["message"]) : "";

if ($message === "") {
    $redirect = "/scholarship/ticket.php" . ($ticketId > 0 ? "?id=" . urlencode($ticketId) . "&" : "?");
    header("Location: " . $redirect . "error=" . urlencode("請輸入內容"));
    exit;
}

if ($ticketId <= 0 && $title === "") {
    header("Location: /scholarship/ticket.php?error=" . urlencode("請輸入標題"));
    exit;
}

try {
    $pdo->beginTransaction();

    if ($ticketId > 0) {
        $stmt = $pdo->prepare("
            SELECT TICKET_ID, USER_ID, ADMIN_ID
            FROM tickets
            WHERE TICKET_ID = :ticket_id
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([":ticket_id" => $ticketId]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            $pdo->rollBack();
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
                $pdo->rollBack();
                http_response_code(403);
                exit("您沒有權限回覆此工單。");
            }
        }
    } else {
        $status = $isAdmin ? "closed" : "open";
        $adminId = $isAdmin ? $userId : null;

        $stmt = $pdo->prepare("
            INSERT INTO tickets (USER_ID, ADMIN_ID, TITLE, STATUS)
            VALUES (:user_id, :admin_id, :title, :status)
        ");
        $stmt->execute([
            ":user_id" => $userId,
            ":admin_id" => $adminId,
            ":title" => $title,
            ":status" => $status
        ]);
        $ticketId = (int)$pdo->lastInsertId();
    }

    $stmt = $pdo->prepare("
        INSERT INTO ticket_messages (TICKET_ID, SENDER_ID, MESSAGE)
        VALUES (:ticket_id, :sender_id, :message)
    ");
    $stmt->execute([
        ":ticket_id" => $ticketId,
        ":sender_id" => $userId,
        ":message" => $message
    ]);

    if ($isAdmin) {
        $stmt = $pdo->prepare("
            UPDATE tickets
            SET STATUS = 'closed',
                ADMIN_ID = :admin_id
            WHERE TICKET_ID = :ticket_id
        ");
        $stmt->execute([
            ":admin_id" => $userId,
            ":ticket_id" => $ticketId
        ]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE tickets
            SET STATUS = 'open'
            WHERE TICKET_ID = :ticket_id
        ");
        $stmt->execute([":ticket_id" => $ticketId]);
    }

    $pdo->commit();
    header("Location: /scholarship/ticket.php?id=" . urlencode($ticketId));
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    exit("資料庫錯誤：" . htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8"));
}
