<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

$lockKey = RedisConnector::prefix('cron:lock');
$lockTtl = 900;
$lockAcquired = false;
$redis = RedisConnector::connect();
$lockFileHandle = null;
try {
    if ($redis) {
        $lockAcquired = (bool)$redis->set($lockKey, gethostname() . ':' . getmypid(), ['nx', 'ex'=>$lockTtl]);
        if (!$lockAcquired) { echo "Cron sedang berjalan di instance lain\n"; exit(0); }
    } else {
        $lockFile = __DIR__ . '/../storage/cache/cron.lock';
        if (!is_dir(dirname($lockFile))) @mkdir(dirname($lockFile), 0755, true);
        $lockFileHandle = fopen($lockFile, 'c');
        $lockAcquired = $lockFileHandle && flock($lockFileHandle, LOCK_EX | LOCK_NB);
        if (!$lockAcquired) { echo "Cron sedang berjalan\n"; exit(0); }
    }

    if (db_column_exists($conn, 'users', 'reset_expires')) {
        mysqli_query($conn, "UPDATE users SET reset_token=NULL, reset_expires=NULL WHERE reset_expires IS NOT NULL AND reset_expires < NOW()");
    }
    if (db_table_exists($conn, 'verification_codes')) {
        mysqli_query($conn, "UPDATE verification_codes SET verified_at=COALESCE(verified_at, NOW()) WHERE verified_at IS NULL AND expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    }
    if (db_table_exists($conn, 'rate_limits')) {
        mysqli_query($conn, "DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)");
    }
    try { (new RateLimitService($conn))->cleanup(172800); } catch (Throwable $e) { revibe_log('warning','rate limit cleanup failed',['error'=>$e->getMessage()]); }
    if (db_table_exists($conn, 'payments')) {
        mysqli_query($conn, "UPDATE payments SET status='expired' WHERE status IN ('pending','waiting_upload','waiting_payment') AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    }
    if (db_table_exists($conn, 'orders')) {
        $days=(int)revibe_env('AUTO_RELEASE_AFTER_DELIVERED_DAYS',3);
        $q=mysqli_query($conn,"SELECT id,buyer_id FROM orders WHERE status='delivered' AND updated_at < DATE_SUB(NOW(), INTERVAL $days DAY) LIMIT 50");
        while($q && $o=mysqli_fetch_assoc($q)){
            $oid=(int)$o['id'];
            $hasComplaint = db_table_exists($conn,'complaints') ? mysqli_query($conn,"SELECT id FROM complaints WHERE order_id=$oid AND status IN ('open','review') LIMIT 1") : false;
            if($hasComplaint && mysqli_num_rows($hasComplaint)>0) continue;
            mysqli_query($conn,"UPDATE orders SET status='completed', completed_at=COALESCE(completed_at,NOW()), updated_at=NOW() WHERE id=$oid AND status='delivered'");
            revibe_order_status_history($conn,$oid,'delivered','completed',null,'Auto-complete setelah delivered');
            revibe_release_order_settlement($conn,$oid);
            award_seller_cashback($conn,$oid);
        }
    }
    try { (new QueueService($conn))->process(50); } catch (Throwable $e) { revibe_log('warning','queue cron failed',['error'=>$e->getMessage()]); }
    if (function_exists('curl_init')) {
        $ping = (string)revibe_env('HEALTHCHECKS_PING_URL','');
        if ($ping !== '') { $ch=curl_init($ping); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5]); curl_exec($ch); curl_close($ch); }
    }
    revibe_log('info', 'Cron production selesai');
    echo "Cron selesai\n";
} finally {
    if ($redis && $lockAcquired) $redis->del($lockKey);
    if ($lockFileHandle) { flock($lockFileHandle, LOCK_UN); fclose($lockFileHandle); }
}
