<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
revibe_require_verified_account($conn, 'verification_required.php');

$user_id = (int)$_SESSION['user_id'];
$product_id = (int)($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
$seller_id = (int)($_GET['seller_id'] ?? $_POST['seller_id'] ?? 0);

if ($product_id > 0) {
    $q = mysqli_query($conn, "SELECT id, user_id, name FROM products WHERE id=$product_id LIMIT 1");
    $product = $q ? mysqli_fetch_assoc($q) : null;
    if (!$product) {
        $_SESSION['error'] = 'Produk tidak ditemukan.';
        header('Location: ../index.php');
        exit;
    }
    $seller_id = (int)($product['user_id'] ?? 0);
}

if ($seller_id <= 0) {
    $_SESSION['error'] = 'Penjual produk ini belum terhubung ke akun user, jadi chat belum bisa dimulai.';
    header('Location: ' . ($product_id > 0 ? 'detail.php?id=' . $product_id : 'messages.php'));
    exit;
}

if ($seller_id === $user_id) {
    $_SESSION['error'] = 'Ini produk kamu sendiri, jadi kamu tidak bisa chat sebagai pembeli.';
    header('Location: ' . ($product_id > 0 ? 'detail.php?id=' . $product_id : 'messages.php'));
    exit;
}

if(!db_table_exists($conn, 'chat_messages')){ $_SESSION['error']='Chat belum siap. Jalankan migration database 005_create_chat.sql.'; header('Location: ' . ($product_id > 0 ? 'detail.php?id=' . $product_id : 'messages.php')); exit; }
header('Location: chat.php?user_id=' . $seller_id . ($product_id > 0 ? '&product_id=' . $product_id : ''));
exit;
?>
