<?php

class ServiceResult {
    public bool $success;
    public string $message;
    public array $data;
    public ?string $error_code;

    public function __construct(bool $success, string $message = '', array $data = [], ?string $error_code = null) {
        $this->success = $success;
        $this->message = $message;
        $this->data = $data;
        $this->error_code = $error_code;
    }

    public static function success(string $message = 'OK', array $data = []): self {
        return new self(true, $message, $data, null);
    }

    public static function fail(string $message = 'Gagal memproses permintaan.', ?string $errorCode = 'REVIBE_ERROR', array $data = []): self {
        return new self(false, $message, $data, $errorCode);
    }

    public function toArray(): array {
        $payload = ['success' => $this->success, 'message' => $this->message];
        if ($this->success) $payload['data'] = $this->data;
        else $payload['error_code'] = $this->error_code ?: 'REVIBE_ERROR';
        return $payload;
    }
}
