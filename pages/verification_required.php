<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
$_SESSION['pending_verification_user_id'] = (int)$_SESSION['user_id'];
$user=current_user($conn);
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Verifikasi Diperlukan - ReVibe</title><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="../assets/css/style.css"></head><body><div class="navbar"><a href="../index.php" class="btn">Beranda</a><a href="logout.php" class="btn danger">Logout</a></div><div class="page-shell"><section class="form-card" style="max-width:620px;margin:32px auto"><h1>Verifikasi Akun Diperlukan</h1><p>Akun kamu sudah terdaftar, tetapi fitur checkout, seller center, upload produk, chat transaksi, dan withdrawal hanya bisa digunakan setelah email/WhatsApp terverifikasi.</p><?php if(isset($_SESSION['error'])): ?><div class="alert error"><?= e($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?><div class="action-row"><a class="btn primary" href="verify_email.php">Verifikasi Email</a><a class="btn" href="verify_phone.php">Verifikasi WhatsApp</a></div><p>Email: <b><?= e($user['email'] ?? '-') ?></b><br>WhatsApp: <b><?= e($user['phone'] ?? '-') ?></b></p></section></div></body></html>
