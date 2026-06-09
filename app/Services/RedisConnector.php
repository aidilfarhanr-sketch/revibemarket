<?php
class RedisConnector {
    public static function connect(?int $database = null) {
        if (!class_exists('Redis')) return null;
        try {
            $redis = new Redis();
            $host = (string)(function_exists('revibe_env') ? revibe_env('REDIS_HOST', '127.0.0.1') : '127.0.0.1');
            $port = (int)(function_exists('revibe_env') ? revibe_env('REDIS_PORT', 6379) : 6379);
            $password = (string)(function_exists('revibe_env') ? revibe_env('REDIS_PASSWORD', '') : '');
            $timeout = (float)(function_exists('revibe_env') ? revibe_env('REDIS_TIMEOUT', 0.5) : 0.5);
            if (!@$redis->connect($host, $port, $timeout)) return null;
            if ($password !== '' && !@$redis->auth($password)) return null;
            $db = $database ?? (int)(function_exists('revibe_env') ? revibe_env('REDIS_DATABASE', 0) : 0);
            if ($db > 0 && !@$redis->select($db)) return null;
            return $redis;
        } catch (Throwable $e) {
            if (function_exists('revibe_log')) revibe_log('warning', 'redis connection unavailable', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public static function prefix(string $suffix = ''): string {
        $prefix = (string)(function_exists('revibe_env') ? revibe_env('REDIS_PREFIX', 'revibe:') : 'revibe:');
        return $prefix . $suffix;
    }
}
