<?php

class StorageService {
    private $conn;
    private string $driver;
    private string $root;
    private string $publicPath;
    private string $privatePath;

    public function __construct($conn = null, ?string $driver = null) {
        $this->conn = $conn;
        $this->driver = strtolower((string)($driver ?: (function_exists('revibe_env') ? revibe_env('STORAGE_DRIVER', 'local') : 'local')));
        if (!in_array($this->driver, ['local', 's3', 'r2', 'spaces'], true)) $this->driver = 'local';
        $this->root = dirname(__DIR__, 2);
        $this->publicPath = trim((string)(function_exists('revibe_env') ? revibe_env('STORAGE_PUBLIC_PATH', 'uploads') : 'uploads'), '/');
        $this->privatePath = trim((string)(function_exists('revibe_env') ? revibe_env('STORAGE_PRIVATE_PATH', 'storage/private') : 'storage/private'), '/');
    }

    public function put($file, string $path, string $visibility = 'private') {
        $visibility = $visibility === 'public' ? 'public' : 'private';
        $key = $this->normalizeKey($path);
        $body = $this->readBody($file);
        if ($body === null) return false;
        if (!$this->remoteConfigured()) {
            if ($this->mustUseRemote()) {
                if (function_exists('revibe_log')) revibe_log('critical', 'remote storage is required but not configured', ['driver'=>$this->driver, 'key'=>$key]);
                return false;
            }
            return $this->putLocal($body, $key, $visibility);
        }
        $contentType = $this->guessMime($file, $key);
        $ok = $this->s3Request('PUT', $key, $body, ['content-type' => $contentType], $visibility)['ok'] ?? false;
        if (!$ok) {
            if (function_exists('revibe_log')) revibe_log('critical', 'remote storage put failed', ['key'=>$key, 'driver'=>$this->driver]);
            if ($this->mustUseRemote()) return false;
            return $this->putLocal($body, $key, $visibility);
        }
        return ['disk'=>$this->driver, 'path'=>$key, 'url'=>$visibility === 'public' ? $this->url($key) : null, 'visibility'=>$visibility];
    }

    public function get(string $path) {
        $key = $this->normalizeKey($path);
        if ($this->remoteConfigured()) {
            $res = $this->s3Request('GET', $key);
            if (($res['ok'] ?? false)) return $res['body'];
        }
        $local = $this->resolveLocalPath($key, 'private');
        if (!is_file($local)) $local = $this->resolveLocalPath($key, 'public');
        return is_file($local) ? file_get_contents($local) : false;
    }

    public function delete(string $path): bool {
        $key = $this->normalizeKey($path);
        $remoteOk = true;
        if ($this->remoteConfigured()) $remoteOk = (bool)($this->s3Request('DELETE', $key)['ok'] ?? false);
        $localPrivate = $this->resolveLocalPath($key, 'private');
        $localPublic = $this->resolveLocalPath($key, 'public');
        if (is_file($localPrivate)) @unlink($localPrivate);
        if (is_file($localPublic)) @unlink($localPublic);
        return $remoteOk;
    }

    public function exists(string $path): bool {
        $key = $this->normalizeKey($path);
        if ($this->remoteConfigured()) {
            $res = $this->s3Request('HEAD', $key);
            if (($res['ok'] ?? false)) return true;
        }
        return is_file($this->resolveLocalPath($key, 'private')) || is_file($this->resolveLocalPath($key, 'public'));
    }

    public function url(string $path): string {
        $key = $this->normalizeKey($path);
        $base = rtrim((string)(function_exists('revibe_env') ? revibe_env('STORAGE_PUBLIC_BASE_URL', '') : ''), '/');
        if ($base !== '') return $base . '/' . str_replace('%2F', '/', rawurlencode($key));
        if ($this->remoteConfigured()) return $this->remoteObjectUrl($key);
        return function_exists('revibe_app_url') ? revibe_app_url($this->publicPath . '/' . $key) : ('/' . $this->publicPath . '/' . $key);
    }

    public function signedUrl(string $path, ?int $expires = null): string {
        $key = $this->normalizeKey($path);
        $expires = max(60, min(86400, (int)($expires ?: (function_exists('revibe_env') ? revibe_env('STORAGE_SIGNED_URL_EXPIRES', 600) : 600))));
        if ($this->remoteConfigured()) return $this->presignS3Url($key, $expires);
        $secret = (string)(function_exists('revibe_env') ? revibe_env('APP_KEY', 'revibe-local') : 'revibe-local');
        $until = time() + $expires;
        $sig = hash_hmac('sha256', $key . '|' . $until, $secret);
        return function_exists('revibe_app_url') ? revibe_app_url('pages/admin/view_file.php?file=' . rawurlencode(basename($key)) . '&folder=' . rawurlencode(dirname($key)) . '&expires=' . $until . '&sig=' . $sig) : '';
    }

    public function metadata($file): array {
        $name = is_array($file) ? (string)($file['name'] ?? '') : basename((string)$file);
        $tmp = is_array($file) ? (string)($file['tmp_name'] ?? '') : (string)$file;
        $size = is_array($file) ? (int)($file['size'] ?? 0) : (is_file($tmp) ? filesize($tmp) : strlen((string)$file));
        return [
            'original_name' => $name,
            'mime_type' => $this->guessMime($file, $name),
            'size' => $size,
            'sha256' => is_file($tmp) ? hash_file('sha256', $tmp) : hash('sha256', (string)$file),
        ];
    }

    public function saveMetadataToDatabase(array $data): bool { return $this->recordMetadata($data); }

    public function storeUploadedFile(array $file, string $folder, array $options = []) {
        $visibility = !empty($options['private']) ? 'private' : ($options['visibility'] ?? 'public');
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {

            if (empty($file['tmp_name']) || !is_file((string)$file['tmp_name'])) return false;
        }
        $folder = $this->safeFolder($folder);
        $ext = strtolower(pathinfo((string)($file['name'] ?? 'file'), PATHINFO_EXTENSION));
        $safeExt = preg_match('/^[a-z0-9]{1,8}$/', $ext) ? ('.' . $ext) : '';
        $stored = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . $safeExt;
        $key = $folder . '/' . $stored;
        $storedResult = $this->put($file, $key, $visibility);
        if (!$storedResult) return false;
        $meta = $this->metadata($file);
        $this->recordMetadata([
            'user_id' => $options['user_id'] ?? ($_SESSION['user_id'] ?? null),
            'disk' => is_array($storedResult) ? ($storedResult['disk'] ?? $this->driver) : $this->driver,
            'visibility' => $visibility,
            'original_name' => $meta['original_name'],
            'stored_name' => $stored,
            'path' => $key,
            'mime_type' => $meta['mime_type'],
            'size' => $meta['size'],
        ]);
        return $stored;
    }

    public function putPrivateUploadedFile(array $file, string $folder, int $userId = 0, array $options = []) {
        $options['private'] = true;
        $options['user_id'] = $userId;
        return $this->storeUploadedFile($file, $folder, $options);
    }

    public function privatePath(string $folder, string $filename): ?string {
        $folder = $this->safeFolder($folder);
        $filename = basename($filename);
        if ($filename === '') return null;
        $candidates = [
            $this->root . '/' . $this->privatePath . '/' . $folder . '/' . $filename,
            $this->root . '/uploads/' . $folder . '/' . $filename,
        ];
        foreach ($candidates as $candidate) {
            $path = realpath($candidate);
            if ($path && is_file($path) && str_starts_with($path, $this->root)) return $path;
        }
        return null;
    }

    public function privateUrl(string $filename, string $folder): string {
        return function_exists('revibe_private_file_url') ? revibe_private_file_url($filename, $folder) : $this->signedUrl($folder . '/' . basename($filename));
    }

    public function recordMetadata(array $data): bool {
        if (!$this->conn || !function_exists('db_table_exists') || !db_table_exists($this->conn, 'storage_files')) return false;
        $userId = isset($data['user_id']) && $data['user_id'] !== null ? (int)$data['user_id'] : null;
        $disk = (string)($data['disk'] ?? $this->driver);
        $visibility = (string)($data['visibility'] ?? 'private');
        $original = (string)($data['original_name'] ?? '');
        $stored = (string)($data['stored_name'] ?? basename((string)($data['path'] ?? '')));
        $path = (string)($data['path'] ?? '');
        $mime = (string)($data['mime_type'] ?? 'application/octet-stream');
        $size = (int)($data['size'] ?? 0);
        if ($stored === '' || $path === '') return false;
        $stmt = mysqli_prepare($this->conn, "INSERT INTO storage_files (user_id, disk, visibility, original_name, stored_name, path, mime_type, size, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 'issssssi', $userId, $disk, $visibility, $original, $stored, $path, $mime, $size);
        return mysqli_stmt_execute($stmt);
    }

    public function health(): array {
        @mkdir($this->root . '/' . $this->privatePath, 0755, true);
        @mkdir($this->root . '/' . $this->publicPath, 0755, true);
        $remote = $this->remoteConfigured();
        $remoteOk = null;
        if ($remote) {
            $probe = $this->s3Request('HEAD', 'revibe-healthcheck.txt');
            $remoteOk = ($probe['ok'] ?? false) || (($probe['code'] ?? 0) === 404);
        }
        return [
            'driver' => $this->driver,
            'active_driver' => $remote ? $this->driver : 'local',
            'local_private_writable' => is_writable($this->root . '/' . $this->privatePath),
            'local_public_writable' => is_writable($this->root . '/' . $this->publicPath),
            's3_r2_configured' => $remote,
            'remote_probe_ok' => $remoteOk,
            'remote_required' => $this->mustUseRemote(),
            'fallback' => $this->driver !== 'local' && !$remote ? ($this->mustUseRemote() ? 'disabled_remote_required' : 'local') : null,
        ];
    }

    private function putLocal(string $body, string $key, string $visibility): array {
        $base = $this->root . '/' . ($visibility === 'public' ? $this->publicPath : $this->privatePath);
        $target = $base . '/' . $key;
        @mkdir(dirname($target), 0755, true);
        file_put_contents($target, $body, LOCK_EX);
        return ['disk'=>'local', 'path'=>$key, 'url'=>$visibility === 'public' ? $this->url($key) : null, 'visibility'=>$visibility];
    }

    private function resolveLocalPath(string $key, string $visibility): string {
        $base = $visibility === 'public' ? $this->publicPath : $this->privatePath;
        return $this->root . '/' . $base . '/' . $this->normalizeKey($key);
    }

    private function readBody($file): ?string {
        if (is_array($file)) {
            $tmp = (string)($file['tmp_name'] ?? '');
            return is_file($tmp) ? file_get_contents($tmp) : null;
        }
        if (is_string($file) && is_file($file)) return file_get_contents($file);
        if (is_string($file)) return $file;
        return null;
    }

    private function guessMime($file, string $fallbackName = ''): string {
        $path = is_array($file) ? (string)($file['tmp_name'] ?? '') : (is_string($file) && is_file($file) ? $file : '');
        if ($path !== '' && function_exists('mime_content_type')) {
            $mime = @mime_content_type($path);
            if ($mime) return $mime;
        }
        $ext = strtolower(pathinfo($fallbackName, PATHINFO_EXTENSION));
        return ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif','pdf'=>'application/pdf','txt'=>'text/plain'][$ext] ?? 'application/octet-stream';
    }

    private function normalizeKey(string $path): string {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        $parts = [];
        foreach (explode('/', trim($path, '/')) as $part) {
            if ($part === '' || $part === '.' || $part === '..') continue;
            $parts[] = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', $part);
        }
        return implode('/', $parts) ?: ('files/' . bin2hex(random_bytes(8)));
    }

    private function safeFolder(string $folder): string { return preg_replace('/[^a-zA-Z0-9_\-]/', '', $folder) ?: 'files'; }

    private function mustUseRemote(): bool {
        $prod = function_exists('revibe_is_production') ? revibe_is_production() : ((string)(function_exists('revibe_env') ? revibe_env('APP_ENV', 'local') : 'local') === 'production');
        $multi = function_exists('revibe_is_multiserver') ? revibe_is_multiserver() : filter_var(function_exists('revibe_env') ? revibe_env('MULTI_SERVER', false) : false, FILTER_VALIDATE_BOOLEAN);
        $fail = filter_var(function_exists('revibe_env') ? revibe_env('STORAGE_FAIL_ON_REMOTE_ERROR', true) : true, FILTER_VALIDATE_BOOLEAN);
        return $prod && $multi && $this->driver !== 'local' && $fail;
    }

    private function remoteConfigured(): bool {
        if ($this->driver === 'local' || !function_exists('curl_init')) return false;
        return $this->bucket() !== '' && $this->accessKey() !== '' && $this->secretKey() !== '' && $this->endpoint() !== '';
    }

    private function bucket(): string {
        if (!function_exists('revibe_env')) return '';
        if ($this->driver === 'r2') return (string)revibe_env('STORAGE_R2_BUCKET', revibe_env('STORAGE_S3_BUCKET', ''));
        return (string)revibe_env('STORAGE_S3_BUCKET', '');
    }
    private function accessKey(): string {
        if (!function_exists('revibe_env')) return '';
        if ($this->driver === 'r2') return (string)revibe_env('STORAGE_R2_ACCESS_KEY', revibe_env('STORAGE_S3_ACCESS_KEY', revibe_env('STORAGE_S3_KEY', '')));
        return (string)revibe_env('STORAGE_S3_ACCESS_KEY', revibe_env('STORAGE_S3_KEY', ''));
    }
    private function secretKey(): string {
        if (!function_exists('revibe_env')) return '';
        if ($this->driver === 'r2') return (string)revibe_env('STORAGE_R2_SECRET_KEY', revibe_env('STORAGE_S3_SECRET_KEY', revibe_env('STORAGE_S3_SECRET', '')));
        return (string)revibe_env('STORAGE_S3_SECRET_KEY', revibe_env('STORAGE_S3_SECRET', ''));
    }
    private function region(): string { return (string)(function_exists('revibe_env') ? revibe_env('STORAGE_S3_REGION', 'auto') : 'auto'); }

    private function endpoint(): string {
        $endpoint = (string)(function_exists('revibe_env') ? revibe_env('STORAGE_S3_ENDPOINT', '') : '');
        if ($this->driver === 'r2') {
            $endpoint = (string)(function_exists('revibe_env') ? revibe_env('STORAGE_S3_ENDPOINT', '') : '');
            $account = (string)(function_exists('revibe_env') ? revibe_env('STORAGE_R2_ACCOUNT_ID', '') : '');
            if ($endpoint === '' && $account !== '') $endpoint = 'https://' . $account . '.r2.cloudflarestorage.com';
        }
        return rtrim($endpoint, '/');
    }

    private function remoteObjectUrl(string $key): string {
        $endpoint = $this->endpoint();
        $bucket = $this->bucket();
        $pathStyle = filter_var(function_exists('revibe_env') ? revibe_env('STORAGE_S3_USE_PATH_STYLE', true) : true, FILTER_VALIDATE_BOOLEAN);
        $encodedKey = str_replace('%2F', '/', rawurlencode($key));
        if ($pathStyle || $this->driver === 'r2') return $endpoint . '/' . rawurlencode($bucket) . '/' . $encodedKey;
        $host = preg_replace('#^https?://#', '', $endpoint);
        $scheme = str_starts_with($endpoint, 'http://') ? 'http://' : 'https://';
        return $scheme . $bucket . '.' . $host . '/' . $encodedKey;
    }

    private function s3Request(string $method, string $key, string $body = '', array $headers = [], string $visibility = 'private'): array {
        $url = $this->remoteObjectUrl($key);
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $canonicalUri = $parsed['path'] ?? '/';
        $payloadHash = hash('sha256', $body);
        $amzDate = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $headers = array_change_key_case($headers, CASE_LOWER);
        $headers['host'] = $host;
        $headers['x-amz-content-sha256'] = $payloadHash;
        $headers['x-amz-date'] = $amzDate;
        if ($visibility === 'public') $headers['x-amz-acl'] = 'public-read';
        ksort($headers);
        $canonicalHeaders = '';
        foreach ($headers as $k => $v) $canonicalHeaders .= strtolower($k) . ':' . trim((string)$v) . "\n";
        $signedHeaders = implode(';', array_keys($headers));
        $canonicalRequest = strtoupper($method) . "\n" . $canonicalUri . "\n\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $payloadHash;
        $scope = $date . '/' . $this->region() . '/s3/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$scope}\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->signingKey($date));
        $headers['authorization'] = 'AWS4-HMAC-SHA256 Credential=' . $this->accessKey() . '/' . $scope . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;
        $curlHeaders = [];
        foreach ($headers as $k => $v) $curlHeaders[] = $k . ': ' . $v;
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => strtoupper($method), CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => $curlHeaders, CURLOPT_TIMEOUT => 30]);
        if (in_array(strtoupper($method), ['PUT','POST'], true)) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        if (strtoupper($method) === 'HEAD') curl_setopt($ch, CURLOPT_NOBODY, true);
        $raw = (string)curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        return ['ok' => $err === '' && $code >= 200 && $code < 300, 'code'=>$code, 'error'=>$err, 'body'=>substr($raw, $headerSize)];
    }

    private function signingKey(string $date): string {
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey(), true);
        $kRegion = hash_hmac('sha256', $this->region(), $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    private function presignS3Url(string $key, int $expires): string {
        $url = $this->remoteObjectUrl($key);
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '/';
        $amzDate = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $scope = $date . '/' . $this->region() . '/s3/aws4_request';
        $query = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->accessKey() . '/' . $scope,
            'X-Amz-Date' => $amzDate,
            'X-Amz-Expires' => (string)$expires,
            'X-Amz-SignedHeaders' => 'host',
        ];
        ksort($query);
        $canonicalQuery = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $canonicalRequest = "GET\n{$path}\n{$canonicalQuery}\nhost:{$host}\n\nhost\nUNSIGNED-PAYLOAD";
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$scope}\n" . hash('sha256', $canonicalRequest);
        $query['X-Amz-Signature'] = hash_hmac('sha256', $stringToSign, $this->signingKey($date));
        return $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
