<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';

$token = $_GET['token'] ?? '';
if ($token !== '' && db_column_exists($conn, 'users', 'verification_token')) {
    $hash = hash('sha256', $token);
    $stmt = mysqli_prepare($conn, "UPDATE users SET email_verified_at=NOW(), email_verified=1, verification_token=NULL, account_status=IF(account_status='unverified','active',account_status) WHERE verification_token=? AND (email_verified_at IS NULL OR email_verified=0) LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $hash); mysqli_stmt_execute($stmt);
        $_SESSION[mysqli_stmt_affected_rows($stmt) > 0 ? 'success' : 'error'] = mysqli_stmt_affected_rows($stmt) > 0 ? 'Email berhasil diverifikasi. Silakan login.' : 'Token sudah digunakan atau tidak valid.';
    } else $_SESSION['error']='Token verifikasi tidak valid.';
    header('Location: ../index.php'); exit;
}

$userId = (int)($_SESSION['pending_verification_user_id'] ?? $_SESSION['user_id'] ?? 0);
if ($userId <= 0) { $_SESSION['error']='Sesi verifikasi tidak ditemukan. Silakan login/daftar ulang.'; header('Location: ../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!revibe_rate_limit('verify_email_otp', 5, 600)) { $_SESSION['error']='Terlalu banyak percobaan OTP. Coba lagi nanti.'; header('Location: verify_email.php'); exit; }
    $code = preg_replace('/[^0-9]/', '', $_POST['otp_code'] ?? '');
    if (strlen($code) !== 6) { $_SESSION['error']='Kode OTP harus 6 digit.'; header('Location: verify_email.php'); exit; }
    $ok = (new VerificationService($conn))->verify($userId, 'email', $code, 'register');
    if ($ok) { $_SESSION['success']='Email berhasil diverifikasi.'; header('Location: verification_success.php'); exit; }
    $_SESSION['error']='Kode OTP salah, kedaluwarsa, atau sudah terlalu banyak percobaan.';
    header('Location: verify_email.php'); exit;
}
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Verifikasi Email - ReVibe</title><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="../assets/css/style.css"><link rel="stylesheet" href="../assets/css/loader.css?v=26"></head><body>
<div class="navbar"><a href="../index.php" class="btn">← Beranda</a></div>
<div class="page-shell"><section class="form-card" style="max-width:520px;margin:32px auto"><h1>Verifikasi Email</h1><p>Masukkan kode OTP 6 digit yang dikirim ke email pendaftar. Kode berlaku 10 menit.</p><?php if(isset($_SESSION['error'])): ?><div class="alert error"><?= e($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?><?php if(isset($_SESSION['success'])): ?><div class="alert success"><?= e($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?><form method="POST"><?= csrf_field() ?><label>Kode OTP Email</label><input name="otp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Contoh: 123456" required><button class="btn primary full" type="submit">Verifikasi Email</button></form><form method="POST" action="resend_verification.php" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="channel" value="email"><button class="btn full" type="submit">Kirim Ulang Kode</button></form><p style="margin-top:16px"><a href="verify_phone.php">Verifikasi nomor WhatsApp</a></p></section></div><script defer src="../assets/js/loader.js?v=26"></script></body></html>
