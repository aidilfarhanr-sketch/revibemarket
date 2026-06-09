<?php
class ErrorTrackingService {
    private string $dsn;
    private string $environment;
    public function __construct(?string $dsn = null) {
        $this->dsn = (string)($dsn ?? (function_exists('revibe_env') ? revibe_env('SENTRY_DSN', '') : ''));
        $this->environment = (string)(function_exists('revibe_env') ? revibe_env('SENTRY_ENVIRONMENT', revibe_env('APP_ENV', 'local')) : 'local');
    }

    public function capture(Throwable $e, array $context = [], string $level = 'error'): bool {
        $safe = $this->sanitize($context + [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        if (function_exists('revibe_log')) revibe_log($level, 'captured exception', $safe);
        if ($this->dsn === '' || !function_exists('curl_init')) return false;
        return $this->sendSentry([
            'event_id' => bin2hex(random_bytes(16)),
            'timestamp' => gmdate('c'),
            'platform' => 'php',
            'level' => $level,
            'environment' => $this->environment,
            'request' => [
                'url' => $_SERVER['REQUEST_URI'] ?? null,
                'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'headers' => ['User-Agent' => $_SERVER['HTTP_USER_AGENT'] ?? null],
            ],
            'user' => ['id' => $_SESSION['user_id'] ?? null],
            'tags' => ['request_id' => function_exists('revibe_request_id') ? revibe_request_id() : null],
            'exception' => ['values' => [[
                'type' => get_class($e),
                'value' => $e->getMessage(),
                'stacktrace' => ['frames' => $this->frames($e)],
            ]]],
            'extra' => $safe,
        ]);
    }

    public function alert(string $event, array $context = [], string $level = 'warning'): bool {
        $context = $this->sanitize($context);
        if (function_exists('revibe_log')) revibe_log($level, 'alert: ' . $event, $context);
        $channel = (string)(function_exists('revibe_env') ? revibe_env('ALERT_CHANNEL', 'none') : 'none');
        if ($channel === 'webhook') return $this->sendWebhook($event, $context, $level);
        if ($channel === 'email' && function_exists('revibe_send_mail')) {
            $to = (string)revibe_env('ALERT_EMAIL_TO', '');
            return $to !== '' && revibe_send_mail($to, '[ReVibe Alert] ' . $event, json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        return false;
    }

    private function sendWebhook(string $event, array $context, string $level): bool {
        $url = (string)(function_exists('revibe_env') ? revibe_env('ALERT_WEBHOOK_URL', '') : '');
        if ($url === '' || !function_exists('curl_init')) return false;
        $payload = json_encode(['event'=>$event, 'level'=>$level, 'context'=>$context, 'time'=>date('c')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_POSTFIELDS=>$payload, CURLOPT_TIMEOUT=>8]);
        $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return $res !== false && $code >= 200 && $code < 300;
    }

    private function sendSentry(array $payload): bool {
        $parts = parse_url($this->dsn);
        if (!$parts || empty($parts['host']) || empty($parts['user'])) return false;
        $projectId = trim((string)($parts['path'] ?? ''), '/');
        if ($projectId === '') return false;
        $url = ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . '/api/' . $projectId . '/store/';
        $auth = 'Sentry sentry_version=7, sentry_client=revibe-php-native/1.0, sentry_key=' . $parts['user'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Content-Type: application/json', 'X-Sentry-Auth: ' . $auth], CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), CURLOPT_TIMEOUT=>8]);
        $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return $res !== false && $code >= 200 && $code < 300;
    }

    private function sanitize(array $context): array {
        $blocked = ['password','pass','otp','code','api_key','key','secret','token','session','authorization','cookie','raw_payload'];
        $clean = [];
        foreach ($context as $k => $v) {
            $lk = strtolower((string)$k);
            $isBlocked = false;
            foreach ($blocked as $b) { if (str_contains($lk, $b)) { $isBlocked = true; break; } }
            if ($isBlocked) $clean[$k] = '[filtered]';
            elseif (is_array($v)) $clean[$k] = $this->sanitize($v);
            else $clean[$k] = is_string($v) ? mb_substr($v, 0, 1000) : $v;
        }
        return $clean;
    }

    private function frames(Throwable $e): array {
        $frames = [];
        foreach (array_reverse($e->getTrace()) as $trace) {
            $frames[] = ['filename'=>$trace['file'] ?? null, 'function'=>$trace['function'] ?? null, 'lineno'=>$trace['line'] ?? null];
        }
        return $frames;
    }
}
