<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') revibe_json_response(false, 'Method tidak didukung', [], 'METHOD_NOT_ALLOWED', 405);
$q = trim($_GET['q'] ?? ''); $category = trim($_GET['category'] ?? ''); $limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
$where = "WHERE product_status='approved'"; $types=''; $params=[];
if($q!==''){ $where .= " AND (name LIKE ? OR description LIKE ?)"; $like='%'.$q.'%'; $types.='ss'; $params[]=$like; $params[]=$like; }
if($category!==''){ $where .= " AND category=?"; $types.='s'; $params[]=$category; }
$sql="SELECT id,name,category,condition_status,price,stock,location,rating,review_count,sold,created_at FROM products $where ORDER BY id DESC LIMIT $limit";
$stmt=mysqli_prepare($conn,$sql); if($types) mysqli_stmt_bind_param($stmt,$types,...$params); mysqli_stmt_execute($stmt); $res=mysqli_stmt_get_result($stmt); $rows=[]; while($res && $r=mysqli_fetch_assoc($res)) $rows[]=$r;
revibe_json_response(true,'Berhasil',['products'=>$rows]);
