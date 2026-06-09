<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
verify_csrf();
$userId=(int)($_SESSION['pending_verification_user_id'] ?? $_SESSION['user_id'] ?? 0);
$channel=($_POST['channel'] ?? 'email') === 'whatsapp' ? 'whatsapp' : 'email';
if($userId<=0){ $_SESSION['error']='Sesi verifikasi tidak ditemukan.'; header('Location: ../index.php'); exit; }
if(!revibe_rate_limit('resend_verification_'.$channel, (int)revibe_env('OTP_MAX_RESEND_PER_HOUR',5), 3600)){ $_SESSION['error']='Terlalu sering meminta kode. Coba lagi nanti.'; header('Location: '.($channel==='whatsapp'?'verify_phone.php':'verify_email.php')); exit; }
$ok=(new VerificationService($conn))->resend($userId,$channel,'register');
$_SESSION[$ok?'success':'error']=$ok?'Kode OTP baru berhasil dikirim.':'Gagal mengirim ulang kode. Pastikan data email/WhatsApp valid.';
header('Location: '.($channel==='whatsapp'?'verify_phone.php':'verify_email.php')); exit;
?>
