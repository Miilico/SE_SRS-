<?php
//declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$pdo = new PDO(
  "mysql:host=localhost;dbname=scholarship;charset=utf8mb4",
  "root",
  "a1125518",
  [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]
);
