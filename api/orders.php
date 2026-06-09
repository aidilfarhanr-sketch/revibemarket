<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

require_login('../index.php');
$userId=(int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD']==='GET') {
  $role=$_GET['role'] ?? 'buyer';
  $where=$role==='seller' ? "seller_id=$userId" : "buyer_id=$userId";
  if(($_SESSION['role']??'')==='admin' && isset($_GET['all'])) $where='1=1';
  $q=mysqli_query($conn,"SELECT id,order_code,buyer_id,seller_id,total_price,status,payment_method,shipping_cost,created_at,updated_at FROM orders WHERE $where ORDER BY id DESC LIMIT 50");
  $rows=[]; while($q && $r=mysqli_fetch_assoc($q)) $rows[]=$r;
  revibe_json_response(true,'Berhasil',['orders'=>$rows]);
}
revibe_json_response(false,'Gunakan halaman checkout untuk membuat order agar validasi stok dan CSRF tetap lengkap.',[],'ORDER_CREATE_UI_ONLY',405);
