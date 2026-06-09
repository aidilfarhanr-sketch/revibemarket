<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

require_login('../index.php'); revibe_require_verified_account($conn, '../pages/verification_required.php');
$userId=(int)$_SESSION['user_id'];
if($_SERVER['REQUEST_METHOD']==='GET'){
 $peer=(int)($_GET['peer_id']??0); $product=(int)($_GET['product_id']??0); if(!canAccessChat($conn,$peer,$product,$userId)) revibe_json_response(false,'Akses chat ditolak',[],'CHAT_FORBIDDEN',403);
 $q=mysqli_query($conn,"SELECT * FROM chat_messages WHERE ((sender_id=$userId AND receiver_id=$peer) OR (sender_id=$peer AND receiver_id=$userId)) ORDER BY id DESC LIMIT 50"); $rows=[]; while($q&&$r=mysqli_fetch_assoc($q)) $rows[]=$r; revibe_json_response(true,'Berhasil',['messages'=>array_reverse($rows)]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){ verify_csrf(); if(!revibe_rate_limit('chat_send',30,60)) revibe_json_response(false,'Terlalu banyak mengirim chat',[],'RATE_LIMITED',429); $peer=(int)($_POST['receiver_id']??0); $product=(int)($_POST['product_id']??0); $msg=trim($_POST['message']??''); if($msg==='') revibe_json_response(false,'Pesan kosong',[],'CHAT_EMPTY',400); if(!canAccessChat($conn,$peer,$product,$userId)) revibe_json_response(false,'Akses chat ditolak',[],'CHAT_FORBIDDEN',403); $stmt=mysqli_prepare($conn,"INSERT INTO chat_messages (sender_id,receiver_id,product_id,message,created_at) VALUES (?,?,?,?,NOW())"); mysqli_stmt_bind_param($stmt,'iiis',$userId,$peer,$product,$msg); mysqli_stmt_execute($stmt); revibe_json_response(true,'Pesan terkirim',['id'=>mysqli_insert_id($conn)]); }
revibe_json_response(false,'Method tidak didukung',[],'METHOD_NOT_ALLOWED',405);
