<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
verify_csrf();
$user_id=(int)$_SESSION['user_id'];
$store=trim($_POST['store_name']??'');
if($store==='') $store=($_SESSION['user_name'] ?? 'ReVibe').' Store';
if(db_table_exists($conn,'sellers')){
    $storeSafe=mysqli_real_escape_string($conn,$store);
    mysqli_query($conn,"INSERT INTO sellers (user_id, store_name, verification_status) VALUES ($user_id, '$storeSafe', 'verified') ON DUPLICATE KEY UPDATE store_name=VALUES(store_name), verification_status='verified'");
}
$_SESSION['success']='Profil toko berhasil disiapkan. Semua member ReVibe bisa menjual dan membeli produk.';
header('Location: seller_center.php'); exit;
?>
