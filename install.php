<?php
// Simple installer to create the database and tables from install.sql
// Visit: /student-appraisal/install.php

$host = getenv('DB_HOST') ?: '127.0.0.1';
$db   = getenv('DB_NAME') ?: 'student_appraisal';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

header('Content-Type: text/plain');

echo "SAS Installer\n";

echo "Connecting to MySQL server on {$host}...\n";
try {
  $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
} catch (Throwable $e) {
  echo "Failed to connect to MySQL server.\n";
  echo "Error: ".$e->getMessage()."\n";
  exit(1);
}

$path = __DIR__.'/install.sql';
if (!file_exists($path)) {
  echo "install.sql not found at $path\n";
  exit(1);
}

$sql = file_get_contents($path);
if ($sql === false) {
  echo "Failed to read install.sql\n";
  exit(1);
}

// Remove BOM if present
if (substr($sql, 0, 3) === "\xEF\xBB\xBF") { $sql = substr($sql, 3); }

try {
  // Enable multi-statements
  $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
  $pdo->exec($sql);
  echo "Install script executed successfully.\n";
  echo "Database '{$db}' and tables should be ready.\n";
} catch (Throwable $e) {
  echo "Error while executing install.sql:\n";
  echo $e->getMessage()."\n";
  exit(1);
}

echo "Done. You can now open /student-appraisal/index.php\n";
