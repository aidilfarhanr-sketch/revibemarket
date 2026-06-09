<?php
class LoggerService {
    private string $logDir;
    public function __construct(?string $logDir = null) {
        $this->logDir = $logDir ?: dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($this->logDir)) @mkdir($this->logDir, 0755, true);
    }
    public function log(string $severity, string $message, array $context = []): void {
        $entry = [
            'time' => date('c'),
            'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? ($_SERVER['UNIQUE_ID'] ?? bin2hex(random_bytes(8))),
            'severity' => $severity,
            'message' => $message,
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip' => function_exists('revibe_client_ip') ? revibe_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? null),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'route' => $_SERVER['REQUEST_URI'] ?? null,
            'context' => $context,
        ];
        $file = in_array($severity, ['error','critical','alert'], true) ? 'error.log' : 'app.log';
        @file_put_contents($this->logDir . DIRECTORY_SEPARATOR . $file, json_encode($entry, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
