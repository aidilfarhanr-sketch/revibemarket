<?php
require_once __DIR__ . '/config/env.php';
header('Content-Type: application/json; charset=utf-8');
$rid = bin2hex(random_bytes(8));
header('X-Request-Id: ' . $rid);
$checks = [];
$root = __DIR__;
$checks['php_berjalan'] = ['ok' => true, 'message' => 'PHP berjalan ' . PHP_VERSION];
$envPath = $root . '/.env';
$checks['env_terbaca'] = ['ok' => is_readable($envPath), 'message' => is_readable($envPath) ? '.env terbaca.' : '.env belum terbaca.'];
$debugOff = !filter_var(revibe_env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
$checks['app_debug_false'] = ['ok' => $debugOff, 'message' => $debugOff ? 'APP_DEBUG sudah false.' : 'APP_DEBUG masih true.'];
$appUrl = trim((string)revibe_env('APP_URL', ''));
$checks['app_url'] = ['ok' => $appUrl !== '', 'message' => $appUrl !== '' ? 'APP_URL sudah diisi.' : 'APP_URL masih kosong.'];
$conn = null;
try {
    $conn = @mysqli_connect(
        (string)revibe_env('DB_HOST', 'localhost'),
        (string)revibe_env('DB_USER', 'root'),
        (string)revibe_env('DB_PASS', ''),
        (string)revibe_env('DB_NAME', 'revibemarket'),
        (int)revibe_env('DB_PORT', 3306)
    );
    if ($conn) @mysqli_set_charset($conn, (string)revibe_env('DB_CHARSET', 'utf8mb4'));
} catch (Throwable $e) {
    $conn = null;
}
$checks['database'] = ['ok' => $conn instanceof mysqli, 'message' => $conn instanceof mysqli ? 'Database terhubung.' : 'Database belum terhubung.'];
$uploadFolders = ['uploads','uploads/products','uploads/profile','uploads/payment_proofs','uploads/proofs'];
$folderResults = [];
$foldersOk = true;
foreach ($uploadFolders as $folder) {
    $path = $root . '/' . $folder;
    if (!is_dir($path)) @mkdir($path, 0755, true);
    $ok = is_dir($path) && is_writable($path);
    $foldersOk = $foldersOk && $ok;
    $folderResults[$folder] = $ok ? 'writable' : 'belum writable';
}
$checks['uploads_writable'] = ['ok' => $foldersOk, 'message' => $foldersOk ? 'Folder uploads penting writable.' : 'Ada folder uploads yang belum writable.', 'folders' => $folderResults];
$requiredTables = ['users','products','orders','order_items','payments','reviews','cart','wishlist'];
$tableResults = [];
$tablesOk = $conn instanceof mysqli;
if ($conn instanceof mysqli) {
    foreach ($requiredTables as $table) {
        $safe = mysqli_real_escape_string($conn, $table);
        $q = @mysqli_query($conn, "SHOW TABLES LIKE '$safe'");
        $ok = $q && mysqli_num_rows($q) > 0;
        $tableResults[$table] = $ok ? 'ada' : 'belum ada';
        $tablesOk = $tablesOk && $ok;
    }
    $cartAlias = @mysqli_query($conn, "SHOW TABLES LIKE 'carts'");
    $wishAlias = @mysqli_query($conn, "SHOW TABLES LIKE 'wishlists'");
    $tableResults['carts_alias'] = $cartAlias && mysqli_num_rows($cartAlias) > 0 ? 'ada' : 'boleh memakai tabel cart';
    $tableResults['wishlists_alias'] = $wishAlias && mysqli_num_rows($wishAlias) > 0 ? 'ada' : 'boleh memakai tabel wishlist';
}
$checks['tabel_penting'] = ['ok' => $tablesOk, 'message' => $tablesOk ? 'Tabel penting tersedia.' : 'Ada tabel penting yang belum tersedia.', 'tables' => $tableResults];
$serviceFee = (float)revibe_env('REVIBE_SERVICE_FEE_PERCENT', 12);
$sellerCashback = (float)revibe_env('REVIBE_SELLER_CASHBACK_PERCENT', revibe_env('SELLER_COIN_CASHBACK_PERCENT', 6));
$paymentMode = (string)revibe_env('PAYMENT_MODE', 'manual_demo');
$checks['service_fee_12'] = ['ok' => abs($serviceFee - 12) < 0.001, 'message' => 'Biaya layanan aktif ' . $serviceFee . '%.'];
$checks['cashback_seller_6'] = ['ok' => abs($sellerCashback - 6) < 0.001, 'message' => 'Cashback seller aktif ' . $sellerCashback . '%.'];
$checks['payment_manual_demo'] = ['ok' => $paymentMode === 'manual_demo', 'message' => 'Payment mode: ' . $paymentMode];
$dummyOk = true;
if ($conn instanceof mysqli) {
    $q = @mysqli_query($conn, "SELECT COUNT(*) total FROM reviews r LEFT JOIN users u ON u.id = r.user_id WHERE CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) IN ('Rina A.','Dimas P.','Maya S.') OR r.comment LIKE '%contoh tampilan ulasan%'");
    if ($q) {
        $row = mysqli_fetch_assoc($q);
        $dummyOk = (int)($row['total'] ?? 0) === 0;
    }
}
$checks['tanpa_dummy_review'] = ['ok' => $dummyOk, 'message' => $dummyOk ? 'Tidak ada dummy review aktif.' : 'Masih ada dummy review yang perlu dihapus.'];
$checks['default_png'] = ['ok' => is_file($root . '/assets/images/default.png'), 'message' => is_file($root . '/assets/images/default.png') ? 'default.png tersedia.' : 'default.png belum tersedia.'];
$ok = true;
foreach ($checks as $check) $ok = $ok && !empty($check['ok']);
if ($conn instanceof mysqli) @mysqli_close($conn);
http_response_code($ok ? 200 : 503);
echo json_encode([
    'success' => $ok,
    'ok' => $ok,
    'mode' => $_GET['mode'] ?? 'cloudflare-demo',
    'catatan' => 'Demo saja, jangan transfer uang asli.',
    'checks' => $checks,
    'request_id' => $rid,
    'time' => date('c')
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
