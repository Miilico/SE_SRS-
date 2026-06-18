<?php
if (!defined("SITE_FLASH_HELPERS_LOADED")) {
    define("SITE_FLASH_HELPERS_LOADED", true);

    function site_flash_normalize_type($type)
    {
        $types = array(
            "success" => "success",
            "info" => "info",
            "warning" => "warning",
            "danger" => "danger",
            "error" => "danger",
            "err" => "danger",
        );

        $key = strtolower(trim((string)$type));
        return isset($types[$key]) ? $types[$key] : "info";
    }

    function site_flash_add($message, $type = "info")
    {
        $message = trim((string)$message);
        if ($message === "") {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION["flash_messages"]) || !is_array($_SESSION["flash_messages"])) {
            $_SESSION["flash_messages"] = array();
        }

        $_SESSION["flash_messages"][] = array(
            "type" => site_flash_normalize_type($type),
            "message" => $message,
        );
    }

    function site_flash_take_all()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $messages = isset($_SESSION["flash_messages"]) && is_array($_SESSION["flash_messages"])
            ? $_SESSION["flash_messages"]
            : array();

        unset($_SESSION["flash_messages"]);

        return $messages;
    }

    function site_flash_redirect($url, $message, $type = "info")
    {
        site_flash_add($message, $type);
        header("Location: " . $url);
        exit;
    }
}
