<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
verify_csrf();
$user_id=(int)$_SESSION['user_id'];
$id=(int)($_POST['id']??0);
if(db_column_exists($conn,'products','product_status')){
    mysqli_query($conn,"UPDATE products SET product_status=IF(product_status='inactive','pending_review','inactive') WHERE id=$id AND user_id=$user_id");
}
$_SESSION['success']='Status produk diperbarui.';
header('Location: seller_center.php'); exit;
?>
