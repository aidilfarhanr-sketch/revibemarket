<?php
require_once __DIR__ . '/RedisConnector.php';
class CacheService {
    private string $driver;
    private string $dir;
    private string $prefix;
    private $redis = null;
    private const MISS = '__REVIBE_CACHE_MISS__';

    public function __construct(?string $dir = null, ?string $driver = null) {
        $this->driver = strtolower((string)($driver ?: (function_exists('revibe_env') ? revibe_env('CACHE_DRIVER', 'file') : 'file')));
        $this->dir = $dir ?: dirname(__DIR__, 2) . '/storage/cache';
        $this->prefix = RedisConnector::prefix('cache:');
        if (!is_dir($this->dir)) @mkdir($this->dir, 0755, true);
        if ($this->driver === 'redis') $this->redis = RedisConnector::connect();
        if ($this->driver === 'redis' && !$this->redis) {
            $this->driver = 'file';
            if (function_exists('revibe_log')) revibe_log('warning', 'redis cache unavailable, fallback file');
        }
    }

    private function file(string $key): string { return $this->dir . '/' . hash('sha256', $key) . '.json'; }
    private function key(string $key): string { return $this->prefix . preg_replace('/[^a-zA-Z0-9_:\-.]/', '_', $key); }

    public function get(string $key, $default = null) {
        if ($this->redis) {
            $value = $this->redis->get($this->key($key));
            if ($value !== false && $value !== null) {
                $decoded = json_decode($value, true);
                return is_array($decoded) && array_key_exists('value', $decoded) ? $decoded['value'] : $default;
            }
            return $default;
        }
        $f = $this->file($key);
        if (!is_file($f)) return $default;
        $d = json_decode((string)@file_get_contents($f), true);
        if (!is_array($d) || ($d['expires'] ?? 0) < time()) { @unlink($f); return $default; }
        return $d['value'] ?? $default;
    }

    public function put(string $key, $value, int $ttl = 300): bool {
        $ttl = max(1, $ttl);
        $payload = json_encode(['expires' => time() + $ttl, 'value' => $value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($this->redis) return (bool)$this->redis->setex($this->key($key), $ttl, $payload);
        return (bool)@file_put_contents($this->file($key), $payload, LOCK_EX);
    }

    public function remember(string $key, int $ttl, callable $callback) {
        $sentinel = self::MISS;
        $cached = $this->get($key, $sentinel);
        if ($cached !== $sentinel) return $cached;
        $value = $callback();
        $this->put($key, $value, $ttl);
        return $value;
    }

    public function forget(string $key): void {
        if ($this->redis) $this->redis->del($this->key($key));
        @unlink($this->file($key));
    }

    public function forgetPattern(string $pattern): int {
        $deleted = 0;
        if ($this->redis) {
            $it = null;
            $glob = $this->key($pattern);
            while (($keys = $this->redis->scan($it, $glob, 100)) !== false) {
                foreach ($keys as $key) { $this->redis->del($key); $deleted++; }
            }
            return $deleted;
        }

        foreach (glob($this->dir . '/*.json') ?: [] as $file) { @unlink($file); $deleted++; }
        return $deleted;
    }

    public function invalidatePublicProductCache(): int {
        return $this->forgetPattern('public:*') + $this->forgetPattern('products:*') + $this->forgetPattern('categories:*');
    }

    public function clearExpired(): int {
        $deleted = 0;
        foreach (glob($this->dir . '/*.json') ?: [] as $file) {
            $d = json_decode((string)@file_get_contents($file), true);
            if (!is_array($d) || ($d['expires'] ?? 0) < time()) { @unlink($file); $deleted++; }
        }
        return $deleted;
    }

    public function noStoreHeaders(): void {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }
    }

    public function publicAssetHeaders(int $seconds = 31536000): void {
        if (!headers_sent()) header('Cache-Control: public, max-age=' . max(60, $seconds) . ', immutable');
    }

    public function health(): array {
        return ['driver' => $this->driver, 'redis_connected' => (bool)$this->redis, 'file_cache_writable' => is_writable($this->dir), 'dir' => $this->dir];
    }
}
