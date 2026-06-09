<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['pending_admin_2fa_user_id'])) { header('Location: ../index.php'); exit; }
verify_csrf();
$adminId = (int)$_SESSION['pending_admin_2fa_user_id'];
if (!revibe_rate_limit('admin_2fa_resend_'.$adminId, 3, 600)) { $_SESSION['error'] = 'Kirim ulang kode terlalu sering.'; header('Location: admin_2fa.php'); exit; }
$ok = (new VerificationService($conn))->resend($adminId, 'email', 'admin_2fa');
$_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Kode 2FA baru sudah dikirim.' : 'Kode belum bisa dikirim. Tunggu cooldown atau cek SMTP.';
header('Location: admin_2fa.php'); exit;
