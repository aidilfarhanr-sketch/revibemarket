<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

require_login('../index.php'); $userId=(int)$_SESSION['user_id'];
if(($_SESSION['role']??'')!=='admin') revibe_json_response(false,'Akses admin diperlukan',[],'ADMIN_ONLY',403);
if($_SERVER['REQUEST_METHOD']==='GET'){
 $q=mysqli_query($conn,"SELECT seller_id,user_id,pending_balance,available_balance,withdrawn_balance,total_earned,updated_at FROM seller_balances ORDER BY pending_balance DESC, available_balance DESC LIMIT 100"); $rows=[]; while($q&&$r=mysqli_fetch_assoc($q)) $rows[]=$r; revibe_json_response(true,'Berhasil',['escrow'=>$rows]);
}
revibe_json_response(false,'Method tidak didukung',[],'METHOD_NOT_ALLOWED',405);
