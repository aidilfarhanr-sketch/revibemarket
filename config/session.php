<?php

require_once __DIR__ . '/env.php';
if (is_file(dirname(__DIR__) . '/app/Services/RedisConnector.php')) require_once dirname(__DIR__) . '/app/Services/RedisConnector.php';

if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
          || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
          || filter_var(revibe_env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOLEAN);

    $lifetimeMinutes = max(15, (int)revibe_env('SESSION_LIFETIME_MINUTES', 120));
    ini_set('session.gc_maxlifetime', (string)($lifetimeMinutes * 60));
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name((string)revibe_env('SESSION_COOKIE', 'REVIBESESSID'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    $driver = strtolower((string)revibe_env('SESSION_DRIVER', 'file'));

    if ($driver === 'redis' && class_exists('RedisConnector')) {
        $redis = RedisConnector::connect();
        if ($redis) {
            $prefix = (string)revibe_env('SESSION_REDIS_PREFIX', RedisConnector::prefix('session:'));
            session_set_save_handler(
                fn($savePath, $sessionName) => true,
                fn() => true,
                function($id) use ($redis, $prefix) { $data = $redis->get($prefix . hash('sha256', $id)); return $data === false ? '' : (string)$data; },
                function($id, $data) use ($redis, $prefix, $lifetimeMinutes) { return (bool)$redis->setex($prefix . hash('sha256', $id), $lifetimeMinutes * 60, $data); },
                function($id) use ($redis, $prefix) { return (bool)$redis->del($prefix . hash('sha256', $id)); },
                function($max_lifetime) { return true; }
            );
        } else {
            $driver = (string)revibe_env('SESSION_FALLBACK_DRIVER', 'file');
            error_log('ReVibe: Redis session unavailable, fallback ' . $driver);
        }
    }

    if ($driver === 'database' && extension_loaded('mysqli')) {
        $sessionDb = null;
        $connectSessionDb = function() use (&$sessionDb) {
            if ($sessionDb instanceof mysqli && @mysqli_ping($sessionDb)) return $sessionDb;
            $sessionDb = @mysqli_connect(
                (string)revibe_env('DB_HOST','localhost'),
                (string)revibe_env('DB_USER','root'),
                (string)revibe_env('DB_PASS',''),
                (string)revibe_env('DB_NAME','revibe_market'),
                (int)revibe_env('DB_PORT',3306)
            );
            if ($sessionDb instanceof mysqli) @mysqli_set_charset($sessionDb, (string)revibe_env('DB_CHARSET','utf8mb4'));
            return $sessionDb;
        };
        if ($connectSessionDb() instanceof mysqli) {
            session_set_save_handler(
                function($savePath, $sessionName) { return true; },
                function() use (&$sessionDb) { if ($sessionDb instanceof mysqli) @mysqli_close($sessionDb); return true; },
                function($id) use ($connectSessionDb) {
                    $db = $connectSessionDb(); if (!$db) return '';
                    $hash = hash('sha256', $id);
                    $stmt = mysqli_prepare($db, "SELECT payload FROM app_sessions WHERE session_id_hash=? AND (expires_at IS NULL OR expires_at>NOW()) AND revoked_at IS NULL LIMIT 1");
                    if (!$stmt) return '';
                    mysqli_stmt_bind_param($stmt, 's', $hash); mysqli_stmt_execute($stmt);
                    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                    return (string)($row['payload'] ?? '');
                },
                function($id, $data) use ($connectSessionDb) {
                    $db = $connectSessionDb(); if (!$db) return false;
                    $hash = hash('sha256', $id);
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
                    $expires = date('Y-m-d H:i:s', time() + (int)ini_get('session.gc_maxlifetime'));
                    $stmt = mysqli_prepare($db, "INSERT INTO app_sessions (session_id_hash, payload, ip_address, user_agent, expires_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE payload=VALUES(payload), ip_address=VALUES(ip_address), user_agent=VALUES(user_agent), expires_at=VALUES(expires_at), updated_at=NOW()");
                    if (!$stmt) return false;
                    mysqli_stmt_bind_param($stmt, 'sssss', $hash, $data, $ip, $ua, $expires);
                    return mysqli_stmt_execute($stmt);
                },
                function($id) use ($connectSessionDb) {
                    $db = $connectSessionDb(); if (!$db) return false;
                    $hash = hash('sha256', $id);
                    $stmt = mysqli_prepare($db, "UPDATE app_sessions SET revoked_at=NOW(), revoked_reason='session_destroy' WHERE session_id_hash=?");
                    if (!$stmt) return false;
                    mysqli_stmt_bind_param($stmt, 's', $hash);
                    return mysqli_stmt_execute($stmt);
                },
                function($max_lifetime) use ($connectSessionDb) {
                    $db = $connectSessionDb(); if (!$db) return false;
                    $stmt = mysqli_prepare($db, "DELETE FROM app_sessions WHERE expires_at < NOW() OR (revoked_at IS NOT NULL AND revoked_at < DATE_SUB(NOW(), INTERVAL 7 DAY))");
                    return $stmt ? mysqli_stmt_execute($stmt) : false;
                }
            );
        }
    }

    session_start();
}
?>
