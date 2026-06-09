<?php

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/db.php';

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS schema_migrations (id INT AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255) NOT NULL UNIQUE, executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$dir = __DIR__ . '/../database/migrations';
$files = glob($dir . '/*.sql');
sort($files);
foreach ($files as $file) {
    $name = basename($file);
    $stmt = mysqli_prepare($conn, "SELECT id FROM schema_migrations WHERE migration=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $name);
    mysqli_stmt_execute($stmt);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) { echo "SKIP $name
"; continue; }
    $sql = file_get_contents($file);
    echo "RUN  $name
";
    if (!mysqli_multi_query($conn, $sql)) { fwrite(STDERR, "FAILED $name: " . mysqli_error($conn) . "
"); exit(1); }
    do { if ($res = mysqli_store_result($conn)) mysqli_free_result($res); } while (mysqli_more_results($conn) && mysqli_next_result($conn));
    if (mysqli_errno($conn)) { fwrite(STDERR, "FAILED $name: " . mysqli_error($conn) . "
"); exit(1); }
    $stmt = mysqli_prepare($conn, "INSERT INTO schema_migrations (migration) VALUES (?)");
    mysqli_stmt_bind_param($stmt, 's', $name);
    mysqli_stmt_execute($stmt);
}
echo "Migrations selesai.
";
