<?php
require_once __DIR__ . '/MailerService.php';
class EmailService {
    private MailerService $mailer;
    public function __construct(?MailerService $mailer = null) { $this->mailer = $mailer ?: new MailerService(); }
    public function send(string $to, string $subject, string $body): bool { return $this->mailer->send($to, $subject, $body); }
}
