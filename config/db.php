<?php

require_once __DIR__ . '/env.php';

$host = (string)revibe_env('DB_HOST', revibe_env('REVIBE_DB_HOST', 'localhost'));
$port = (int)revibe_env('DB_PORT', 3306);
$db   = (string)revibe_env('DB_NAME', revibe_env('REVIBE_DB_NAME', 'revibe_market'));
$user = (string)revibe_env('DB_USER', revibe_env('REVIBE_DB_USER', 'root'));
$pass = (string)revibe_env('DB_PASS', revibe_env('REVIBE_DB_PASS', ''));
$charset = (string)revibe_env('DB_CHARSET', 'utf8mb4');
$sslMode = strtolower((string)revibe_env('DB_SSL_MODE', 'preferred'));
$clientFlags = 0;

if (!extension_loaded('mysqli') || !function_exists('mysqli_init')) {
    error_log('ReVibe DB extension mysqli is not loaded');
    http_response_code(503);
    die('Ekstensi database belum aktif di server. Aktifkan mysqli/pdo_mysql.');
}

$conn = mysqli_init();
if (!$conn) {
    error_log('ReVibe DB init failed');
    http_response_code(503);
    die('Layanan sedang tidak tersedia. Silakan coba beberapa saat lagi.');
}

mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
if (in_array($sslMode, ['preferred','required'], true)) {
    @mysqli_ssl_set($conn, null, null, null, null, null);
    $clientFlags = MYSQLI_CLIENT_SSL;
}
$connected = @mysqli_real_connect($conn, $host, $user, $pass, $db, $port, null, $clientFlags);
if (!$connected && $sslMode === 'preferred') {
    $conn = mysqli_init();
    mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    $connected = @mysqli_real_connect($conn, $host, $user, $pass, $db, $port);
}

if (!$connected) {
    error_log('ReVibe DB connection failed: ' . mysqli_connect_error());
    http_response_code(503);
    $msg = revibe_is_debug()
        ? 'Koneksi database gagal: ' . htmlspecialchars(mysqli_connect_error(), ENT_QUOTES, 'UTF-8')
        : 'Database belum terhubung.';
    die($msg);
}

mysqli_set_charset($conn, $charset);
?>
