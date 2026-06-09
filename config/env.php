<?php

if (!function_exists('revibe_project_root')) {
    function revibe_project_root(): string {
        return dirname(__DIR__);
    }
}

if (!function_exists('revibe_load_env')) {
    function revibe_load_env(?string $path = null): void {
        static $loaded = false;
        if ($loaded) return;
        $loaded = true;
        $path = $path ?: revibe_project_root() . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($path) || !is_readable($path)) return;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') continue;
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

if (!function_exists('revibe_env')) {
    function revibe_env(string $key, $default = null) {
        revibe_load_env();
        $value = getenv($key);
        if ($value === false) return $default;
        $lower = strtolower((string)$value);
        if (in_array($lower, ['true','(true)'], true)) return true;
        if (in_array($lower, ['false','(false)'], true)) return false;
        if (in_array($lower, ['null','(null)'], true)) return null;
        return $value;
    }
}

if (!function_exists('revibe_is_debug')) {
    function revibe_is_debug(): bool {
        return filter_var(revibe_env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('revibe_app_url')) {
    function revibe_app_url(string $path = ''): string {
        $base = rtrim((string)revibe_env('APP_URL', 'http://localhost/revibe'), '/');
        $path = ltrim($path, '/');
        return $path === '' ? $base : $base . '/' . $path;
    }
}

if (!function_exists('revibe_asset_url')) {
    function revibe_asset_url(string $path): string {
        $cdn = rtrim((string)revibe_env('CDN_URL', ''), '/');
        $asset = ltrim($path, '/');
        $url = ($cdn !== '') ? $cdn . '/' . $asset : revibe_app_url($asset);
        $version = revibe_env('ASSET_VERSION', 'v25');
        return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . rawurlencode((string)$version);
    }
}

if (!function_exists('revibe_is_production')) {
    function revibe_is_production(): bool {
        return (string)revibe_env('APP_ENV', 'local') === 'production';
    }
}

if (!function_exists('revibe_is_multiserver')) {
    function revibe_is_multiserver(): bool {
        return filter_var(revibe_env('MULTI_SERVER', false), FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('revibe_bool_env')) {
    function revibe_bool_env(string $key, bool $default = false): bool {
        return filter_var(revibe_env($key, $default ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('revibe_mask_secret')) {
    function revibe_mask_secret($value): string {
        $value = (string)$value;
        if ($value === '') return '';
        return substr(hash('sha256', $value), 0, 8) . ':set';
    }
}
