<?php
require_once __DIR__ . '/RedisConnector.php';
class RateLimitService {
    private string $driver;
    private string $dir;
    private $conn;
    private $redis = null;

    public function __construct($conn = null, ?string $driver = null, ?string $dir = null) {
        $this->conn = $conn;
        $this->driver = $driver ?: (function_exists('revibe_env') ? (string)revibe_env('RATE_LIMIT_DRIVER', 'file') : 'file');
        $this->dir = $dir ?: dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . (function_exists('revibe_env') ? (string)revibe_env('RATE_LIMIT_DIR', 'storage/private/rate_limits') : 'storage/private/rate_limits');
        if (!is_dir($this->dir)) @mkdir($this->dir, 0755, true);
        if ($this->driver === 'redis') $this->connectRedis();
    }

    private function connectRedis(): void {
        $this->redis = class_exists('RedisConnector') ? RedisConnector::connect() : null;
    }

    public function hit(string $bucket, string $identity, int $maxRequests, int $windowSeconds): bool {
        $bucket = preg_replace('/[^a-zA-Z0-9_\-:\.]/', '_', $bucket);
        $identityHash = hash('sha256', $identity);
        $maxRequests = max(1, $maxRequests);
        $windowSeconds = max(1, $windowSeconds);

        if ($this->redis) return $this->hitRedis($bucket, $identityHash, $maxRequests, $windowSeconds);
        if ($this->driver === 'database' && $this->conn) return $this->hitDatabase($bucket, $identityHash, $maxRequests, $windowSeconds);
        return $this->hitFile($bucket, $identityHash, $maxRequests, $windowSeconds);
    }

    private function hitRedis(string $bucket, string $identityHash, int $maxRequests, int $windowSeconds): bool {
        $key = (class_exists('RedisConnector') ? RedisConnector::prefix('rate_limit:') : 'revibe_rl:') . hash('sha256', $bucket . '|' . $identityHash);
        $count = (int)$this->redis->incr($key);
        if ($count === 1) $this->redis->expire($key, $windowSeconds);
        return $count <= $maxRequests;
    }

    private function hitFile(string $bucket, string $identityHash, int $maxRequests, int $windowSeconds): bool {
        $file = $this->dir . DIRECTORY_SEPARATOR . hash('sha256', $bucket . '|' . $identityHash) . '.json';
        $now = time();
        $timestamps = [];
        if (is_file($file)) {
            $decoded = json_decode((string)@file_get_contents($file), true);
            if (is_array($decoded)) $timestamps = $decoded;
        }
        $timestamps = array_values(array_filter($timestamps, fn($ts) => is_int($ts) && ($now - $ts) < $windowSeconds));
        if (count($timestamps) >= $maxRequests) return false;
        $timestamps[] = $now;
        @file_put_contents($file, json_encode($timestamps), LOCK_EX);
        return true;
    }

    private function hitDatabase(string $bucket, string $identityHash, int $maxRequests, int $windowSeconds): bool {
        if (!function_exists('db_table_exists') || !db_table_exists($this->conn, 'rate_limits')) {
            return $this->hitFile($bucket, $identityHash, $maxRequests, $windowSeconds);
        }
        $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);
        $stmt = mysqli_prepare($this->conn, "DELETE FROM rate_limits WHERE created_at < ?");
        if ($stmt) { mysqli_stmt_bind_param($stmt, 's', $windowStart); mysqli_stmt_execute($stmt); }
        $stmt = mysqli_prepare($this->conn, "SELECT COUNT(*) AS total FROM rate_limits WHERE bucket=? AND identity_hash=? AND created_at >= ?");
        mysqli_stmt_bind_param($stmt, 'sss', $bucket, $identityHash, $windowStart);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ((int)($row['total'] ?? 0) >= $maxRequests) return false;
        $stmt = mysqli_prepare($this->conn, "INSERT INTO rate_limits (bucket, identity_hash, created_at) VALUES (?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, 'ss', $bucket, $identityHash);
        return mysqli_stmt_execute($stmt);
    }

    public function cleanup(int $olderThanSeconds = 172800): int {
        $deleted = 0;
        $cutoff = time() - max(60, $olderThanSeconds);
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
            if (@filemtime($file) < $cutoff) { @unlink($file); $deleted++; }
        }
        if ($this->conn && function_exists('db_table_exists') && db_table_exists($this->conn, 'rate_limits')) {
            $cutoffSql = date('Y-m-d H:i:s', $cutoff);
            $stmt = mysqli_prepare($this->conn, "DELETE FROM rate_limits WHERE created_at < ?");
            if ($stmt) { mysqli_stmt_bind_param($stmt, 's', $cutoffSql); mysqli_stmt_execute($stmt); $deleted += mysqli_affected_rows($this->conn); }
        }
        return $deleted;
    }

    public function health(): array {
        return ['driver'=>$this->driver, 'redis_connected'=>(bool)$this->redis, 'file_bucket_writable'=>is_writable($this->dir), 'database_ready'=>(bool)($this->conn && function_exists('db_table_exists') && db_table_exists($this->conn, 'rate_limits'))];
    }
}
