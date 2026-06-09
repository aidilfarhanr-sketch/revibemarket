<?php
class WhatsAppService {
    public function send(string $destination, string $message): bool {
        $provider = function_exists('revibe_env') ? (string)revibe_env('WHATSAPP_PROVIDER', 'log') : 'log';
        $destination = self::normalizeIndonesiaPhone($destination);
        if ($destination === '') return false;
        if ($provider === 'log' || $provider === '') {
            $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'logs';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            @file_put_contents($dir . DIRECTORY_SEPARATOR . 'whatsapp.log', json_encode(['time'=>date('c'), 'to'=>$destination, 'message'=>$message], JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
            return true;
        }
        $url = (string)revibe_env('WHATSAPP_API_URL', '');
        $token = (string)revibe_env('WHATSAPP_API_TOKEN', '');
        if ($url === '' || $token === '') return false;
        $payload = json_encode(['to'=>$destination, 'message'=>$message, 'sender'=>revibe_env('WHATSAPP_SENDER_ID', '')], JSON_UNESCAPED_UNICODE);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Content-Type: application/json', 'Authorization: Bearer '.$token], CURLOPT_POSTFIELDS=>$payload, CURLOPT_TIMEOUT=>15]);
        $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return $res !== false && $code >= 200 && $code < 300;
    }
    public static function normalizeIndonesiaPhone(string $phone): string {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if ($phone === '') return '';
        if (str_starts_with($phone, '+')) $phone = substr($phone, 1);
        if (str_starts_with($phone, '08')) $phone = '62' . substr($phone, 1);
        if (str_starts_with($phone, '8')) $phone = '62' . $phone;
        return preg_match('/^62[0-9]{8,15}$/', $phone) ? $phone : '';
    }
}
