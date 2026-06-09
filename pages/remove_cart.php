<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
verify_csrf();
$user_id = (int)$_SESSION['user_id'];
$cart_id = (int)($_POST['cart_id'] ?? 0);
mysqli_query($conn, "DELETE FROM cart WHERE id=$cart_id AND user_id=$user_id");
$_SESSION['success'] = 'Produk dihapus dari keranjang.';
header('Location: cart.php'); exit;
?>
