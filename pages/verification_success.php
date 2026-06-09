<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
$userId=(int)($_SESSION['pending_verification_user_id'] ?? $_SESSION['user_id'] ?? 0);
$ready=$userId>0 ? (new VerificationService($conn))->activateIfReady($userId) : false;
if($ready){ unset($_SESSION['pending_verification_user_id']); }
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Verifikasi Berhasil - ReVibe</title><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="../assets/css/style.css"></head><body><div class="page-shell"><section class="form-card" style="max-width:560px;margin:48px auto;text-align:center"><h1><?= $ready ? 'Akun Aktif ✅' : 'Verifikasi Tersimpan' ?></h1><p><?= $ready ? 'Akun ReVibe kamu sudah aktif dan bisa memakai fitur transaksi.' : 'Satu channel sudah berhasil diverifikasi. Jika aturan meminta email dan WhatsApp, lanjutkan verifikasi channel lainnya.' ?></p><a class="btn primary" href="../index.php">Kembali ke Beranda</a> <a class="btn" href="verify_phone.php">Verifikasi WhatsApp</a></section></div></body></html>
