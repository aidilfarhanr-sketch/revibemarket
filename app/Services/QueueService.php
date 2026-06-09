<?php
require_once __DIR__ . '/RedisConnector.php';
class QueueService {
    private $conn;
    private string $driver;
    private $redis = null;
    private string $redisKey;
    private int $retryLimit;
    private int $retryDelay;

    public function __construct($conn = null, ?string $driver = null) {
        $this->conn = $conn;
        $this->driver = strtolower((string)($driver ?: (function_exists('revibe_env') ? revibe_env('QUEUE_DRIVER', 'sync') : 'sync')));
        $this->redisKey = (string)(function_exists('revibe_env') ? revibe_env('QUEUE_REDIS_KEY', RedisConnector::prefix('queue')) : 'revibe_queue');
        $this->retryLimit = max(1, (int)(function_exists('revibe_env') ? revibe_env('QUEUE_RETRY_LIMIT', 3) : 3));
        $this->retryDelay = max(1, (int)(function_exists('revibe_env') ? revibe_env('QUEUE_RETRY_DELAY_SECONDS', 60) : 60));
        if ($this->driver === 'redis') $this->redis = RedisConnector::connect();
        if ($this->driver === 'redis' && !$this->redis) {
            $this->driver = $this->conn ? 'database' : 'sync';
            if (function_exists('revibe_log')) revibe_log('warning', 'redis queue unavailable, fallback ' . $this->driver);
        }
    }

    public function pushJob(string $type, array $payload = [], ?string $availableAt = null): bool {
        $job = [
            'id' => bin2hex(random_bytes(12)),
            'type' => $type,
            'payload' => $payload,
            'attempts' => 0,
            'available_at' => $availableAt ?: date('c'),
            'created_at' => date('c'),
        ];
        if ($this->redis) return (bool)$this->redis->rPush($this->redisKey, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($this->driver === 'database' && $this->conn && function_exists('db_table_exists') && db_table_exists($this->conn, 'notification_queue')) {
            if ($type === 'notification') {
                $p = $payload;
                return $this->pushNotification($p['user_id'] ?? null, $p['channel'] ?? 'in_app', $p['type'] ?? 'general', $p['title'] ?? 'ReVibe', $p['message'] ?? '', $p['destination'] ?? '', $p['payload'] ?? [], $availableAt);
            }
        }
        return $this->executeJob($job);
    }

    public function pushNotification(?int $userId, string $channel, string $type, string $title, string $message, string $destination = '', array $payload = [], ?string $scheduledAt = null): bool {
        $channel = in_array($channel, ['email','whatsapp','in_app'], true) ? $channel : 'in_app';
        $scheduledAt = $scheduledAt ?: date('Y-m-d H:i:s');
        if ($this->redis) {
            return $this->pushJob('notification', compact('userId', 'channel', 'type', 'title', 'message', 'destination', 'payload'), $scheduledAt);
        }
        if (!$this->conn || !function_exists('db_table_exists') || !db_table_exists($this->conn, 'notification_queue')) {
            if ($this->driver === 'sync') return $this->deliverNotification(['channel'=>$channel,'destination'=>$destination,'title'=>$title,'message'=>$message]);
            return false;
        }
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $stmt = mysqli_prepare($this->conn, "INSERT INTO notification_queue (user_id, channel, type, title, message, destination, payload_json, status, retry_count, scheduled_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 0, ?, NOW())");
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 'isssssss', $userId, $channel, $type, $title, $message, $destination, $payloadJson, $scheduledAt);
        return mysqli_stmt_execute($stmt);
    }

    public function process(int $limit = 25): int {
        $limit = max(1, min(200, $limit));
        $done = 0;
        if ($this->redis) {
            for ($i = 0; $i < $limit; $i++) {
                $raw = $this->redis->lPop($this->redisKey);
                if (!$raw) break;
                $job = json_decode($raw, true);
                if (!is_array($job)) continue;
                $availableAt = strtotime((string)($job['available_at'] ?? 'now')) ?: time();
                if ($availableAt > time()) { $this->redis->rPush($this->redisKey, $raw); break; }
                if ($this->executeJob($job)) $done++;
                else $this->retryRedisJob($job);
            }
            return $done;
        }
        if ($this->conn && function_exists('db_table_exists') && db_table_exists($this->conn, 'notification_queue')) {
            return $this->processDatabaseNotifications($limit);
        }
        return 0;
    }

    private function executeJob(array $job): bool {
        try {
            if (($job['type'] ?? '') === 'notification') {
                $p = $job['payload'] ?? [];

                return $this->deliverNotification([
                    'channel' => $p['channel'] ?? 'in_app',
                    'destination' => $p['destination'] ?? '',
                    'title' => $p['title'] ?? 'ReVibe',
                    'message' => $p['message'] ?? '',
                ]);
            }
            return true;
        } catch (Throwable $e) {
            if (function_exists('revibe_log')) revibe_log('error', 'queue job failed', ['type'=>$job['type'] ?? 'unknown', 'error'=>$e->getMessage()]);
            if (class_exists('ErrorTrackingService')) (new ErrorTrackingService())->capture($e, ['queue_job'=>$job], 'error');
            return false;
        }
    }

    private function retryRedisJob(array $job): void {
        $job['attempts'] = (int)($job['attempts'] ?? 0) + 1;
        if ($job['attempts'] < $this->retryLimit && $this->redis) {
            $job['available_at'] = date('c', time() + $this->retryDelay);
            $this->redis->rPush($this->redisKey, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } elseif (function_exists('revibe_log')) {
            revibe_log('error', 'redis queue job exhausted', ['job'=>$job]);
        }
    }

    private function processDatabaseNotifications(int $limit): int {
        require_once __DIR__ . '/EmailService.php';
        require_once __DIR__ . '/WhatsAppService.php';
        $retry = $this->retryLimit;
        $delay = max(1, (int)(function_exists('revibe_env') ? revibe_env('QUEUE_RETRY_DELAY_SECONDS', 60) : 60));
        $q = mysqli_query($this->conn, "SELECT * FROM notification_queue WHERE status IN ('pending','failed') AND retry_count < {$retry} AND (scheduled_at IS NULL OR scheduled_at <= NOW()) ORDER BY id ASC LIMIT {$limit}");
        $sent = 0;
        while ($q && ($n = mysqli_fetch_assoc($q))) {
            $id = (int)$n['id']; $ok = false; $error = null; $provider = 'local';
            try { $ok = $this->deliverNotification($n); $provider = ($n['channel'] ?? '') === 'email' ? 'smtp' : (($n['channel'] ?? '') === 'whatsapp' ? (string)(function_exists('revibe_env') ? revibe_env('WHATSAPP_PROVIDER', 'log') : 'log') : 'in_app'); }
            catch (Throwable $e) { $error = $e->getMessage(); $ok = false; }
            if ($ok) { mysqli_query($this->conn, "UPDATE notification_queue SET status='sent', sent_at=NOW(), last_error=NULL WHERE id={$id}"); $sent++; }
            else {
                $err = mysqli_real_escape_string($this->conn, substr((string)($error ?: 'Provider gagal mengirim'), 0, 500));
                mysqli_query($this->conn, "UPDATE notification_queue SET status='failed', retry_count=retry_count+1, scheduled_at=DATE_ADD(NOW(), INTERVAL {$delay} SECOND), last_error='{$err}' WHERE id={$id}");
                if (class_exists('ErrorTrackingService')) (new ErrorTrackingService())->alert('queue_notification_failed', ['notification_id'=>$id, 'channel'=>$n['channel'] ?? '', 'error'=>$err], 'warning');
            }
            $this->logNotification($id, $provider, $ok ? 'sent' : 'failed', $error);
        }
        return $sent;
    }

    private function deliverNotification(array $n): bool {
        require_once __DIR__ . '/EmailService.php';
        require_once __DIR__ . '/WhatsAppService.php';
        $channel = (string)($n['channel'] ?? 'in_app');
        if ($channel === 'email') return (new EmailService())->send((string)($n['destination'] ?? ''), (string)($n['title'] ?? 'ReVibe'), (string)($n['message'] ?? ''));
        if ($channel === 'whatsapp') return (new WhatsAppService())->send((string)($n['destination'] ?? ''), (string)($n['message'] ?? ''));
        return true;
    }

    private function logNotification(int $notificationId, string $provider, string $status, ?string $error = null): void {
        if (!$this->conn || !function_exists('db_table_exists') || !db_table_exists($this->conn, 'notification_logs')) return;
        $response = json_encode(['local_status'=>$status], JSON_UNESCAPED_SLASHES);
        $stmt = mysqli_prepare($this->conn, "INSERT INTO notification_logs (notification_id, provider, status, response_json, error_message, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($stmt) { mysqli_stmt_bind_param($stmt, 'issss', $notificationId, $provider, $status, $response, $error); mysqli_stmt_execute($stmt); }
    }

    public function health(): array {
        return ['driver'=>$this->driver, 'redis_connected'=>(bool)$this->redis, 'redis_key'=>$this->redisKey, 'retry_limit'=>$this->retryLimit, 'retry_delay_seconds'=>$this->retryDelay, 'database_queue'=>($this->conn && function_exists('db_table_exists') ? db_table_exists($this->conn,'notification_queue') : false)];
    }
}
