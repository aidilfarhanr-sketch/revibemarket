<?php

if (!defined('REVIBE_ERROR_HANDLER_LOADED')) {
    define('REVIBE_ERROR_HANDLER_LOADED', true);

    if (!function_exists('revibe_safe_error_message')) {
        function revibe_safe_error_message(string $fallback = 'Terjadi kendala saat memproses permintaan. Silakan coba lagi.'): string {
            $rid = function_exists('revibe_request_id') ? revibe_request_id() : ($_SERVER['REVIBE_REQUEST_ID'] ?? bin2hex(random_bytes(8)));
            return $fallback . ' Kode bantuan: ' . htmlspecialchars($rid, ENT_QUOTES, 'UTF-8');
        }
    }

    set_error_handler(function($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) return false;
        $ctx = ['severity'=>$severity, 'message'=>$message, 'file'=>$file, 'line'=>$line, 'request_id'=>function_exists('revibe_request_id') ? revibe_request_id() : null];
        if (function_exists('revibe_log')) revibe_log('warning', 'php warning/error', $ctx);
        return false;
    });

    set_exception_handler(function(Throwable $e) {
        $ctx = ['error'=>$e->getMessage(), 'file'=>$e->getFile(), 'line'=>$e->getLine(), 'request_id'=>function_exists('revibe_request_id') ? revibe_request_id() : null];
        if (function_exists('revibe_log')) revibe_log('critical', 'uncaught exception', $ctx);
        if (class_exists('ErrorTrackingService')) (new ErrorTrackingService())->capture($e, ['handler'=>'global_exception'], 'critical');
        $debug = function_exists('revibe_is_debug') ? revibe_is_debug() : false;
        if (!headers_sent()) {
            http_response_code(500);
            header('X-Request-Id: ' . (function_exists('revibe_request_id') ? revibe_request_id() : ''));
        }
        if ($debug) {
            echo '<pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>';
        } else {
            $errorPage = dirname(__DIR__) . '/pages/500.php';
            if (is_file($errorPage) && PHP_SAPI !== 'cli') { include $errorPage; }
            else echo revibe_safe_error_message('Maaf, sistem sedang mengalami kendala.');
        }
        exit;
    });

    register_shutdown_function(function() {
        $err = error_get_last();
        if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;
        $ctx = ['message'=>$err['message'], 'file'=>$err['file'], 'line'=>$err['line'], 'request_id'=>function_exists('revibe_request_id') ? revibe_request_id() : null];
        if (function_exists('revibe_log')) revibe_log('critical', 'fatal shutdown error', $ctx);
        if (class_exists('ErrorTrackingService')) (new ErrorTrackingService())->alert('fatal_shutdown_error', $ctx, 'critical');
    });
}
