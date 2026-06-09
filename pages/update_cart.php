<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
verify_csrf();
$user_id = (int)$_SESSION['user_id'];
$cart_id = (int)($_POST['cart_id'] ?? 0);
$qty = max(1, (int)($_POST['qty'] ?? 1));
$q = mysqli_query($conn, "SELECT c.id, p.stock FROM cart c JOIN products p ON c.product_id=p.id WHERE c.id=$cart_id AND c.user_id=$user_id LIMIT 1");
$row = $q ? mysqli_fetch_assoc($q) : null;
if ($row) {
    $qty = min($qty, max(1, (int)$row['stock']));
    mysqli_query($conn, "UPDATE cart SET qty=$qty WHERE id=$cart_id AND user_id=$user_id");
    $_SESSION['success'] = 'Jumlah produk diperbarui.';
}
header('Location: cart.php'); exit;
?>
