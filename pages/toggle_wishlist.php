<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
verify_csrf();
$user_id=(int)$_SESSION['user_id'];
$product_id=(int)($_POST['product_id']??0);
if(db_table_exists($conn,'wishlist') && $product_id>0){
    $q=mysqli_query($conn,"SELECT id FROM wishlist WHERE user_id=$user_id AND product_id=$product_id LIMIT 1");
    if($q && mysqli_num_rows($q)>0){ mysqli_query($conn,"DELETE FROM wishlist WHERE user_id=$user_id AND product_id=$product_id"); $_SESSION['success']='Produk dihapus dari wishlist.'; }
    else { mysqli_query($conn,"INSERT INTO wishlist (user_id, product_id) VALUES ($user_id,$product_id)"); $_SESSION['success']='Produk masuk wishlist.'; }
}
header('Location: detail.php?id='.$product_id); exit;
?>
