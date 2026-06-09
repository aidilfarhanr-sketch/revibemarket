<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
revibe_require_verified_account($conn, 'verification_required.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: seller_center.php');
    exit;
}

verify_csrf();
$user_id = (int)($_SESSION['user_id'] ?? 0);
$product_id = (int)($_POST['id'] ?? 0);

if ($product_id <= 0) {
    $_SESSION['error'] = 'Produk tidak valid.';
    header('Location: seller_center.php');
    exit;
}

$productQ = mysqli_query($conn, "SELECT * FROM products WHERE id=$product_id AND user_id=$user_id LIMIT 1");
$product = $productQ ? mysqli_fetch_assoc($productQ) : null;
if (!$product) {
    $_SESSION['error'] = 'Produk tidak ditemukan atau bukan milik kamu.';
    header('Location: seller_center.php');
    exit;
}

function revibe_delete_product_file_safely($conn, $filename) {
    $filename = basename((string)$filename);
    if ($filename === '' || $filename === 'default.png') return;
    try {
        (new StorageService($conn))->delete('products/' . $filename);
    } catch (Throwable $e) {
        if (function_exists('revibe_log')) revibe_log('warning', 'delete product file failed', ['file'=>$filename, 'error'=>$e->getMessage()]);
    }
    $root = realpath(__DIR__ . '/..');
    foreach ([$root . '/uploads/products/', $root . '/assets/images/'] as $dir) {
        $path = $dir . $filename;
        if (is_file($path)) @unlink($path);
    }
}

$orderCount = 0;
if (db_table_exists($conn, 'orders')) {
    $orderQ = mysqli_query($conn, "SELECT COUNT(*) total FROM orders WHERE product_id=$product_id");
    $orderCount = $orderQ ? (int)(mysqli_fetch_assoc($orderQ)['total'] ?? 0) : 0;
}

try {
    mysqli_begin_transaction($conn);

    if ($orderCount > 0) {
        $sets = ['stock=0'];
        if (db_column_exists($conn, 'products', 'product_status')) $sets[] = "product_status='inactive'";
        if (db_column_exists($conn, 'products', 'is_active')) $sets[] = 'is_active=0';
        mysqli_query($conn, 'UPDATE products SET ' . implode(', ', $sets) . " WHERE id=$product_id AND user_id=$user_id");
        if (db_table_exists($conn, 'cart')) mysqli_query($conn, "DELETE FROM cart WHERE product_id=$product_id");
        if (db_table_exists($conn, 'wishlist')) mysqli_query($conn, "DELETE FROM wishlist WHERE product_id=$product_id");
        mysqli_commit($conn);
        $_SESSION['success'] = 'Produk sudah memiliki riwayat transaksi, jadi tidak dihapus permanen. Produk sudah disembunyikan dari marketplace dan stok dibuat 0 agar data order tetap aman.';
        header('Location: seller_center.php');
        exit;
    }

    $filesToDelete = [];
    if (!empty($product['minus_photo'])) $filesToDelete[] = $product['minus_photo'];
    if (db_table_exists($conn, 'product_images')) {
        $imgQ = mysqli_query($conn, "SELECT image FROM product_images WHERE product_id=$product_id");
        while ($imgQ && $img = mysqli_fetch_assoc($imgQ)) {
            if (!empty($img['image'])) $filesToDelete[] = $img['image'];
        }
        mysqli_query($conn, "DELETE FROM product_images WHERE product_id=$product_id");
    }
    if (db_table_exists($conn, 'cart')) mysqli_query($conn, "DELETE FROM cart WHERE product_id=$product_id");
    if (db_table_exists($conn, 'wishlist')) mysqli_query($conn, "DELETE FROM wishlist WHERE product_id=$product_id");
    if (db_table_exists($conn, 'chat_messages')) mysqli_query($conn, "UPDATE chat_messages SET product_id=NULL WHERE product_id=$product_id");
    mysqli_query($conn, "DELETE FROM products WHERE id=$product_id AND user_id=$user_id");

    foreach (array_unique($filesToDelete) as $file) {
        revibe_delete_product_file_safely($conn, $file);
    }

    mysqli_commit($conn);
    $_SESSION['success'] = 'Produk dan foto yang salah upload berhasil dihapus.';
} catch (Throwable $e) {
    mysqli_rollback($conn);
    if (function_exists('revibe_log')) revibe_log('error', 'seller delete product failed', ['product_id'=>$product_id, 'error'=>$e->getMessage()]);
    $_SESSION['error'] = 'Produk gagal dihapus. Silakan coba lagi.';
}

header('Location: seller_center.php');
exit;
?>
