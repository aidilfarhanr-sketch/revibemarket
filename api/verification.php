<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

if($_SERVER['REQUEST_METHOD']!=='POST') revibe_json_response(false,'Method tidak didukung',[],'METHOD_NOT_ALLOWED',405);
verify_csrf(); $userId=(int)($_SESSION['pending_verification_user_id'] ?? $_SESSION['user_id'] ?? 0); if($userId<=0) revibe_json_response(false,'Sesi verifikasi tidak ditemukan',[],'VERIFY_SESSION_MISSING',400);
$action=$_POST['action'] ?? 'verify'; $channel=($_POST['channel'] ?? 'email')==='whatsapp'?'whatsapp':'email';
$svc=new VerificationService($conn);
if($action==='resend'){ if(!revibe_rate_limit('api_resend_'.$channel, (int)revibe_env('OTP_MAX_RESEND_PER_HOUR',5),3600)) revibe_json_response(false,'Terlalu sering meminta kode',[],'RATE_LIMITED',429); $ok=$svc->resend($userId,$channel,'register'); revibe_json_response($ok,$ok?'Kode dikirim':'Gagal mengirim kode',['channel'=>$channel],$ok?null:'VERIFY_RESEND_FAILED',$ok?200:400); }
$code=preg_replace('/[^0-9]/','',$_POST['otp_code'] ?? ''); if(strlen($code)!==6) revibe_json_response(false,'OTP harus 6 digit',[],'OTP_INVALID',400); $ok=$svc->verify($userId,$channel,$code,'register'); revibe_json_response($ok,$ok?'Verifikasi berhasil':'OTP salah atau kedaluwarsa',['channel'=>$channel],$ok?null:'OTP_FAILED',$ok?200:400);
