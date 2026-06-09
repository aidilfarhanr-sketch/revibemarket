<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!revibe_rate_limit('forgot_password', 5, 3600)) {
        $_SESSION['success'] = 'Jika email terdaftar, link reset password akan dikirim. Token tidak ditampilkan demi keamanan publik.';
    } else {
    $email = trim($_POST['email'] ?? '');
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($user = mysqli_fetch_assoc($result)) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            $hash = hash('sha256', $token);
            $stmtUp = mysqli_prepare($conn, "UPDATE users SET reset_token=?, reset_expires=? WHERE id=?");
            mysqli_stmt_bind_param($stmtUp, 'ssi', $hash, $expires, $user['id']);
            mysqli_stmt_execute($stmtUp);
            $link = revibe_app_url('pages/reset_password.php?token=' . urlencode($token));
            revibe_send_mail($email, 'Reset Password ReVibe Market', "Klik link ini untuk reset password ReVibe Market. Link berlaku 1 jam:\n\n" . $link);
        }
    }
    $_SESSION['success'] = 'Jika email terdaftar, link reset password akan dikirim. Token tidak ditampilkan demi keamanan publik.';
    }
}
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Lupa Password - ReVibe</title><link rel="stylesheet" href="../assets/css/style.css"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="stylesheet" href="../assets/css/loader.css?v=25">
</head><body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>

<div class="navbar"><a href="../index.php" class="btn">← Beranda</a></div>
<div class="page-shell narrow"><div class="page-header"><h1>Lupa Kata Sandi</h1><p>Masukkan email. Jika terdaftar, link reset akan dikirim ke email tersebut.</p></div>
<?php if(isset($_SESSION['success'])): ?><div class="alert success"><?= e($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
<form method="POST" class="form-card"><?= csrf_field() ?><label>Email</label><input type="email" name="email" required><button class="btn primary full" type="submit">Kirim Link Reset</button></form>
</div><?php render_revibe_floating_nav($conn); ?><script defer src="../assets/js/loader.js?v=25"></script>
</body></html>
