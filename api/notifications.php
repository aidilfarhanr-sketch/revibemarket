<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

require_login('../index.php'); $userId=(int)$_SESSION['user_id'];
if($_SERVER['REQUEST_METHOD']!=='GET') revibe_json_response(false,'Method tidak didukung',[],'METHOD_NOT_ALLOWED',405);
$q=mysqli_query($conn,"SELECT id,title,message,type,is_read,created_at FROM notifications WHERE user_id=$userId ORDER BY id DESC LIMIT 50"); $rows=[]; while($q&&$r=mysqli_fetch_assoc($q)) $rows[]=$r; revibe_json_response(true,'Berhasil',['notifications'=>$rows]);
