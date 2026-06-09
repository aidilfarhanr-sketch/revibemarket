<?php
class MailerService {
    public function send(string $to, string $subject, string $body): bool {
        $from = function_exists('revibe_env') ? (string)revibe_env('MAIL_FROM', 'no-reply@revibe.local') : 'no-reply@revibe.local';
        $fromName = function_exists('revibe_env') ? (string)revibe_env('MAIL_FROM_NAME', 'ReVibe Market') : 'ReVibe Market';
        $smtpHost = function_exists('revibe_env') ? (string)revibe_env('SMTP_HOST', '') : '';

        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                if ($smtpHost !== '') {
                    $mail->isSMTP();
                    $mail->Host = $smtpHost;
                    $mail->Port = (int)(function_exists('revibe_env') ? revibe_env('SMTP_PORT', 587) : 587);
                    $mail->SMTPAuth = true;
                    $mail->Username = (string)(function_exists('revibe_env') ? revibe_env('SMTP_USER', '') : '');
                    $mail->Password = (string)(function_exists('revibe_env') ? revibe_env('SMTP_PASS', '') : '');
                    $secure = (string)(function_exists('revibe_env') ? revibe_env('SMTP_SECURE', 'tls') : 'tls');
                    if ($secure !== '') $mail->SMTPSecure = $secure;
                }
                $mail->setFrom($from, $fromName);
                $mail->addAddress($to);
                $mail->Subject = $subject;
                $mail->Body = $body;
                return $mail->send();
            } catch (Throwable $e) {
                error_log('MailerService failed: ' . $e->getMessage());
                return false;
            }
        }

        if ((function_exists('revibe_env') ? (string)revibe_env('APP_ENV', 'local') : 'local') !== 'production') {
            $headers = 'From: ' . $fromName . ' <' . $from . '>' . "\r\n";
            return @mail($to, $subject, $body, $headers);
        }
        error_log('SMTP/PHPMailer belum dikonfigurasi. Email tidak dikirim ke ' . $to);
        return false;
    }
}
