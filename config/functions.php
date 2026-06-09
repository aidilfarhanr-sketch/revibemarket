<?php
require_once __DIR__ . '/env.php';
require_once dirname(__DIR__) . '/app/Services/LoggerService.php';
require_once dirname(__DIR__) . '/app/Services/RateLimitService.php';
require_once dirname(__DIR__) . '/app/Services/MailerService.php';
require_once dirname(__DIR__) . '/app/Services/AuditService.php';
require_once dirname(__DIR__) . '/app/Services/QueueService.php';
require_once dirname(__DIR__) . '/app/Services/VerificationService.php';
require_once dirname(__DIR__) . '/app/Services/WhatsAppService.php';
require_once dirname(__DIR__) . '/app/Services/PaymentGatewayService.php';
require_once dirname(__DIR__) . '/app/Services/EscrowService.php';
require_once dirname(__DIR__) . '/app/Services/StorageService.php';
require_once dirname(__DIR__) . '/app/Services/CacheService.php';
require_once dirname(__DIR__) . '/app/Services/ErrorTrackingService.php';
require_once dirname(__DIR__) . '/app/Support/ServiceResult.php';
require_once dirname(__DIR__) . '/app/Support/JsonResponse.php';
require_once __DIR__ . '/error_handler.php';

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/session.php';
}

function revibe_request_id() {
    static $rid = null;
    if ($rid === null) {
        $rid = $_SERVER['HTTP_X_REQUEST_ID'] ?? ($_SERVER['UNIQUE_ID'] ?? bin2hex(random_bytes(8)));
        $_SERVER['REVIBE_REQUEST_ID'] = $rid;
    }
    return $rid;
}

function revibe_json_response($success, $message, array $data = [], $error_code = null, $http = 200) {
    http_response_code((int)$http);
    header('Content-Type: application/json; charset=utf-8');
    $payload = ['success'=>(bool)$success, 'message'=>(string)$message];
    if ($success) $payload['data'] = $data;
    else $payload['error_code'] = $error_code ?: 'REVIBE_ERROR';
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function revibe_audit_log($conn, $action, $entity_type = null, $entity_id = null, array $context = [], $user_id = null) {
    try { return (new AuditService($conn))->log((string)$action, $entity_type, $entity_id, $context, $user_id); }
    catch (Throwable $e) { revibe_log('warning', 'audit log failed', ['action'=>$action, 'error'=>$e->getMessage()]); return false; }
}

function revibe_queue_notification($conn, $user_id, $channel, $type, $title, $message, $destination = '', array $payload = []) {
    try { return (new QueueService($conn))->pushNotification($user_id ? (int)$user_id : null, (string)$channel, (string)$type, (string)$title, (string)$message, (string)$destination, $payload); }
    catch (Throwable $e) { revibe_log('warning', 'queue notification failed', ['type'=>$type, 'error'=>$e->getMessage()]); return false; }
}

function revibe_notify_user_event($conn, $user_id, $type, $title, $message, array $payload = []) {
    $user_id=(int)$user_id;
    if ($user_id<=0) return false;
    add_notification($conn, $user_id, $title, $message, $type);
    $u = mysqli_fetch_assoc(mysqli_query($conn, "SELECT email, phone, email_verified_at, phone_verified_at, email_verified, phone_verified, notify_email_enabled, notify_whatsapp_enabled FROM users WHERE id=$user_id LIMIT 1"));
    if (!$u) return false;
    $emailOk = !empty($u['email_verified_at']) || !empty($u['email_verified']);
    $phoneOk = !empty($u['phone_verified_at']) || !empty($u['phone_verified']);
    if (!empty($u['notify_email_enabled']) && $emailOk && !empty($u['email'])) revibe_queue_notification($conn, $user_id, 'email', $type, $title, $message, $u['email'], $payload);
    if (!empty($u['notify_whatsapp_enabled']) && $phoneOk && !empty($u['phone'])) revibe_queue_notification($conn, $user_id, 'whatsapp', $type, $title, $message, $u['phone'], $payload);
    return true;
}

function revibe_user_is_verified($conn, $user_id) {
    $user_id=(int)$user_id;
    if ($user_id<=0) return false;
    $q=mysqli_query($conn,"SELECT * FROM users WHERE id=$user_id LIMIT 1");
    $u=$q?mysqli_fetch_assoc($q):null;
    if(!$u) return false;
    if (isset($u['account_status']) && in_array($u['account_status'], ['suspended','banned'], true)) return false;
    $needEmail = filter_var(revibe_env('REQUIRE_EMAIL_VERIFICATION', true), FILTER_VALIDATE_BOOLEAN);
    $needPhone = filter_var(revibe_env('REQUIRE_PHONE_VERIFICATION', false), FILTER_VALIDATE_BOOLEAN);
    $needBoth = filter_var(revibe_env('REQUIRE_BOTH_EMAIL_AND_PHONE_VERIFICATION', false), FILTER_VALIDATE_BOOLEAN);
    $emailOk = !empty($u['email_verified_at']) || !empty($u['email_verified']);
    $phoneOk = !empty($u['phone_verified_at']) || !empty($u['phone_verified']) || empty($u['phone']);
    if ($needBoth) return $emailOk && $phoneOk;
    return (!$needEmail || $emailOk) && (!$needPhone || $phoneOk);
}

function revibe_require_verified_account($conn, $redirect = 'verification_required.php') {
    if (!is_logged_in()) return;
    if (($_SESSION['role'] ?? '') === 'admin') return;
    if (!revibe_user_is_verified($conn, (int)$_SESSION['user_id'])) {
        $_SESSION['pending_verification_user_id'] = (int)$_SESSION['user_id'];
        $_SESSION['error'] = 'Akun perlu verifikasi email/WhatsApp dulu sebelum memakai fitur transaksi.';
        header('Location: ' . $redirect);
        exit;
    }
}

function revibe_order_status_history($conn, $order_id, $old, $new, $changed_by = null, $note = '') {
    if (!db_table_exists($conn, 'order_status_history')) return false;
    $order_id=(int)$order_id; $changed_by=$changed_by!==null?(int)$changed_by:(int)($_SESSION['user_id']??0);
    $stmt=mysqli_prepare($conn,"INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, note, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    if(!$stmt) return false;
    mysqli_stmt_bind_param($stmt,'issis',$order_id,$old,$new,$changed_by,$note);
    return mysqli_stmt_execute($stmt);
}

function revibe_payment_status_history($conn, $payment_id, $order_id, $old, $new, $source = 'manual', $note = '') {
    if (!db_table_exists($conn, 'payment_status_history')) return false;
    $payment_id=$payment_id!==null?(int)$payment_id:null; $order_id=(int)$order_id;
    $stmt=mysqli_prepare($conn,"INSERT INTO payment_status_history (payment_id, order_id, old_status, new_status, source, note, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    if(!$stmt) return false;
    mysqli_stmt_bind_param($stmt,'iissss',$payment_id,$order_id,$old,$new,$source,$note);
    return mysqli_stmt_execute($stmt);
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($amount) {
    return 'Rp ' . number_format((float)$amount, 0, ',', '.');
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            die('Akses ditolak. Token keamanan tidak valid. Silakan kembali dan coba lagi.');
        }
    }
}

function revibe_client_ip() {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $trusted = array_filter(array_map('trim', explode(',', (string)revibe_env('TRUSTED_PROXIES', ''))));
    $isTrusted = false;
    foreach ($trusted as $proxy) {
        if ($proxy === '' || $proxy === '*') { $isTrusted = true; break; }
        if ($remote === $proxy) { $isTrusted = true; break; }

        if (str_contains($proxy, '/')) {
            [$subnet, $bits] = explode('/', $proxy, 2);
            $remoteLong = ip2long($remote); $subnetLong = ip2long($subnet); $bits = (int)$bits;
            if ($remoteLong !== false && $subnetLong !== false && $bits >= 0 && $bits <= 32) {
                $mask = -1 << (32 - $bits);
                if (($remoteLong & $mask) === ($subnetLong & $mask)) { $isTrusted = true; break; }
            }
        }
    }
    if ($isTrusted && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        foreach ($parts as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP)) return $candidate;
        }
    }
    if ($isTrusted && !empty($_SERVER['HTTP_X_REAL_IP']) && filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP)) return $_SERVER['HTTP_X_REAL_IP'];
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
}

function revibe_rate_limit($bucket, $maxRequests, $windowSeconds) {
    global $conn;
    $identity = revibe_client_ip() . '|' . ($_SESSION['user_id'] ?? 'guest');
    $service = new RateLimitService($conn ?? null);
    return $service->hit((string)$bucket, $identity, max(1, (int)$maxRequests), max(1, (int)$windowSeconds));
}

function revibe_log($severity, $message, array $context = []) {
    try { (new LoggerService())->log((string)$severity, (string)$message, $context); }
    catch (Throwable $e) { error_log('revibe_log failed: ' . $e->getMessage()); }
}

function revibe_send_mail($to, $subject, $body) {
    return (new MailerService())->send((string)$to, (string)$subject, (string)$body);
}

function revibe_has_permission($permission) {
    $matrix = require __DIR__ . '/security.php';
    $role = current_role();
    $allowed = $matrix['permissions'][$role] ?? [];
    return in_array('*', $allowed, true) || in_array($permission, $allowed, true);
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function current_role() {
    $role = $_SESSION['role'] ?? 'guest';

    return $role === 'admin' ? 'admin' : ($role === 'guest' ? 'guest' : 'user');
}

function require_login($redirect = '../index.php') {
    if (!is_logged_in()) {
        $_SESSION['error'] = 'Silakan login terlebih dahulu';
        header('Location: ' . $redirect);
        exit;
    }
}

function require_role($roles, $redirect = '../index.php') {
    require_login($redirect);
    $roles = (array)$roles;
    if (!in_array(current_role(), $roles, true)) {
        $_SESSION['error'] = 'Akses ditolak. Halaman ini khusus admin.';
        header('Location: ' . $redirect);
        exit;
    }
}

function db_column_exists($conn, $table, $column) {
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) return $cache[$key];
    $table_safe = mysqli_real_escape_string($conn, $table);
    $column_safe = mysqli_real_escape_string($conn, $column);
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `$table_safe` LIKE '$column_safe'");
    $cache[$key] = $q && mysqli_num_rows($q) > 0;
    return $cache[$key];
}

function db_table_exists($conn, $table) {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $table_safe = mysqli_real_escape_string($conn, $table);
    $q = mysqli_query($conn, "SHOW TABLES LIKE '$table_safe'");
    $cache[$table] = $q && mysqli_num_rows($q) > 0;
    return $cache[$table];
}

function current_user($conn) {
    if (!is_logged_in()) return null;
    $uid = (int)$_SESSION['user_id'];
    $q = mysqli_query($conn, "SELECT * FROM users WHERE id=$uid LIMIT 1");
    return $q ? mysqli_fetch_assoc($q) : null;
}

function canViewOrder($conn, $order_id, $user_id = null) {
    $user_id = $user_id !== null ? (int)$user_id : (int)($_SESSION['user_id'] ?? 0);
    if ($user_id <= 0) return false;
    if (($_SESSION['role'] ?? '') === 'admin') return true;
    $order_id = (int)$order_id;
    $q = mysqli_query($conn, "SELECT buyer_id, seller_id FROM orders WHERE id=$order_id LIMIT 1");
    $o = $q ? mysqli_fetch_assoc($q) : null;
    return $o && ((int)($o['buyer_id'] ?? 0) === $user_id || (int)($o['seller_id'] ?? 0) === $user_id);
}

function canUpdateProduct($conn, $product_id, $user_id = null) {
    $user_id = $user_id !== null ? (int)$user_id : (int)($_SESSION['user_id'] ?? 0);
    if ($user_id <= 0) return false;
    if (($_SESSION['role'] ?? '') === 'admin') return true;
    $product_id = (int)$product_id;
    $q = mysqli_query($conn, "SELECT user_id FROM products WHERE id=$product_id LIMIT 1");
    $p = $q ? mysqli_fetch_assoc($q) : null;
    return $p && (int)($p['user_id'] ?? 0) === $user_id;
}

function canAccessChat($conn, $peer_id, $product_id = 0, $user_id = null) {
    $user_id = $user_id !== null ? (int)$user_id : (int)($_SESSION['user_id'] ?? 0);
    $peer_id = (int)$peer_id;
    if ($user_id <= 0 || $peer_id <= 0 || $peer_id === $user_id) return false;
    if ($product_id > 0) {
        $product_id = (int)$product_id;
        $q = mysqli_query($conn, "SELECT user_id FROM products WHERE id=$product_id LIMIT 1");
        $p = $q ? mysqli_fetch_assoc($q) : null;
        return $p && ((int)$p['user_id'] === $peer_id || (int)$p['user_id'] === $user_id);
    }
    return true;
}

function ensure_seller_profile($conn, $user_id) {

    $user_id = (int)$user_id;
    if (!db_table_exists($conn, 'sellers')) return;
    $q = mysqli_query($conn, "SELECT id FROM sellers WHERE user_id=$user_id LIMIT 1");
    if ($q && mysqli_num_rows($q) === 0) {
        $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT first_name,last_name FROM users WHERE id=$user_id LIMIT 1"));
        $name = trim(($user['first_name'] ?? 'Seller') . ' ' . ($user['last_name'] ?? 'ReVibe'));
        $store_name = mysqli_real_escape_string($conn, $name . ' Store');
        mysqli_query($conn, "INSERT INTO sellers (user_id, store_name, verification_status) VALUES ($user_id, '$store_name', 'verified')");
    }
}

function generate_order_code() {
    return 'RV-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

function product_badges($product) {
    $badges = [];
    if (!empty($product['badges'])) {
        foreach (explode(',', $product['badges']) as $badge) {
            $badge = trim($badge);
            if ($badge !== '') $badges[] = $badge;
        }
    }
    if (!empty($product['condition_status']) && in_array($product['condition_status'], ['Baru', 'Like New', 'Sangat Baik'], true)) {
        $badges[] = 'Verified Preloved';
    }
    if (!empty($product['shipping_option']) && in_array($product['shipping_option'], ['cod', 'both'], true)) {
        $badges[] = 'COD Area Lokal';
    }
    return array_values(array_unique($badges));
}

function add_notification($conn, $user_id, $title, $message, $type = 'info') {
    if (!db_table_exists($conn, 'notifications')) return;
    $stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'isss', $user_id, $title, $message, $type);
        mysqli_stmt_execute($stmt);
    }
}

function log_admin_action($conn, $action, $target_type = null, $target_id = null, $detail = null) {
    if (!db_table_exists($conn, 'admin_logs')) return;
    $admin_id = (int)($_SESSION['user_id'] ?? 0);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = mysqli_prepare($conn, "INSERT INTO admin_logs (admin_id, action, target_type, target_id, detail, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $target_id_int = $target_id !== null ? (int)$target_id : 0;
        mysqli_stmt_bind_param($stmt, 'ississ', $admin_id, $action, $target_type, $target_id_int, $detail, $ip);
        mysqli_stmt_execute($stmt);
    }
}

function get_coin_balance($conn, $user_id) {

    $user_id = (int)$user_id;
    if ($user_id <= 0) return 0;

    $successIn = 0;
    $successOut = 0;
    $pendingOut = 0;
    if (db_table_exists($conn, 'coin_transactions')) {
        $qIn = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS total FROM coin_transactions WHERE user_id=$user_id AND status='success' AND type IN ('cashback','adjustment','refund','rank_reward','legacy_cashback')");
        $successIn = (int)(mysqli_fetch_assoc($qIn)['total'] ?? 0);
        $qOut = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS total FROM coin_transactions WHERE user_id=$user_id AND status='success' AND type='withdraw'");
        $successOut = (int)(mysqli_fetch_assoc($qOut)['total'] ?? 0);
        $qPending = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS total FROM coin_transactions WHERE user_id=$user_id AND status='pending' AND type='withdraw'");
        $pendingOut = (int)(mysqli_fetch_assoc($qPending)['total'] ?? 0);
    } else if (db_table_exists($conn, 'coins')) {
        $q = mysqli_query($conn, "SELECT balance FROM coins WHERE user_id=$user_id LIMIT 1");
        $legacy = $q ? mysqli_fetch_assoc($q) : null;
        return max(0, (int)($legacy['balance'] ?? 0));
    }

    $available = max(0, $successIn - $successOut - $pendingOut);
    if (db_table_exists($conn, 'coins')) {
        mysqli_query($conn, "INSERT IGNORE INTO coins (user_id, balance) VALUES ($user_id, 0)");
        mysqli_query($conn, "UPDATE coins SET balance=$available WHERE user_id=$user_id");
    }
    if (db_column_exists($conn, 'users', 'coins')) {
        mysqli_query($conn, "UPDATE users SET coins=$available WHERE id=$user_id");
    }
    return $available;
}

function revibe_coin_ledger_add($conn, $user_id, $type, $amount, $description='', $reference_type=null, $reference_id=null, $status='success', $idempotency_key = null) {
    if (!db_table_exists($conn, 'coin_transactions')) return false;
    $user_id=(int)$user_id; $amount=(int)$amount;
    if ($user_id<=0 || $amount<=0) return false;
    $allowed=['cashback','withdraw','adjustment','refund','rank_reward','legacy_cashback'];
    if (!in_array($type,$allowed,true)) $type='adjustment';
    $status = in_array($status, ['pending','success','failed'], true) ? $status : 'success';
    $reference_id = $reference_id !== null ? (int)$reference_id : null;
    if ($idempotency_key && db_column_exists($conn,'coin_transactions','idempotency_key')) {
        $safe=mysqli_real_escape_string($conn,$idempotency_key);
        $exists=mysqli_query($conn,"SELECT id FROM coin_transactions WHERE idempotency_key='$safe' LIMIT 1");
        if($exists && mysqli_num_rows($exists)>0) return false;
    }
    $cols=['user_id','type','amount','description','reference_type','reference_id','status'];
    $vals=['?','?','?','?','?','?','?'];
    $types='isissis';
    $params=[$user_id,$type,$amount,$description,$reference_type,$reference_id,$status];
    if (db_column_exists($conn,'coin_transactions','idempotency_key')) { $cols[]='idempotency_key'; $vals[]='?'; $types.='s'; $params[]=$idempotency_key; }
    if (db_column_exists($conn,'coin_transactions','order_id') && $reference_type==='order') { $cols[]='order_id'; $vals[]='?'; $types.='i'; $params[]=$reference_id; }
    $sql="INSERT INTO coin_transactions (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
    $stmt=mysqli_prepare($conn,$sql); if(!$stmt) return false;
    mysqli_stmt_bind_param($stmt,$types,...$params);
    $ok=mysqli_stmt_execute($stmt);
    get_coin_balance($conn,$user_id);
    return $ok;
}

function award_seller_cashback($conn, $order_id) {
    $order_id = (int)$order_id;
    if (!db_table_exists($conn, 'coin_transactions')) return false;
    $q = mysqli_query($conn, "SELECT seller_id, total_price, status FROM orders WHERE id=$order_id LIMIT 1");
    $order = $q ? mysqli_fetch_assoc($q) : null;
    if (!$order || ($order['status'] ?? '') !== 'completed') return false;
    $enabled = filter_var(revibe_env('SELLER_COIN_CASHBACK_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
    if (!$enabled) return false;
    $key = 'seller_coin_cashback_order_' . $order_id;
    $check = db_column_exists($conn,'coin_transactions','idempotency_key')
        ? mysqli_query($conn, "SELECT id FROM coin_transactions WHERE idempotency_key='".mysqli_real_escape_string($conn,$key)."' LIMIT 1")
        : mysqli_query($conn, "SELECT id FROM coin_transactions WHERE reference_type='order' AND reference_id=$order_id AND type='cashback' LIMIT 1");
    if ($check && mysqli_num_rows($check) > 0) return false;
    $seller_id = (int)$order['seller_id'];
    $amount = revibe_calculate_seller_cashback((int)$order['total_price']);
    if ($amount <= 0) return false;
    $desc = 'Simulasi cashback seller ' . revibe_seller_cashback_percent() . '% dari transaksi completed #' . $order_id;
    $ok = revibe_coin_ledger_add($conn, $seller_id, 'cashback', $amount, $desc, 'order', $order_id, 'success', $key);
    if ($ok && db_table_exists($conn,'seller_coin_ledger')) {
        $stmt=mysqli_prepare($conn,"INSERT IGNORE INTO seller_coin_ledger (seller_id, order_id, type, amount, idempotency_key, description, created_at) VALUES (?, ?, 'seller_coin_cashback_added', ?, ?, ?, NOW())");
        if($stmt){ mysqli_stmt_bind_param($stmt,'iiiss',$seller_id,$order_id,$amount,$key,$desc); mysqli_stmt_execute($stmt); }
    }
    if ($ok) {
        revibe_notify_user_event($conn, $seller_id, 'coin_cashback', 'Coin Cashback Seller Diterima', 'Simulasi cashback ' . revibe_seller_cashback_percent() . '% dari order #' . $order_id . ' sudah masuk: ' . number_format($amount) . ' koin.', ['order_id'=>$order_id,'coin'=>$amount]);
        revibe_audit_log($conn, 'seller_coin_cashback_added', 'order', $order_id, ['seller_id'=>$seller_id,'amount'=>$amount,'percent'=>revibe_seller_cashback_percent()]);
    }
    return $ok;
}

function revibe_sync_all_coin_balances($conn) {
    if (!db_table_exists($conn,'users')) return;
    $users=mysqli_query($conn,"SELECT id FROM users");
    if($users) while($u=mysqli_fetch_assoc($users)) get_coin_balance($conn,(int)$u['id']);
}

function revibe_seller_balance($conn, $user_id) {
    return revibe_seller_available_balance($conn, $user_id);
}

function revibe_seller_pending_withdrawal_total($conn, $user_id) {
    $user_id=(int)$user_id;
    if($user_id<=0) return 0;
    $total=0;
    if(db_table_exists($conn,'seller_withdrawals')){
        $q=mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) total FROM seller_withdrawals WHERE user_id=$user_id AND status='pending'");
        $row=$q?mysqli_fetch_assoc($q):null;
        $total=max($total,(int)($row['total']??0));
    }
    if(db_table_exists($conn,'seller_balance_transactions')){
        $q=mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) total FROM seller_balance_transactions WHERE user_id=$user_id AND status='pending' AND type IN ('seller_withdraw','seller_withdrawal_requested')");
        $row=$q?mysqli_fetch_assoc($q):null;
        $total=max($total,(int)($row['total']??0));
    }
    return max(0,$total);
}

function revibe_seller_available_balance($conn, $user_id) {
    $user_id=(int)$user_id;
    if($user_id<=0) return 0;
    $pendingWithdraw=revibe_seller_pending_withdrawal_total($conn,$user_id);
    if(db_table_exists($conn,'seller_balances') && db_column_exists($conn,'seller_balances','available_balance')){
        mysqli_query($conn,"INSERT IGNORE INTO seller_balances (seller_id,user_id,pending_balance,available_balance,withdrawn_balance,total_earned,balance) VALUES ($user_id,$user_id,0,0,0,0,0)");
        $q=mysqli_query($conn,"SELECT available_balance FROM seller_balances WHERE seller_id=$user_id OR user_id=$user_id LIMIT 1");
        $row=$q?mysqli_fetch_assoc($q):null;
        return max(0,(int)($row['available_balance']??0)-$pendingWithdraw);
    }
    if($user_id<=0 || !db_table_exists($conn,'seller_balance_transactions')) return 0;
    $in=mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) total FROM seller_balance_transactions WHERE user_id=$user_id AND status='success' AND type IN ('sale_release','adjustment','refund_reversal','order_completed_release')");
    $out=mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) total FROM seller_balance_transactions WHERE user_id=$user_id AND status='success' AND type IN ('seller_withdraw','refund','seller_withdrawal_paid')");
    $balance=max(0,(int)(mysqli_fetch_assoc($in)['total']??0) - (int)(mysqli_fetch_assoc($out)['total']??0) - $pendingWithdraw);
    if(db_table_exists($conn,'seller_balances')){
        mysqli_query($conn,"INSERT IGNORE INTO seller_balances (user_id,balance) VALUES ($user_id,0)");
        mysqli_query($conn,"UPDATE seller_balances SET balance=$balance WHERE user_id=$user_id");
    }
    return $balance;
}

function revibe_seller_pending_balance($conn, $user_id) {
    $user_id=(int)$user_id;
    if($user_id<=0 || !db_table_exists($conn,'seller_balances') || !db_column_exists($conn,'seller_balances','pending_balance')) return 0;
    mysqli_query($conn,"INSERT IGNORE INTO seller_balances (seller_id,user_id,pending_balance,available_balance,withdrawn_balance,total_earned,balance) VALUES ($user_id,$user_id,0,0,0,0,0)");
    $q=mysqli_query($conn,"SELECT pending_balance FROM seller_balances WHERE seller_id=$user_id OR user_id=$user_id LIMIT 1");
    $row=$q?mysqli_fetch_assoc($q):null;
    return max(0,(int)($row['pending_balance']??0));
}

function revibe_seller_ledger_add($conn,$user_id,$type,$amount,$description='',$reference_type=null,$reference_id=null,$status='success',$balance_type='available',$idempotency_key=null){
    $user_id=(int)$user_id; $amount=(int)$amount;
    if($user_id<=0 || $amount<=0) return false;
    $reference_id = $reference_id !== null ? (int)$reference_id : null;
    $balance_type = $balance_type === 'pending' ? 'pending' : 'available';
    $idempotency_key = $idempotency_key ?: hash('sha256', implode('|', [$user_id,$type,$amount,$reference_type,$reference_id,$balance_type]));
    if (db_table_exists($conn,'seller_ledger')) {
        $safe=mysqli_real_escape_string($conn,$idempotency_key);
        $exists=mysqli_query($conn,"SELECT id FROM seller_ledger WHERE idempotency_key='$safe' LIMIT 1");
        if($exists && mysqli_num_rows($exists)>0) return true;
    }
    if (db_table_exists($conn,'seller_balance_transactions') && db_column_exists($conn,'seller_balance_transactions','idempotency_key')) {
        $safe=mysqli_real_escape_string($conn,$idempotency_key);
        $exists=mysqli_query($conn,"SELECT id FROM seller_balance_transactions WHERE idempotency_key='$safe' LIMIT 1");
        if($exists && mysqli_num_rows($exists)>0) return true;
    }
    $before = $balance_type === 'pending' ? revibe_seller_pending_balance($conn,$user_id) : revibe_seller_available_balance($conn,$user_id);
    $after = $before;
    if (in_array($type, ['order_paid_pending','adjustment_credit','seller_coin_cashback_order'], true)) $after = $before + $amount;
    if (in_array($type, ['order_completed_release_pending_debit','seller_withdrawal_paid','refund_deducted','adjustment_debit'], true)) $after = max(0,$before - $amount);
    if (in_array($type, ['order_completed_release'], true)) $after = $before + $amount;
    if(db_table_exists($conn,'seller_ledger')){
        $order_id = $reference_type === 'order' ? $reference_id : null;
        $withdrawal_id = $reference_type === 'seller_withdrawal' ? $reference_id : null;
        $stmt=mysqli_prepare($conn,"INSERT INTO seller_ledger (seller_id,order_id,withdrawal_id,type,amount,balance_type,balance_before,balance_after,idempotency_key,description,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");
        if($stmt){ mysqli_stmt_bind_param($stmt,'iiisisiiss',$user_id,$order_id,$withdrawal_id,$type,$amount,$balance_type,$before,$after,$idempotency_key,$description); mysqli_stmt_execute($stmt); }
    }
    if(db_table_exists($conn,'seller_balance_transactions')){
        $legacyType = in_array($type, ['order_completed_release']) ? 'sale_release' : (str_starts_with($type,'seller_withdrawal') ? 'seller_withdraw' : (in_array($type,['refund_deducted'])?'refund':'adjustment'));
        $cols=['user_id','type','amount','description','reference_type','reference_id','status'];
        $vals=['?','?','?','?','?','?','?']; $types='isissis'; $params=[$user_id,$legacyType,$amount,$description,$reference_type,$reference_id,$status];
        if(db_column_exists($conn,'seller_balance_transactions','idempotency_key')){ $cols[]='idempotency_key'; $vals[]='?'; $types.='s'; $params[]=$idempotency_key; }
        if(db_column_exists($conn,'seller_balance_transactions','balance_type')){ $cols[]='balance_type'; $vals[]='?'; $types.='s'; $params[]=$balance_type; }
        $stmt=mysqli_prepare($conn,"INSERT INTO seller_balance_transactions (".implode(',',$cols).") VALUES (".implode(',',$vals).")");
        if($stmt){ mysqli_stmt_bind_param($stmt,$types,...$params); mysqli_stmt_execute($stmt); }
    }
    return true;
}

function revibe_create_pending_seller_balance($conn,$order_id){
    $order_id=(int)$order_id;
    if($order_id<=0 || !db_table_exists($conn,'seller_balances')) return false;
    $txQ=@mysqli_query($conn, "SELECT @@in_transaction AS tx");
    $txRow=$txQ?mysqli_fetch_assoc($txQ):null;
    $started=!$txRow || (int)($txRow['tx']??0)===0;
    if($started) mysqli_begin_transaction($conn); else @mysqli_query($conn, "SAVEPOINT revibe_escrow_pending");
    try {
        $q=mysqli_query($conn,"SELECT id,seller_id,total_price,payment_method,status FROM orders WHERE id=$order_id LIMIT 1 FOR UPDATE");
        $o=$q?mysqli_fetch_assoc($q):null;
        if(!$o) throw new Exception('Order tidak ditemukan.');
        if(($o['payment_method']??'')==='cod') { if($started) mysqli_commit($conn); return true; }
        $seller_id=(int)$o['seller_id']; $gross=(int)$o['total_price'];
        if($seller_id<=0 || $gross<=0) throw new Exception('Data escrow tidak valid.');
        $key='escrow_pending_order_'.$order_id;
        if(db_table_exists($conn,'seller_ledger')){
            $safe=mysqli_real_escape_string($conn,$key);
            $exists=mysqli_query($conn,"SELECT id FROM seller_ledger WHERE idempotency_key='$safe' LIMIT 1");
            if($exists && mysqli_num_rows($exists)>0){ if($started) mysqli_commit($conn); return true; }
        }
        mysqli_query($conn,"INSERT IGNORE INTO seller_balances (seller_id,user_id,pending_balance,available_balance,withdrawn_balance,total_earned,balance) VALUES ($seller_id,$seller_id,0,0,0,0,0)");
        $balQ=mysqli_query($conn,"SELECT * FROM seller_balances WHERE seller_id=$seller_id OR user_id=$seller_id LIMIT 1 FOR UPDATE");
        $bal=$balQ?mysqli_fetch_assoc($balQ):null;
        $before=(int)($bal['pending_balance']??0);
        $after=$before+$gross;
        mysqli_query($conn,"UPDATE seller_balances SET pending_balance=$after, total_earned=total_earned+$gross, updated_at=NOW() WHERE seller_id=$seller_id OR user_id=$seller_id LIMIT 1");
        if(db_table_exists($conn,'seller_ledger')){
            $desc='Dana escrow tertahan dari order #'.$order_id;
            $type='order_paid_pending'; $balanceType='pending';
            $stmt=mysqli_prepare($conn,"INSERT INTO seller_ledger (seller_id,order_id,type,amount,balance_type,balance_before,balance_after,idempotency_key,description,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())");
            if($stmt){ mysqli_stmt_bind_param($stmt,'iisisiiss',$seller_id,$order_id,$type,$gross,$balanceType,$before,$after,$key,$desc); mysqli_stmt_execute($stmt); }
        }
        if(db_table_exists($conn,'seller_balance_transactions')){
            revibe_seller_ledger_add($conn,$seller_id,'order_paid_pending',$gross,'Dana escrow tertahan dari order #'.$order_id,'order',$order_id,'success','pending',$key.'_legacy');
        }
        if($started) mysqli_commit($conn); else @mysqli_query($conn, "RELEASE SAVEPOINT revibe_escrow_pending");
        revibe_notify_user_event($conn,$seller_id,'order_paid','Order Baru Masuk','Pembayaran order #'.$order_id.' sudah diterima ReVibe. Dana masih tertahan di escrow sampai buyer konfirmasi barang sampai.',['order_id'=>$order_id]);
        revibe_audit_log($conn,'escrow_pending_created','order',$order_id,['seller_id'=>$seller_id,'amount'=>$gross]);
        return true;
    } catch (Throwable $e) {
        if($started) mysqli_rollback($conn); else @mysqli_query($conn, "ROLLBACK TO SAVEPOINT revibe_escrow_pending");
        revibe_log('error','escrow pending failed',['order_id'=>$order_id,'error'=>$e->getMessage()]);
        if(class_exists('ErrorTrackingService')) (new ErrorTrackingService())->capture($e,['order_id'=>$order_id,'flow'=>'escrow_pending'],'error');
        return false;
    }
}

function revibe_release_order_settlement($conn,$order_id){
    $order_id=(int)$order_id;
    if($order_id<=0 || !db_table_exists($conn,'seller_balances')) return false;
    $txQ=@mysqli_query($conn, "SELECT @@in_transaction AS tx");
    $txRow=$txQ?mysqli_fetch_assoc($txQ):null;
    $started=!$txRow || (int)($txRow['tx']??0)===0;
    if($started) mysqli_begin_transaction($conn); else @mysqli_query($conn, "SAVEPOINT revibe_escrow_release");
    try {
        $q=mysqli_query($conn,"SELECT id,seller_id,total_price,payment_method,status FROM orders WHERE id=$order_id LIMIT 1 FOR UPDATE");
        $o=$q?mysqli_fetch_assoc($q):null;
        if(!$o || ($o['status']??'')!=='completed') { if($started) mysqli_commit($conn); return true; }
        if(($o['payment_method']??'')==='cod') { if($started) mysqli_commit($conn); return true; }
        $seller_id=(int)$o['seller_id']; $gross=(int)$o['total_price'];
        $commission=revibe_calculate_platform_margin($gross); $net=$gross;
        $key='escrow_release_order_'.$order_id;
        if(db_table_exists($conn,'seller_ledger')){
            $safe=mysqli_real_escape_string($conn,$key);
            $exists=mysqli_query($conn,"SELECT id FROM seller_ledger WHERE idempotency_key='$safe' LIMIT 1");
            if($exists && mysqli_num_rows($exists)>0){ if($started) mysqli_commit($conn); return true; }
        }
        mysqli_query($conn,"INSERT IGNORE INTO seller_balances (seller_id,user_id,pending_balance,available_balance,withdrawn_balance,total_earned,balance) VALUES ($seller_id,$seller_id,0,0,0,0,0)");
        $balQ=mysqli_query($conn,"SELECT * FROM seller_balances WHERE seller_id=$seller_id OR user_id=$seller_id LIMIT 1 FOR UPDATE");
        $bal=$balQ?mysqli_fetch_assoc($balQ):null;
        $pendingBefore=(int)($bal['pending_balance']??0);
        $availableBefore=(int)($bal['available_balance']??0);
        $pendingAfter=max(0,$pendingBefore-$gross);
        $availableAfter=$availableBefore+$net;
        mysqli_query($conn,"UPDATE seller_balances SET pending_balance=$pendingAfter, available_balance=$availableAfter, balance=$availableAfter, updated_at=NOW() WHERE seller_id=$seller_id OR user_id=$seller_id LIMIT 1");
        if(db_table_exists($conn,'seller_ledger')){
            $descPending='Dana escrow keluar dari pending untuk order #'.$order_id;
            $typePending='order_completed_release_pending_debit'; $balancePending='pending'; $keyPending=$key.'_pending_debit';
            $stmt=mysqli_prepare($conn,"INSERT INTO seller_ledger (seller_id,order_id,type,amount,balance_type,balance_before,balance_after,idempotency_key,description,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())");
            if($stmt){ mysqli_stmt_bind_param($stmt,'iisisiiss',$seller_id,$order_id,$typePending,$gross,$balancePending,$pendingBefore,$pendingAfter,$keyPending,$descPending); mysqli_stmt_execute($stmt); }
            $desc='Dana escrow dilepas ke available balance untuk order #'.$order_id;
            $type='order_completed_release'; $balanceType='available';
            $stmt=mysqli_prepare($conn,"INSERT INTO seller_ledger (seller_id,order_id,type,amount,balance_type,balance_before,balance_after,idempotency_key,description,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())");
            if($stmt){ mysqli_stmt_bind_param($stmt,'iisisiiss',$seller_id,$order_id,$type,$net,$balanceType,$availableBefore,$availableAfter,$key,$desc); mysqli_stmt_execute($stmt); }
        }
        if(db_table_exists($conn,'seller_balance_transactions')){
            revibe_seller_ledger_add($conn,$seller_id,'order_completed_release',$net,'Dana escrow dilepas ke available balance untuk order #'.$order_id,'order',$order_id,'success','available',$key.'_legacy');
        }
        if(db_table_exists($conn,'platform_commissions') && $commission>0) mysqli_query($conn,"INSERT IGNORE INTO platform_commissions (order_id,seller_id,gross_amount,commission_amount,net_amount,status) VALUES ($order_id,$seller_id,$gross,$commission,$net,'success')");
        if($started) mysqli_commit($conn); else @mysqli_query($conn, "RELEASE SAVEPOINT revibe_escrow_release");
        revibe_notify_user_event($conn,$seller_id,'escrow_release','Order Selesai, Saldo Dirilis','Saldo order #'.$order_id.' sebesar '.money($net).' sudah masuk ke available balance. Margin platform demo '.money($commission).' dicatat sebagai simulasi.',['order_id'=>$order_id,'amount'=>$net]);
        revibe_audit_log($conn,'escrow_release','order',$order_id,['seller_id'=>$seller_id,'amount'=>$net,'commission'=>$commission]);
        return true;
    } catch (Throwable $e) {
        if($started) mysqli_rollback($conn); else @mysqli_query($conn, "ROLLBACK TO SAVEPOINT revibe_escrow_release");
        revibe_log('error','escrow release failed',['order_id'=>$order_id,'error'=>$e->getMessage()]);
        if(class_exists('ErrorTrackingService')) (new ErrorTrackingService())->capture($e,['order_id'=>$order_id,'flow'=>'escrow_release'],'error');
        return false;
    }
}

function get_unread_chat_count($conn, $user_id) {
    $user_id = (int)$user_id;
    if (!db_table_exists($conn, 'chat_messages')) return 0;
    $q = mysqli_query($conn, "SELECT COUNT(*) total FROM chat_messages WHERE receiver_id=$user_id AND is_read=0");
    return (int)(mysqli_fetch_assoc($q)['total'] ?? 0);
}

function get_total_sold_by_user($conn, $user_id) {
    $user_id = (int)$user_id;
    $q = mysqli_query($conn, "SELECT COALESCE(SUM(sold),0) total FROM products WHERE user_id=$user_id");
    return (int)(mysqli_fetch_assoc($q)['total'] ?? 0);
}

function seller_rank_label($sold) {
    $sold = (int)$sold;
    if ($sold >= 1000) return 'Diamond ReViber';
    if ($sold >= 500) return 'Platinum Seller';
    if ($sold >= 250) return 'Gold Seller';
    if ($sold >= 100) return 'Silver Seller';
    if ($sold >= 50) return 'Bronze Seller';
    return 'New ReViber';
}

function seller_next_rank_target($sold) {
    $sold = (int)$sold;
    foreach ([50, 100, 250, 500, 1000] as $target) {
        if ($sold < $target) return $target;
    }
    return null;
}

function monthly_sold_expr() {
    return "COALESCE(SUM(CASE WHEN o.status='completed' AND DATE_FORMAT(o.completed_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') THEN o.qty ELSE 0 END),0)";
}

function revibe_float_or_null($value) {
    if ($value === null || $value === '') return null;
    if (!is_numeric($value)) return null;
    return (float)$value;
}

function revibe_valid_coordinate($lat, $lng) {
    if ($lat === null || $lng === null) return false;
    if (!is_numeric($lat) || !is_numeric($lng)) return false;
    $lat = (float)$lat;
    $lng = (float)$lng;
    return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
}

function revibe_distance_km($lat1, $lng1, $lat2, $lng2) {
    if (!revibe_valid_coordinate($lat1, $lng1) || !revibe_valid_coordinate($lat2, $lng2)) return null;
    $earthRadius = 6371;
    $dLat = deg2rad((float)$lat2 - (float)$lat1);
    $dLng = deg2rad((float)$lng2 - (float)$lng1);
    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad((float)$lat1)) * cos(deg2rad((float)$lat2)) * sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($earthRadius * $c, 2);
}

function revibe_courier_services() {
    return [
        'JNE REG' => 'JNE REG - Regular',
        'JNE YES' => 'JNE YES - Express 1 Hari',
        'J&T EZ' => 'J&T EZ - Regular',
        'J&T Super' => 'J&T Super - Cepat',
        'SiCepat REG' => 'SiCepat REGULAR',
        'SiCepat BEST' => 'SiCepat BEST - 1 Hari',
        'COD Lokal' => 'COD Lokal / Bayar di Tempat',
    ];
}

function revibe_weight_kg_from_gram($weight_gram) {
    $gram = is_numeric($weight_gram) ? (int)$weight_gram : 1000;

    return max(1, (int)ceil(max(1, $gram) / 1000));
}

function revibe_shipping_zone($distance_km) {
    if ($distance_km === null || !is_numeric($distance_km)) return 'unknown';
    $d = max(0, (float)$distance_km);
    if ($d <= 10) return 'city';
    if ($d <= 30) return 'near_city';
    if ($d <= 75) return 'metro';
    if ($d <= 150) return 'regional';
    if ($d <= 500) return 'intercity';
    return 'far';
}

function revibe_shipping_cost_by_distance($distance_km, $courier = 'JNE REG', $weight_gram = 1000) {
    if ($distance_km === null || !is_numeric($distance_km)) return 15000;
    $d = max(0, (float)$distance_km);
    $kg = revibe_weight_kg_from_gram($weight_gram);
    $c = strtolower(trim((string)$courier));
    $zone = revibe_shipping_zone($d);

    $rules = [
        'jne reg' => [
            'min' => 10000,
            'base' => ['city'=>10000,'near_city'=>12000,'metro'=>18000,'regional'=>25000,'intercity'=>35000,'far'=>45000,'unknown'=>15000],
            'extra_kg' => 8000,
        ],
        'jne yes' => [
            'min' => 18000,
            'base' => ['city'=>18000,'near_city'=>22000,'metro'=>28000,'regional'=>42000,'intercity'=>60000,'far'=>85000,'unknown'=>25000],
            'extra_kg' => 14000,
        ],
        'j&t ez' => [
            'min' => 9000,
            'base' => ['city'=>9000,'near_city'=>11000,'metro'=>17000,'regional'=>24000,'intercity'=>34000,'far'=>45000,'unknown'=>15000],
            'extra_kg' => 7500,
        ],
        'j&t super' => [
            'min' => 16000,
            'base' => ['city'=>16000,'near_city'=>20000,'metro'=>28000,'regional'=>42000,'intercity'=>62000,'far'=>85000,'unknown'=>24000],
            'extra_kg' => 13000,
        ],
        'sicepat reg' => [
            'min' => 8000,
            'base' => ['city'=>8000,'near_city'=>10000,'metro'=>16000,'regional'=>23000,'intercity'=>33000,'far'=>44000,'unknown'=>14000],
            'extra_kg' => 7000,
        ],
        'sicepat best' => [
            'min' => 17000,
            'base' => ['city'=>17000,'near_city'=>21000,'metro'=>29000,'regional'=>43000,'intercity'=>64000,'far'=>88000,'unknown'=>25000],
            'extra_kg' => 13500,
        ],
        'cod lokal' => [
            'min' => 7000,
            'base' => ['city'=>7000,'near_city'=>12000,'metro'=>22000,'regional'=>35000,'intercity'=>50000,'far'=>75000,'unknown'=>12000],
            'extra_kg' => 4000,
        ],
    ];

    if (strpos($c, 'cod') !== false) $key = 'cod lokal';
    elseif (strpos($c, 'yes') !== false) $key = 'jne yes';
    elseif (strpos($c, 'jne') !== false) $key = 'jne reg';
    elseif (strpos($c, 'super') !== false) $key = 'j&t super';
    elseif (strpos($c, 'j&t') !== false || strpos($c, 'jt') !== false) $key = 'j&t ez';
    elseif (strpos($c, 'best') !== false) $key = 'sicepat best';
    elseif (strpos($c, 'sicepat') !== false) $key = 'sicepat reg';
    else $key = 'jne reg';

    $rule = $rules[$key];
    $cost = ($rule['base'][$zone] ?? $rule['base']['unknown']) + (($kg - 1) * $rule['extra_kg']);

    if ($key === 'cod lokal' && $d > 30) $cost += (int)ceil(($d - 30) * 700);

    $cost = max($rule['min'], $cost);
    return (int)(ceil($cost / 1000) * 1000);
}

function revibe_user_full_address($user) {
    if (!$user) return '';
    $parts = [];
    if (!empty($user['street_address'])) $parts[] = trim($user['street_address']);
    if (!empty($user['address_detail'])) $parts[] = trim($user['address_detail']);
    if (!empty($user['address_region'])) $parts[] = trim($user['address_region']);
    $full = trim(implode(', ', array_filter($parts)));
    if ($full === '' && !empty($user['address'])) $full = trim($user['address']);
    return $full;
}

function revibe_user_region($user) {
    if (!$user) return '';
    if (!empty($user['address_region'])) return trim($user['address_region']);
    if (!empty($user['city'])) return trim($user['city']);
    return '';
}

function revibe_address_label($user) {
    if (!$user) return 'Rumah';
    return !empty($user['address_label']) ? trim($user['address_label']) : 'Rumah';
}

function revibe_product_image($conn, $product_id, $fallback = 'default.png') {
    $product_id = (int)$product_id;
    $q = mysqli_query($conn, "SELECT image FROM product_images WHERE product_id=$product_id ORDER BY id ASC LIMIT 1");
    if ($q && $row = mysqli_fetch_assoc($q)) return $row['image'] ?: $fallback;
    return $fallback;
}


function revibe_service_fee_percent() {
    return (float)revibe_env('REVIBE_SERVICE_FEE_PERCENT', 12);
}

function revibe_seller_cashback_percent() {
    return (float)revibe_env('REVIBE_SELLER_CASHBACK_PERCENT', revibe_env('SELLER_COIN_CASHBACK_PERCENT', 6));
}

function revibe_platform_margin_percent() {
    return max(0, revibe_service_fee_percent() - revibe_seller_cashback_percent());
}

function revibe_calculate_service_fee($subtotal) {
    return (int)round(max(0, (int)$subtotal) * revibe_service_fee_percent() / 100);
}

function revibe_calculate_seller_cashback($subtotal) {
    return (int)round(max(0, (int)$subtotal) * revibe_seller_cashback_percent() / 100);
}

function revibe_calculate_platform_margin($subtotal) {
    $serviceFee = revibe_calculate_service_fee($subtotal);
    $cashback = revibe_calculate_seller_cashback($subtotal);
    return max(0, $serviceFee - $cashback);
}

function revibe_order_service_fee($order) {
    $stored = isset($order['service_fee']) ? (int)$order['service_fee'] : 0;
    return $stored > 0 ? $stored : revibe_calculate_service_fee((int)($order['total_price'] ?? 0));
}

function revibe_order_seller_cashback($order) {
    $stored = isset($order['seller_cashback_amount']) ? (int)$order['seller_cashback_amount'] : 0;
    return $stored > 0 ? $stored : revibe_calculate_seller_cashback((int)($order['total_price'] ?? 0));
}

function revibe_order_platform_margin($order) {
    $stored = isset($order['platform_margin_amount']) ? (int)$order['platform_margin_amount'] : 0;
    return $stored > 0 ? $stored : max(0, revibe_order_service_fee($order) - revibe_order_seller_cashback($order));
}

function revibe_order_grand_total($order) {
    $paymentAmount = isset($order['payment_amount']) ? (int)$order['payment_amount'] : 0;
    $invTotal = isset($order['inv_total']) ? (int)$order['inv_total'] : 0;
    if ($paymentAmount > 0) return $paymentAmount;
    if ($invTotal > 0) return $invTotal;
    return (int)($order['total_price'] ?? 0) + (int)($order['shipping_cost'] ?? 0) + revibe_order_service_fee($order) - (int)($order['discount_amount'] ?? 0);
}

function revibe_demo_payment_note() {
    return 'Demo saja, jangan transfer uang asli.';
}

function revibe_service_fee_note() {
    return 'Biaya layanan ini adalah simulasi fee platform untuk demo.';
}

function revibe_payment_methods() {
    return [
        'transfer_bank' => 'Transfer Bank Demo ReVibe',
        'ewallet' => 'E-Wallet Demo ReVibe',
        'cod' => 'COD / Bayar di Tempat',
    ];
}

function revibe_payment_label($method) {
    $methods = revibe_payment_methods();
    return $methods[$method] ?? $method;
}

function revibe_payment_instruction($method, $amount = null) {
    $amountText = $amount !== null ? money($amount) : 'sesuai total checkout';
    $bankName = (string)revibe_env('ADMIN_MERCHANT_BANK_NAME', 'BANK DEMO REVIBE');
    $bankAccount = (string)revibe_env('ADMIN_MERCHANT_BANK_ACCOUNT', '0000000000');
    $holder = (string)revibe_env('ADMIN_MERCHANT_ACCOUNT_HOLDER', 'REVIBE DEMO');
    if ($bankName === '') $bankName = 'BANK DEMO REVIBE';
    if ($bankAccount === '') $bankAccount = '0000000000';
    if ($holder === '') $holder = 'REVIBE DEMO';
    if ($method === 'transfer_bank') {
        return [
            'title' => 'Transfer Bank Demo ReVibe',
            'lines' => [
                'Bank: ' . $bankName,
                'No. Rekening: ' . $bankAccount,
                'Atas Nama: ' . $holder,
                'Nominal: ' . $amountText,
                revibe_demo_payment_note(),
                'Setelah simulasi bayar, upload bukti pembayaran gambar agar admin bisa verifikasi demo.',
            ],
            'image' => null,
        ];
    }
    if ($method === 'ewallet') {
        return [
            'title' => 'E-Wallet Demo ReVibe',
            'lines' => [
                'Gunakan simulasi e-wallet demo ReVibe, bukan QR pembayaran asli.',
                'Nominal: ' . $amountText,
                revibe_demo_payment_note(),
                'Upload screenshot bukti pembayaran demo dalam format JPG, PNG, atau WEBP.',
            ],
            'image' => null,
        ];
    }
    return [
        'title' => 'COD / Bayar di Tempat',
        'lines' => [
            'Pembeli membayar langsung ke penjual saat barang diterima untuk simulasi COD.',
            'Seller memproses order dan pembeli tetap klik Konfirmasi Sampai agar transaksi selesai.',
            'Cashback seller ' . revibe_seller_cashback_percent() . '% diproses setelah order completed.',
        ],
        'image' => null,
    ];
}

function revibe_delivery_estimate_text($distance_km, $courier = 'JNE REG', $weight_gram = 1000) {
    if ($distance_km === null || $distance_km === '' || !is_numeric($distance_km)) return 'Estimasi belum tersedia';
    $d = max(0, (float)$distance_km);
    $kg = revibe_weight_kg_from_gram($weight_gram);
    $c = strtolower(trim((string)$courier));

    if (strpos($c, 'cod') !== false) {
        if ($d <= 10) return 'COD lokal: hari ini / maksimal 1 hari';
        if ($d <= 30) return 'COD lokal: 1–2 hari';
        return 'COD luar area lokal: perlu kesepakatan seller (2–4 hari)';
    }

    if (strpos($c, 'yes') !== false || strpos($c, 'best') !== false || strpos($c, 'super') !== false) {
        if ($kg > 3 && strpos($c, 'super') !== false) return 'J&T Super ideal maksimal 3 kg; gunakan EZ/REG untuk paket lebih berat';
        if ($d <= 150) return 'Estimasi 1 hari kerja';
        if ($d <= 500) return 'Estimasi 1–2 hari kerja';
        return 'Estimasi 2–3 hari kerja';
    }

    if (strpos($c, 'sicepat') !== false) {
        if ($d <= 150) return 'Estimasi 1–3 hari';
        if ($d <= 500) return 'Estimasi 2–5 hari';
        return 'Estimasi 3–7 hari';
    }

    if (strpos($c, 'j&t') !== false || strpos($c, 'jt') !== false) {
        if ($d <= 75) return 'Estimasi 1–2 hari';
        if ($d <= 500) return 'Estimasi 2–4 hari';
        return 'Estimasi 3–7 hari';
    }

    if ($d <= 75) return 'Estimasi 1–2 hari';
    if ($d <= 500) return 'Estimasi 2–4 hari';
    return 'Estimasi 3–7 hari';
}

function revibe_review_count($conn, $product_id) {
    $product_id = (int)$product_id;
    if (!db_table_exists($conn, 'reviews')) return 0;
    $q = mysqli_query($conn, "SELECT COUNT(*) total FROM reviews WHERE product_id=$product_id");
    return (int)(mysqli_fetch_assoc($q)['total'] ?? 0);
}

function revibe_rating_summary($conn, $product_id, $fallbackRating = 0) {
    $product_id = (int)$product_id;
    $summary = ['avg'=>0.0, 'count'=>0, 'dist'=>[1=>0,2=>0,3=>0,4=>0,5=>0]];
    if (!db_table_exists($conn, 'reviews')) return $summary;
    $q = mysqli_query($conn, "SELECT rating, COUNT(*) total FROM reviews WHERE product_id=$product_id GROUP BY rating");
    $total = 0; $sum = 0;
    if ($q) while($row = mysqli_fetch_assoc($q)) {
        $r = max(1, min(5, (int)$row['rating']));
        $c = (int)$row['total'];
        $summary['dist'][$r] = $c;
        $total += $c;
        $sum += $r * $c;
    }
    if ($total > 0) {
        $summary['count'] = $total;
        $summary['avg'] = round($sum / $total, 1);
    }
    return $summary;
}

function revibe_sample_reviews($product) {
    return [];
}

function revibe_sync_product_rating($conn, $product_id) {
    $product_id = (int)$product_id;
    if (!db_table_exists($conn, 'reviews')) return;
    $avgQ = mysqli_query($conn, "SELECT ROUND(AVG(rating),1) avg_rating, COUNT(*) total FROM reviews WHERE product_id=$product_id");
    $avg = $avgQ ? mysqli_fetch_assoc($avgQ) : null;
    $rating = (float)($avg['avg_rating'] ?? 0);
    $count = (int)($avg['total'] ?? 0);
    if (db_column_exists($conn, 'products', 'review_count')) {
        mysqli_query($conn, "UPDATE products SET rating=$rating, review_count=$count WHERE id=$product_id");
    } else {
        mysqli_query($conn, "UPDATE products SET rating=$rating WHERE id=$product_id");
    }
}

function revibe_upload_root() {
    return realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
}

function revibe_ensure_dir($dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
}

function revibe_safe_upload($file, $folder='products', $options=[]) {
    if (empty($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    $allowed = $options['allowed'] ?? ['jpg','jpeg','png','webp'];
    $maxSize = $options['max_size'] ?? (4 * 1024 * 1024);
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true) || (int)$file['size'] > $maxSize) return null;

    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    }
    $allowedMime = [
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','pdf'=>'application/pdf'
    ];
    if ($mime && isset($allowedMime[$ext]) && $mime !== $allowedMime[$ext]) return null;
    if (in_array($ext, ['jpg','jpeg','png','webp'], true) && @getimagesize($file['tmp_name']) === false) return null;

    $safeFolder = preg_replace('/[^a-zA-Z0-9_\-]/','', $folder) ?: 'products';
    $privateFolders = ['payment_proofs','complaints','refunds','kyc','private'];
    $isPrivate = !empty($options['private']) || in_array($safeFolder, $privateFolders, true);

    global $conn;
    try {
        $storage = new StorageService((isset($conn) && $conn instanceof mysqli) ? $conn : null);
        $stored = $storage->storeUploadedFile($file, $safeFolder, [
            'prefix' => $options['prefix'] ?? $safeFolder,
            'private' => $isPrivate,
            'visibility' => $isPrivate ? 'private' : 'public',
            'user_id' => $options['user_id'] ?? ($_SESSION['user_id'] ?? null),
        ]);
        if ($stored) return $stored;
    } catch (Throwable $e) {
        if (function_exists('revibe_log')) revibe_log('error', 'storage upload failed', ['folder'=>$safeFolder, 'error'=>$e->getMessage()]);
        if (function_exists('revibe_is_production') && revibe_is_production()) return null;
    }

    if (function_exists('revibe_is_multiserver') && revibe_is_multiserver()) return null;
    $root = realpath(__DIR__ . '/..');
    $dir = $root . DIRECTORY_SEPARATOR . ($isPrivate ? 'storage/private' : 'uploads') . DIRECTORY_SEPARATOR . $safeFolder . DIRECTORY_SEPARATOR;
    revibe_ensure_dir($dir);
    if ($isPrivate) {
        $ht = $dir . '.htaccess';
        if (!is_file($ht)) @file_put_contents($ht, "Deny from all\nRequire all denied\n");
    }
    $prefix = preg_replace('/[^a-zA-Z0-9_\-]/','', (string)($options['prefix'] ?? $safeFolder)) ?: $safeFolder;
    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target = $dir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) return null;
    return $filename;
}

function revibe_public_file_url($filename, $preferred='products') {
    $filename = basename((string)$filename);
    if ($filename === '') return revibe_asset_base_path() . 'assets/images/default.png';
    $base = revibe_asset_base_path();
    $root = realpath(__DIR__ . '/..');
    $folders = array_values(array_unique([$preferred, 'products', 'profile', 'assets_images']));
    try {
        $storage = new StorageService(isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli ? $GLOBALS['conn'] : null);
        $driver = strtolower((string)revibe_env('STORAGE_DRIVER', 'local'));
        $remotePublic = in_array($driver, ['s3','r2','spaces'], true) && (string)revibe_env('STORAGE_PUBLIC_BASE_URL','') !== '';
        if ($remotePublic) return $storage->url($preferred . '/' . $filename);
    } catch (Throwable $e) {}
    foreach ($folders as $folder) {
        if ($folder === 'assets_images') {
            if (is_file($root . '/assets/images/' . $filename)) return $base . 'assets/images/' . rawurlencode($filename);
        } else {
            if (is_file($root . '/uploads/' . $folder . '/' . $filename)) return $base . 'uploads/' . $folder . '/' . rawurlencode($filename);
        }
    }
    return $base . 'assets/images/' . rawurlencode($filename);
}

function revibe_private_file_url($filename, $folder='payment_proofs') {
    $base = revibe_asset_base_path();
    return $base . 'pages/admin/view_file.php?folder=' . rawurlencode($folder) . '&file=' . rawurlencode(basename((string)$filename));
}

function revibe_asset_base_path() {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (strpos($script, '/pages/admin/') !== false) return '../../';
    if (strpos($script, '/pages/') !== false) return '../';
    return '';
}

function render_revibe_floating_nav($conn = null) {
    if (function_exists('is_logged_in')) {
        $logged = is_logged_in();
    } else {
        $logged = isset($_SESSION['user_id']);
    }
    $base = revibe_asset_base_path();
    $home_url = $base . 'index.php';
    $rank_url = $base . 'pages/rankings.php';
    $chat_url = $base . 'pages/messages.php';
    $unread = 0;
    if ($logged && $conn) {
        $unread = get_unread_chat_count($conn, (int)$_SESSION['user_id']);
    }
    ?>

    <div class="rv-floating-actions rv-floating-global" aria-label="Navigasi cepat ReVibe">
        <a href="<?= e($home_url) ?>" class="rv-float-home" title="Kembali ke Beranda" aria-label="Beranda">
            <svg viewBox="0 0 24 24" width="25" height="25" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            <span>Home</span>
        </a>

        <?php if($logged): ?>
            <div class="rv-float-right">
                <a href="<?= e($rank_url) ?>" class="rv-float-circle rv-float-rank" title="Peringkat Seller" aria-label="Peringkat Seller">
                    <svg viewBox="0 0 24 24" width="23" height="23" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v4a5 5 0 0 1-10 0V4z"/><path d="M5 5H3v2a4 4 0 0 0 4 4"/><path d="M19 5h2v2a4 4 0 0 1-4 4"/>
                    </svg>
                    <span>Rank</span>
                </a>

                <a href="<?= e($chat_url) ?>" class="rv-float-circle rv-float-chat" title="Chat ReVibe" aria-label="Chat ReVibe">
                    <svg viewBox="0 0 24 24" width="23" height="23" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/><path d="M8 9h8"/><path d="M8 13h5"/>
                    </svg>
                    <span>Chat</span>
                    <?php if($unread > 0): ?><b class="rv-float-badge"><?= min(99, (int)$unread) ?></b><?php endif; ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
