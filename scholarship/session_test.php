<?php
require_once __DIR__ . "/config.php";

if (!isset($_SESSION["n"])) $_SESSION["n"] = 0;
$_SESSION["n"]++;

echo "session_id=" . session_id() . "<br>";
echo "n=" . $_SESSION["n"] . "<br>";
