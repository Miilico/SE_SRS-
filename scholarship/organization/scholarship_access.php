<?php
require_once __DIR__ . "/../auth.php";

function organization_current_role()
{
    return isset($_SESSION["user"]["role"]) ? (int)$_SESSION["user"]["role"] : 0;
}

function organization_current_user_id()
{
    return isset($_SESSION["user"]["id"]) ? (string)$_SESSION["user"]["id"] : "";
}

function organization_is_admin()
{
    return organization_current_role() === 3;
}

function organization_require_scholarship_manager()
{
    require_role(array(3, 4));
}

function organization_provider_display_expr()
{
    return "COALESCE(NULLIF(o.ONAME, ''), NULLIF(u.NAME, ''), s.provider_id)";
}

function organization_provider_options($pdo)
{
    $stmt = $pdo->query("
        SELECT u.ID, COALESCE(NULLIF(o.ONAME, ''), NULLIF(u.NAME, ''), u.ID) AS provider_name
        FROM users u
        LEFT JOIN organization o ON o.ID = u.ID
        WHERE u.ROLE = 4
        ORDER BY provider_name ASC, u.ID ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function organization_validate_provider($pdo, $providerId)
{
    $stmt = $pdo->prepare("
        SELECT u.ID, COALESCE(NULLIF(o.ONAME, ''), NULLIF(u.NAME, ''), u.ID) AS provider_name
        FROM users u
        LEFT JOIN organization o ON o.ID = u.ID
        WHERE u.ID = ? AND u.ROLE = 4
    ");
    $stmt->execute(array($providerId));
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function organization_fetch_managed_scholarship($pdo, $scholarshipId)
{
    if (organization_is_admin()) {
        $providerName = organization_provider_display_expr();
        $stmt = $pdo->prepare("
            SELECT s.*, {$providerName} AS provider_name
            FROM scholarship s
            JOIN users u ON u.ID = s.provider_id AND u.ROLE = 4
            LEFT JOIN organization o ON o.ID = s.provider_id
            WHERE s.id = ?
        ");
        $stmt->execute(array($scholarshipId));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $stmt = $pdo->prepare("SELECT * FROM scholarship WHERE id = ? AND provider_id = ?");
    $stmt->execute(array($scholarshipId, organization_current_user_id()));
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
