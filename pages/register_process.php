<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!revibe_rate_limit('register', 5, 3600)) {
        $_SESSION['error'] = 'Terlalu banyak percobaan daftar dari koneksi ini. Coba lagi nanti.';
        header('Location: ../index.php'); exit;
    }

    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $rawPassword = $_POST['password'] ?? '';

    if ($first === '' || $last === '' || $email === '' || $rawPassword === '') { $_SESSION['error']='Semua data wajib diisi.'; header('Location: ../index.php'); exit; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $_SESSION['error']='Format email tidak valid.'; header('Location: ../index.php'); exit; }
    if (strlen($rawPassword) < 8 || !preg_match('/[A-Z]/', $rawPassword) || !preg_match('/[a-z]/', $rawPassword) || !preg_match('/[0-9]/', $rawPassword)) { $_SESSION['error']='Password minimal 8 karakter dan wajib berisi huruf besar, huruf kecil, dan angka.'; header('Location: ../index.php'); exit; }
    if ($phone !== '' && class_exists('WhatsAppService') && !WhatsAppService::normalizeIndonesiaPhone($phone)) { $_SESSION['error']='Format nomor HP/WhatsApp Indonesia tidak valid. Contoh: 08123456789.'; header('Location: ../index.php'); exit; }
    if ($phone !== '' && class_exists('WhatsAppService')) $phone = WhatsAppService::normalizeIndonesiaPhone($phone);

    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $email); mysqli_stmt_execute($stmt);
    if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) { $_SESSION['error']='Email sudah terdaftar!'; header('Location: ../index.php'); exit; }

    $password = password_hash($rawPassword, PASSWORD_DEFAULT);
    $needEmail = filter_var(revibe_env('REQUIRE_EMAIL_VERIFICATION', true), FILTER_VALIDATE_BOOLEAN);
    $needPhone = filter_var(revibe_env('REQUIRE_PHONE_VERIFICATION', false), FILTER_VALIDATE_BOOLEAN);
    $accountStatus = ($needEmail || $needPhone) ? 'unverified' : 'active';

    $cols = ['first_name','last_name','email','password','role'];
    $vals = ['?','?','?','?',"'user'"];
    $types = 'ssss';
    $params = [$first,$last,$email,$password];
    if (db_column_exists($conn,'users','phone')) { $cols[]='phone'; $vals[]='?'; $types.='s'; $params[]=$phone; }
    if (db_column_exists($conn,'users','birthdate')) { $cols[]='birthdate'; $vals[]='?'; $types.='s'; $params[]=$birthdate; }
    if (db_column_exists($conn,'users','status')) { $cols[]='status'; $vals[]="'active'"; }
    if (db_column_exists($conn,'users','account_status')) { $cols[]='account_status'; $vals[]='?'; $types.='s'; $params[]=$accountStatus; }
    if (!$needEmail && db_column_exists($conn,'users','email_verified')) { $cols[]='email_verified'; $vals[]='1'; }
    if (!$needEmail && db_column_exists($conn,'users','email_verified_at')) { $cols[]='email_verified_at'; $vals[]='NOW()'; }

    $sql = "INSERT INTO users (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { $_SESSION['error']='Gagal menyiapkan register. Jalankan migration terbaru dulu.'; header('Location: ../index.php'); exit; }
    if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);

    if (mysqli_stmt_execute($stmt)) {
        $new_user_id = mysqli_insert_id($conn);
        if (db_table_exists($conn, 'coins')) mysqli_query($conn, "INSERT IGNORE INTO coins (user_id, balance) VALUES ($new_user_id, 0)");
        if (db_table_exists($conn, 'user_notification_preferences')) mysqli_query($conn, "INSERT IGNORE INTO user_notification_preferences (user_id) VALUES ($new_user_id)");
        revibe_audit_log($conn, 'register', 'user', $new_user_id, ['email'=>$email, 'need_email'=>$needEmail, 'need_phone'=>$needPhone]);
        $_SESSION['pending_verification_user_id'] = $new_user_id;
        $_SESSION['pending_verification_email'] = $email;
        $_SESSION['pending_verification_phone'] = $phone;
        if (($needEmail || $needPhone) && db_table_exists($conn,'verification_codes')) {
            $verification = new VerificationService($conn);
            if ($needEmail) $verification->createAndSend($new_user_id, 'email', $email, 'register');
            if ($needPhone && $phone !== '') $verification->createAndSend($new_user_id, 'whatsapp', $phone, 'register');
            $_SESSION['success'] = 'Akun berhasil dibuat. Masukkan kode OTP yang dikirim ke email/WhatsApp untuk mengaktifkan akun.';
            header('Location: verify_email.php'); exit;
        }
        $_SESSION['success'] = 'Akun berhasil dibuat, silakan login!';
    } else {
        $_SESSION['error'] = 'Gagal membuat akun. Coba lagi.';
    }
    header('Location: ../index.php'); exit;
}
header('Location: ../index.php'); exit;
?>
