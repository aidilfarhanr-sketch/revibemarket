<?php
class PolicyMiddleware {
    public static function denyUnless(bool $condition, string $message = 'Akses ditolak.'): void {
        if (!$condition) { http_response_code(403); die($message); }
    }
}
