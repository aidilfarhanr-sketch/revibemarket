<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

require_login('../index.php');
$userId=(int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] !== 'GET') revibe_json_response(false,'Method tidak didukung',[],'METHOD_NOT_ALLOWED',405);
$orderId=(int)($_GET['order_id'] ?? 0); if($orderId<=0) revibe_json_response(false,'Order tidak valid',[],'ORDER_INVALID',400);
if(!canViewOrder($conn,$orderId,$userId)) revibe_json_response(false,'Akses order ditolak',[],'ORDER_FORBIDDEN',403);
$q=mysqli_query($conn,"SELECT id,order_id,method,amount,status,gateway,gateway_reference,payment_url,paid_at,verified_at,expired_at,created_at FROM payments WHERE order_id=$orderId LIMIT 1");
$payment=$q?mysqli_fetch_assoc($q):null;
revibe_json_response(true,'Berhasil',['payment'=>$payment]);
