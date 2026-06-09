<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
if (empty($_SESSION['pending_admin_2fa_user_id'])) { header('Location: ../index.php'); exit; }
$email = (string)($_SESSION['pending_admin_2fa_email'] ?? 'email admin');
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Admin 2FA - ReVibe</title><link rel="stylesheet" href="../assets/css/style.css"><link rel="stylesheet" href="../assets/css/loader.css?v=28"></head><body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market"><div class="rv-loader-card"><div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div><p>Loading ReVibe Market...</p><small>Memuat keamanan admin...</small></div></div>
<div class="navbar"><a href="../index.php" class="btn">← Beranda</a></div>
<main class="page-shell auth-shell">
  <section class="panel-card auth-card">
    <h1>Verifikasi 2FA Admin</h1>
    <p class="muted">Masukkan kode 6 digit yang dikirim ke <?= e($email) ?>. Admin belum dianggap login penuh sebelum kode benar.</p>
    <?php if(isset($_SESSION['error'])): ?><div class="rv-toast error"><?= e($_SESSION['error']); unset($_SESSION['error']); ?><button onclick="this.parentElement.remove()">✕</button></div><?php endif; ?>
    <?php if(isset($_SESSION['success'])): ?><div class="rv-toast success"><?= e($_SESSION['success']); unset($_SESSION['success']); ?><button onclick="this.parentElement.remove()">✕</button></div><?php endif; ?>
    <form method="POST" action="admin_2fa_verify.php" class="revibe-form">
      <?= csrf_field() ?>
      <label>Kode 2FA</label>
      <input type="text" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="Contoh: 123456" required autofocus>
      <button class="btn primary full" type="submit">Verifikasi Admin</button>
    </form>
    <form method="POST" action="admin_2fa_resend.php" class="inline-form" data-no-loader>
      <?= csrf_field() ?>
      <button class="btn secondary" type="submit">Kirim ulang kode</button>
    </form>
  </section>
</main>
<script defer src="../assets/js/loader.js?v=28"></script></body></html>
