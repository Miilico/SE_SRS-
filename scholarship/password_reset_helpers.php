<?php

function ensure_password_resets_table($pdo)
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id char(10) NOT NULL,
            token_hash char(64) NOT NULL,
            expires_at datetime NOT NULL,
            used_at datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_password_resets_token_hash (token_hash),
            KEY idx_password_resets_user_id (user_id),
            KEY idx_password_resets_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function password_reset_token_hash($token)
{
    return hash("sha256", $token);
}

function password_reset_url($token)
{
    $host = isset($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : "127.0.0.1";
    return "https://" . $host . "/scholarship/reset_password.php?token=" . urlencode($token);
}

function create_password_reset($pdo, $userId)
{
    ensure_password_resets_table($pdo);

    $token = bin2hex(random_bytes(32));
    $tokenHash = password_reset_token_hash($token);

    $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
        ->execute(array($userId));

    $stmt = $pdo->prepare("
        INSERT INTO password_resets (user_id, token_hash, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
    ");
    $stmt->execute(array($userId, $tokenHash));

    return $token;
}

function find_valid_password_reset($pdo, $token)
{
    ensure_password_resets_table($pdo);

    $stmt = $pdo->prepare("
        SELECT pr.*, u.ID, u.NAME, u.EMAIL
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.ID
        WHERE pr.token_hash = ?
          AND pr.used_at IS NULL
          AND pr.expires_at >= NOW()
        LIMIT 1
    ");
    $stmt->execute(array(password_reset_token_hash($token)));

    return $stmt->fetch(PDO::FETCH_ASSOC);
}
