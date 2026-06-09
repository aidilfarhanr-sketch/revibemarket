<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../index.php'); exit; }
verify_csrf();

$user_id = (int)$_SESSION['user_id'];
$product_id = (int)($_POST['product_id'] ?? 0);
$qty = max(1, (int)($_POST['qty'] ?? 1));

$pq = mysqli_query($conn, "SELECT id, stock, user_id, product_status FROM products WHERE id=$product_id LIMIT 1");
$product = $pq ? mysqli_fetch_assoc($pq) : null;
if (!$product) {
    $_SESSION['error'] = 'Produk tidak ditemukan.';
    header('Location: ../index.php'); exit;
}
if (($product['product_status'] ?? 'approved') !== 'approved' || (int)$product['stock'] <= 0) {
    $_SESSION['error'] = 'Produk belum tersedia untuk dibeli.';
    header('Location: detail.php?id=' . $product_id); exit;
}
if ((int)$product['user_id'] === $user_id) {
    $_SESSION['error'] = 'Produk milik sendiri tidak bisa dimasukkan ke keranjang.';
    header('Location: detail.php?id=' . $product_id); exit;
}
$qty = min($qty, max(1, (int)$product['stock']));

$check = mysqli_query($conn, "SELECT id, qty FROM cart WHERE user_id=$user_id AND product_id=$product_id LIMIT 1");
if ($check && $row = mysqli_fetch_assoc($check)) {
    $newQty = min((int)$product['stock'], (int)$row['qty'] + $qty);
    mysqli_query($conn, "UPDATE cart SET qty=$newQty WHERE id=" . (int)$row['id']);
} else {
    mysqli_query($conn, "INSERT INTO cart (user_id, product_id, qty) VALUES ($user_id, $product_id, $qty)");
}

$_SESSION['success'] = 'Produk masuk ke keranjang.';
header('Location: cart.php');
exit;
?>
