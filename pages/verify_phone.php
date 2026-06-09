<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
$userId = (int)($_SESSION['pending_verification_user_id'] ?? $_SESSION['user_id'] ?? 0);
if ($userId <= 0) { $_SESSION['error']='Sesi verifikasi tidak ditemukan.'; header('Location: ../index.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!revibe_rate_limit('verify_whatsapp_otp', 5, 600)) { $_SESSION['error']='Terlalu banyak percobaan OTP. Coba lagi nanti.'; header('Location: verify_phone.php'); exit; }
    $code = preg_replace('/[^0-9]/','',$_POST['otp_code'] ?? '');
    if (strlen($code)!==6) { $_SESSION['error']='Kode OTP harus 6 digit.'; header('Location: verify_phone.php'); exit; }
    $ok=(new VerificationService($conn))->verify($userId,'whatsapp',$code,'register');
    if($ok){ $_SESSION['success']='Nomor WhatsApp berhasil diverifikasi.'; header('Location: verification_success.php'); exit; }
    $_SESSION['error']='Kode OTP WhatsApp salah atau kedaluwarsa.'; header('Location: verify_phone.php'); exit;
}
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Verifikasi WhatsApp - ReVibe</title><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="../assets/css/style.css"></head><body><div class="navbar"><a href="../index.php" class="btn">← Beranda</a></div><div class="page-shell"><section class="form-card" style="max-width:520px;margin:32px auto"><h1>Verifikasi WhatsApp</h1><p>Masukkan kode OTP yang dikirim ke WhatsApp kamu.</p><?php if(isset($_SESSION['error'])): ?><div class="alert error"><?= e($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?><form method="POST"><?= csrf_field() ?><label>Kode OTP WhatsApp</label><input name="otp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required><button class="btn primary full">Verifikasi WhatsApp</button></form><form method="POST" action="resend_verification.php" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="channel" value="whatsapp"><button class="btn full">Kirim Ulang Kode</button></form><p style="margin-top:16px"><a href="verify_email.php">Verifikasi email</a></p></section></div></body></html>
