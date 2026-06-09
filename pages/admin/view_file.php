<?php
require_once __DIR__ . '/../../config/session.php';
include '../../config/db.php';
require_once '../../config/functions.php';
require_once __DIR__ . '/../../app/Policies/FilePolicy.php';

require_login('../../index.php');

$folder = preg_replace('/[^a-zA-Z0-9_\-]/','', $_GET['folder'] ?? 'payment_proofs');
$file = basename($_GET['file'] ?? '');
$allowedFolders = ['payment_proofs','complaints','refunds','kyc'];
if(!in_array($folder, $allowedFolders, true) || $file==='') { http_response_code(404); exit('File tidak ditemukan.'); }

$userId = (int)($_SESSION['user_id'] ?? 0);
$policy = new FilePolicy($conn);
if (!$policy->viewPrivate($folder, $file, $userId)) {
    revibe_audit_log($conn, 'private_file_access_denied', 'file', null, ['folder'=>$folder,'file'=>$file], $userId);
    http_response_code(403);
    exit('Akses file ditolak.');
}

$storage = new StorageService($conn);
$body = false;
$path = $storage->privatePath($folder, $file);
if ($path && is_file($path)) {
    $body = file_get_contents($path);
} else {
    $body = $storage->get($folder . '/' . $file);
}
if($body === false || $body === null) { http_response_code(404); exit('File tidak ditemukan.'); }

revibe_audit_log($conn, 'private_file_accessed', 'file', null, ['folder'=>$folder,'file'=>$file], $userId);
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$types = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','pdf'=>'application/pdf'];
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Content-Disposition: inline; filename="' . addslashes($file) . '"');
echo $body;
exit;
