<?php
require_once __DIR__ . '/QueueService.php';
require_once __DIR__ . '/WhatsAppService.php';
class VerificationService {
    private $conn;
    public function __construct($conn) { $this->conn = $conn; }

    public function createAndSend(int $userId, string $channel, string $destination, string $purpose = 'register'): bool {
        if (!function_exists('db_table_exists') || !db_table_exists($this->conn, 'verification_codes')) return false;
        $channel = $channel === 'whatsapp' ? 'whatsapp' : 'email';
        $destination = $channel === 'whatsapp' ? WhatsAppService::normalizeIndonesiaPhone($destination) : trim($destination);
        if ($destination === '') return false;
        $ttl = (int)(function_exists('revibe_env') ? revibe_env('OTP_EXPIRES_MINUTES', 10) : 10);
        $max = (int)(function_exists('revibe_env') ? revibe_env('OTP_MAX_ATTEMPTS', 5) : 5);
        $code = (string)random_int(100000, 999999);
        $hash = password_hash($code, PASSWORD_DEFAULT);
        $destHash = hash('sha256', strtolower($destination));
        $ip = function_exists('revibe_client_ip') ? revibe_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $purposeSafe = mysqli_real_escape_string($this->conn, $purpose);
        $channelSafe = mysqli_real_escape_string($this->conn, $channel);
        mysqli_query($this->conn, "UPDATE verification_codes SET verified_at=NOW() WHERE user_id={$userId} AND channel='{$channelSafe}' AND purpose='{$purposeSafe}' AND verified_at IS NULL");
        $stmt = mysqli_prepare($this->conn, "INSERT INTO verification_codes (user_id, channel, destination_hash, code_hash, purpose, attempts, max_attempts, expires_at, resend_count, last_sent_at, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, 0, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), 0, NOW(), ?, ?, NOW())");
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 'issssiiss', $userId, $channel, $destHash, $hash, $purpose, $max, $ttl, $ip, $ua);
        if (!mysqli_stmt_execute($stmt)) return false;
        $title = $purpose === 'admin_2fa' ? 'Kode 2FA Admin ReVibe Market' : ($channel === 'email' ? 'Verifikasi Akun ReVibe Market' : 'Kode Verifikasi ReVibe Market');
        $body = $this->buildOtpMessage($userId, $code, $channel, $purpose);
        $queued = (new QueueService($this->conn))->pushNotification($userId, $channel, $purpose === 'admin_2fa' ? 'admin_2fa' : 'register_otp', $title, $body, $destination, ['purpose'=>$purpose]);
        if (!$queued && $purpose === 'admin_2fa' && function_exists('revibe_send_mail')) {
            $queued = revibe_send_mail($destination, $title, $body);
        }
        if (function_exists('revibe_is_debug') && revibe_is_debug()) {
            @file_put_contents(dirname(__DIR__, 2) . '/logs/otp-development.log', json_encode(['time'=>date('c'), 'user_id'=>$userId, 'purpose'=>$purpose, 'channel'=>$channel, 'otp'=>$code], JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
        if (function_exists('revibe_audit_log')) revibe_audit_log($this->conn, $purpose . '_sent', 'user', $userId, ['channel'=>$channel]);
        return true;
    }

    public function verify(int $userId, string $channel, string $code, string $purpose = 'register'): bool {
        if (!db_table_exists($this->conn, 'verification_codes')) return false;
        $channel = $channel === 'whatsapp' ? 'whatsapp' : 'email';
        $stmt = mysqli_prepare($this->conn, "SELECT * FROM verification_codes WHERE user_id=? AND channel=? AND purpose=? AND verified_at IS NULL AND expires_at >= NOW() ORDER BY id DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'iss', $userId, $channel, $purpose);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if (!$row) return false;
        $id = (int)$row['id'];
        if ((int)$row['attempts'] >= (int)$row['max_attempts']) return false;
        if (!password_verify($code, (string)$row['code_hash'])) {
            mysqli_query($this->conn, "UPDATE verification_codes SET attempts=attempts+1 WHERE id={$id}");
            if (function_exists('revibe_audit_log')) revibe_audit_log($this->conn, $purpose . '_failed', 'user', $userId, ['channel'=>$channel]);
            return false;
        }
        mysqli_begin_transaction($this->conn);
        try {
            mysqli_query($this->conn, "UPDATE verification_codes SET verified_at=NOW() WHERE id={$id}");
            if ($purpose !== 'admin_2fa') {
                if ($channel === 'email') {
                    if (function_exists('db_column_exists') && db_column_exists($this->conn, 'users', 'email_verified_at')) mysqli_query($this->conn, "UPDATE users SET email_verified_at=NOW(), email_verified=1 WHERE id={$userId}");
                    elseif (function_exists('db_column_exists') && db_column_exists($this->conn, 'users', 'email_verified')) mysqli_query($this->conn, "UPDATE users SET email_verified=1 WHERE id={$userId}");
                } else {
                    if (function_exists('db_column_exists') && db_column_exists($this->conn, 'users', 'phone_verified_at')) mysqli_query($this->conn, "UPDATE users SET phone_verified_at=NOW(), phone_verified=1 WHERE id={$userId}");
                    elseif (function_exists('db_column_exists') && db_column_exists($this->conn, 'users', 'phone_verified')) mysqli_query($this->conn, "UPDATE users SET phone_verified=1 WHERE id={$userId}");
                }
                $this->activateIfReady($userId);
            }
            mysqli_commit($this->conn);
            if (function_exists('revibe_audit_log')) revibe_audit_log($this->conn, $purpose . '_verified', 'user', $userId, ['channel'=>$channel]);
            return true;
        } catch (Throwable $e) { mysqli_rollback($this->conn); if (function_exists('revibe_log')) revibe_log('error','otp verify failed',['user_id'=>$userId,'purpose'=>$purpose,'error'=>$e->getMessage()]); return false; }
    }

    public function resend(int $userId, string $channel, string $purpose = 'register'): bool {
        if (!$this->canResend($userId, $channel, $purpose)) return false;
        $user = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT * FROM users WHERE id=".(int)$userId." LIMIT 1"));
        if (!$user) return false;
        $destination = $channel === 'whatsapp' ? (string)($user['phone'] ?? '') : (string)($user['email'] ?? '');
        return $this->createAndSend($userId, $channel, $destination, $purpose);
    }

    public function createAdmin2fa(int $adminId): bool {
        $stmt = mysqli_prepare($this->conn, "SELECT id,email,role FROM users WHERE id=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $adminId);
        mysqli_stmt_execute($stmt);
        $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if (!$admin || ($admin['role'] ?? '') !== 'admin' || empty($admin['email'])) return false;
        return $this->createAndSend($adminId, 'email', (string)$admin['email'], 'admin_2fa');
    }

    public function verifyAdmin2fa(int $adminId, string $code): bool {
        return $this->verify($adminId, 'email', $code, 'admin_2fa');
    }

    public function canResend(int $userId, string $channel, string $purpose): bool {
        $cooldown = max(10, (int)(function_exists('revibe_env') ? revibe_env('OTP_RESEND_COOLDOWN_SECONDS', 60) : 60));
        $channel = $channel === 'whatsapp' ? 'whatsapp' : 'email';
        $stmt = mysqli_prepare($this->conn, "SELECT last_sent_at FROM verification_codes WHERE user_id=? AND channel=? AND purpose=? ORDER BY id DESC LIMIT 1");
        if (!$stmt) return true;
        mysqli_stmt_bind_param($stmt, 'iss', $userId, $channel, $purpose);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if (!$row || empty($row['last_sent_at'])) return true;
        return (time() - strtotime($row['last_sent_at'])) >= $cooldown;
    }

    public function activateIfReady(int $userId): bool {
        $q = mysqli_query($this->conn, "SELECT * FROM users WHERE id=".(int)$userId." LIMIT 1"); $u = $q ? mysqli_fetch_assoc($q) : null; if (!$u) return false;
        $needEmail = filter_var(function_exists('revibe_env') ? revibe_env('REQUIRE_EMAIL_VERIFICATION', true) : true, FILTER_VALIDATE_BOOLEAN);
        $needPhone = filter_var(function_exists('revibe_env') ? revibe_env('REQUIRE_PHONE_VERIFICATION', false) : false, FILTER_VALIDATE_BOOLEAN);
        $needBoth = filter_var(function_exists('revibe_env') ? revibe_env('REQUIRE_BOTH_EMAIL_AND_PHONE_VERIFICATION', false) : false, FILTER_VALIDATE_BOOLEAN);
        $emailOk = !empty($u['email_verified_at']) || !empty($u['email_verified']);
        $phoneOk = !empty($u['phone_verified_at']) || !empty($u['phone_verified']) || empty($u['phone']);
        $ready = $needBoth ? ($emailOk && $phoneOk) : ((!$needEmail || $emailOk) && (!$needPhone || $phoneOk));
        if ($ready && function_exists('db_column_exists') && db_column_exists($this->conn, 'users', 'account_status')) mysqli_query($this->conn, "UPDATE users SET account_status='active' WHERE id=".(int)$userId);
        return $ready;
    }

    private function buildOtpMessage(int $userId, string $code, string $channel, string $purpose = 'register'): string {
        $u = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT first_name,last_name FROM users WHERE id=".(int)$userId." LIMIT 1"));
        $name = trim(($u['first_name'] ?? 'User') . ' ' . ($u['last_name'] ?? '')) ?: 'User';
        if ($purpose === 'admin_2fa') return "Halo, {$name}\n\nKode 2FA admin ReVibe Market kamu adalah: {$code}\n\nKode berlaku 10 menit dan maksimal 5 percobaan. Jangan bagikan kode ini.";
        if ($channel === 'whatsapp') return "Halo, {$name}\n\nKode verifikasi ReVibe Market kamu adalah: {$code}\n\nKode berlaku 10 menit. Jangan berikan kode ini kepada siapa pun.";
        return "Halo, {$name}\n\nKode verifikasi akun ReVibe Market kamu adalah:\n\n{$code}\n\nKode ini berlaku selama 10 menit.\nJangan berikan kode ini kepada siapa pun.\n\nJika kamu tidak merasa mendaftar di ReVibe Market, abaikan pesan ini.";
    }
}
