<?php
class AuditService {
    private $conn;
    public function __construct($conn = null) { $this->conn = $conn; }
    public function log(string $action, ?string $entityType = null, $entityId = null, array $context = [], $userId = null): bool {
        if (!$this->conn || !function_exists('db_table_exists') || !db_table_exists($this->conn, 'audit_logs')) return false;
        $uid = $userId !== null ? (int)$userId : (int)($_SESSION['user_id'] ?? 0);
        $ip = function_exists('revibe_client_ip') ? revibe_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $requestId = function_exists('revibe_request_id') ? revibe_request_id() : bin2hex(random_bytes(8));
        $json = json_encode($context, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $targetId = $entityId !== null ? (int)$entityId : null;
        $stmt = mysqli_prepare($this->conn, "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, context_json, request_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt) {
            $stmt = mysqli_prepare($this->conn, "INSERT INTO audit_logs (user_id, action, target_type, target_id, ip_address, user_agent, metadata, request_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        }
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 'ississss', $uid, $action, $entityType, $targetId, $ip, $ua, $json, $requestId);
        return mysqli_stmt_execute($stmt);
    }
}
