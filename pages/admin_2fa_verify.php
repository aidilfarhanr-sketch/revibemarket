<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['pending_admin_2fa_user_id'])) { header('Location: ../index.php'); exit; }
verify_csrf();
$adminId = (int)$_SESSION['pending_admin_2fa_user_id'];
$code = preg_replace('/[^0-9]/', '', (string)($_POST['otp'] ?? ''));
if (!revibe_rate_limit('admin_2fa_'.$adminId, 5, 600)) { $_SESSION['error'] = 'Percobaan 2FA terlalu sering. Tunggu sebentar.'; header('Location: admin_2fa.php'); exit; }
if ((new VerificationService($conn))->verifyAdmin2fa($adminId, $code)) {
    $stmt = mysqli_prepare($conn, "SELECT id, first_name, last_name, email, role FROM users WHERE id=? AND role='admin' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $adminId); mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$user) { $_SESSION['error'] = 'Admin tidak ditemukan.'; header('Location: ../index.php'); exit; }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Admin ReVibe';
    $_SESSION['role'] = 'admin';
    unset($_SESSION['pending_admin_2fa_user_id'], $_SESSION['pending_admin_2fa_email']);
    mysqli_query($conn, "UPDATE users SET last_login=NOW(), login_attempts=0" . (db_column_exists($conn,'users','locked_until') ? ", locked_until=NULL" : "") . " WHERE id={$adminId}");
    revibe_audit_log($conn, 'admin_2fa_success', 'user', $adminId);
    if (db_table_exists($conn, 'login_audits')) {
        $ip = revibe_client_ip(); $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255); $email = (string)($user['email'] ?? '');
        $stmtAudit = mysqli_prepare($conn, "INSERT INTO login_audits (user_id, email, ip_address, user_agent, status, created_at) VALUES (?, ?, ?, ?, 'admin_2fa_success', NOW())");
        if ($stmtAudit) { mysqli_stmt_bind_param($stmtAudit, 'isss', $adminId, $email, $ip, $ua); mysqli_stmt_execute($stmtAudit); }
    }
    $_SESSION['success'] = 'Login admin berhasil dengan 2FA.';
    header('Location: admin/index.php'); exit;
}
revibe_audit_log($conn, 'admin_2fa_failed', 'user', $adminId);
$_SESSION['error'] = 'Kode 2FA salah atau sudah kedaluwarsa.';
header('Location: admin_2fa.php'); exit;
