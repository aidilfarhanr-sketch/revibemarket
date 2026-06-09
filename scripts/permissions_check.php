<?php
$paths=['storage','storage/private','storage/private/payment_proofs','storage/private/complaints','storage/cache','logs','uploads','uploads/products','uploads/profile','backups'];
$result=[];
foreach($paths as $p){
    $full=__DIR__.'/../'.$p;
    if(!is_dir($full)) @mkdir($full,0755,true);
    $result[$p]=is_writable($full);
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>!in_array(false,$result,true),'paths'=>$result], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
exit(!in_array(false,$result,true)?0:1);
