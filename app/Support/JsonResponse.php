<?php
class JsonResponse {
    public static function send($result, int $status = 200): void {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Request-Id: ' . (function_exists('revibe_request_id') ? revibe_request_id() : bin2hex(random_bytes(8))));
        }
        if ($result instanceof ServiceResult) $payload = $result->toArray();
        elseif (is_array($result)) $payload = $result;
        else $payload = ['success' => false, 'message' => 'Response tidak valid.', 'error_code' => 'INVALID_RESPONSE'];
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
