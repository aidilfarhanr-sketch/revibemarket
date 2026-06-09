<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$tokenHash = $token !== '' ? hash('sha256', $token) : '';
$user = null;
if ($tokenHash !== '' && db_column_exists($conn, 'users', 'reset_token')) {
    $stmt=mysqli_prepare($conn,"SELECT id FROM users WHERE reset_token=? AND reset_expires > NOW() LIMIT 1");
    mysqli_stmt_bind_param($stmt,'s',$tokenHash);
    mysqli_stmt_execute($stmt);
    $user=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pass = $_POST['password'] ?? '';
    if (!$user) { $_SESSION['error'] = 'Token reset tidak valid atau kadaluarsa.'; }
    elseif (strlen($pass) < 8) { $_SESSION['error'] = 'Password minimal 8 karakter.'; }
    else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL, login_attempts=0 WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'si', $hash, $user['id']);
        mysqli_stmt_execute($stmt);
        $_SESSION['success'] = 'Password berhasil diubah. Silakan login.';
        header('Location: ../index.php'); exit;
    }
}
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Reset Password - ReVibe</title><link rel="stylesheet" href="../assets/css/style.css"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="stylesheet" href="../assets/css/loader.css?v=25">
</head><body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>
<div class="navbar"><a href="../index.php" class="btn">← Beranda</a></div><div class="page-shell narrow"><div class="page-header"><h1>Reset Password</h1><p>Masukkan password baru minimal 8 karakter.</p></div><?php if(isset($_SESSION['error'])): ?><div class="alert error"><?= e($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?><form method="POST" class="form-card"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>"><label>Password Baru</label><input type="password" name="password" minlength="8" required><button class="btn primary full" type="submit">Simpan Password</button></form></div><?php render_revibe_floating_nav($conn); ?><script defer src="../assets/js/loader.js?v=25"></script>
</body></html>
