<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!revibe_rate_limit('login', 10, 900)) {
        $_SESSION['error'] = 'Terlalu banyak percobaan login. Tunggu sekitar 15 menit.';
        header('Location: ../index.php'); exit;
    }
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email === '' || $password === '') { $_SESSION['error']='Email dan password wajib diisi.'; header('Location: ../index.php'); exit; }

    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if ($user && isset($user['status']) && $user['status'] === 'blocked') { $_SESSION['error']='Akun kamu sedang diblokir admin.'; header('Location: ../index.php'); exit; }
    if ($user && db_column_exists($conn,'users','locked_until') && !empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        $_SESSION['error']='Terlalu banyak percobaan login. Coba lagi setelah '.date('H:i', strtotime($user['locked_until'])).'.'; header('Location: ../index.php'); exit;
    }

    if ($user && password_verify($password, $user['password'])) {
        if (!revibe_user_is_verified($conn, (int)$user['id']) && (($user['role'] ?? 'user') !== 'admin')) {
            $_SESSION['pending_verification_user_id'] = (int)$user['id'];
            $_SESSION['pending_verification_email'] = $user['email'] ?? '';
            $_SESSION['pending_verification_phone'] = $user['phone'] ?? '';
            $_SESSION['error'] = 'Akun belum aktif. Masukkan kode OTP email/WhatsApp dulu.';
            header('Location: verify_email.php'); exit;
        }
        if (($user['role'] ?? 'user') === 'admin' && filter_var(revibe_env('ADMIN_2FA_REQUIRED', false), FILTER_VALIDATE_BOOLEAN)) {
            $_SESSION['pending_admin_2fa_user_id'] = (int)$user['id'];
            $_SESSION['pending_admin_2fa_email'] = (string)($user['email'] ?? '');
            unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['role']);
            $sent = (new VerificationService($conn))->createAdmin2fa((int)$user['id']);
            if (db_table_exists($conn, 'login_audits')) {
                $ip = revibe_client_ip(); $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255); $uid2=(int)$user['id'];
                $stmtAudit = mysqli_prepare($conn, "INSERT INTO login_audits (user_id, email, ip_address, user_agent, status, created_at) VALUES (?, ?, ?, ?, 'admin_2fa_pending', NOW())");
                if ($stmtAudit) { mysqli_stmt_bind_param($stmtAudit, 'isss', $uid2, $email, $ip, $ua); mysqli_stmt_execute($stmtAudit); }
            }
            $_SESSION[$sent ? 'success' : 'error'] = $sent ? 'Password benar. Masukkan kode 2FA admin yang dikirim ke email.' : 'Password benar, tetapi kode 2FA belum dapat dikirim. Cek SMTP/log development.';
            header('Location: admin_2fa.php'); exit;
        }
        session_regenerate_id(true);
        $_SESSION['user_id']=(int)$user['id'];
        $_SESSION['user_name']=trim(($user['first_name']??'').' '.($user['last_name']??''));
        $_SESSION['role']=(($user['role']??'user')==='admin')?'admin':'user';
        $uid=(int)$user['id'];
        if (db_table_exists($conn, 'user_sessions')) {
            $sessionHash = hash('sha256', session_id());
            $ip = revibe_client_ip(); $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
            $stmtSess = mysqli_prepare($conn, "INSERT INTO user_sessions (user_id, session_token_hash, ip_address, user_agent, last_seen_at, created_at) VALUES (?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE last_seen_at=NOW(), revoked_at=NULL");
            if ($stmtSess) { mysqli_stmt_bind_param($stmtSess, 'isss', $uid, $sessionHash, $ip, $ua); mysqli_stmt_execute($stmtSess); }
        }
        $set="last_login=NOW(), login_attempts=0";
        if(db_column_exists($conn,'users','locked_until')) $set.=', locked_until=NULL';
        mysqli_query($conn,"UPDATE users SET $set WHERE id=$uid");
        if (db_table_exists($conn, 'login_audits')) {
            $ip = revibe_client_ip(); $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
            $stmtAudit = mysqli_prepare($conn, "INSERT INTO login_audits (user_id, email, ip_address, user_agent, status, created_at) VALUES (?, ?, ?, ?, 'success', NOW())");
            if ($stmtAudit) { mysqli_stmt_bind_param($stmtAudit, 'isss', $uid, $email, $ip, $ua); mysqli_stmt_execute($stmtAudit); }
        }
        $_SESSION['success']='Login berhasil. Selamat datang kembali di ReVibe Market!';
        header('Location: ../index.php'); exit;
    }

    if ($user && db_column_exists($conn, 'users', 'login_attempts')) {
        $uid=(int)$user['id'];
        $attempts=(int)($user['login_attempts']??0)+1;
        $lockSql='';
        if($attempts>=5 && db_column_exists($conn,'users','locked_until')) $lockSql=", locked_until=DATE_ADD(NOW(), INTERVAL 15 MINUTE)";
        mysqli_query($conn,"UPDATE users SET login_attempts=$attempts $lockSql WHERE id=$uid");
        if($attempts>=5){ $_SESSION['error']='Terlalu banyak percobaan salah. Akun dikunci sementara 15 menit.'; header('Location: ../index.php'); exit; }
    }
    if (db_table_exists($conn, 'login_audits')) {
        $ip = revibe_client_ip(); $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $uidAudit = (int)($user['id'] ?? 0);
        $stmtAudit = mysqli_prepare($conn, "INSERT INTO login_audits (user_id, email, ip_address, user_agent, status, created_at) VALUES (?, ?, ?, ?, 'failed', NOW())");
        if ($stmtAudit) { mysqli_stmt_bind_param($stmtAudit, 'isss', $uidAudit, $email, $ip, $ua); mysqli_stmt_execute($stmtAudit); }
    }
    $_SESSION['error']='Email atau password salah!';
    header('Location: ../index.php'); exit;
}
header('Location: ../index.php'); exit;
?>
