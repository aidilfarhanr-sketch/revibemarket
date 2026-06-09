<?php
class UploadService {
    private array $allowed = ['jpg','jpeg','png','webp','gif','pdf'];
    public function validateImage(array $file, int $maxBytes = 5242880): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return [false, 'Upload gagal.'];
        if (($file['size'] ?? 0) > $maxBytes) return [false, 'Ukuran file terlalu besar.'];
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowed, true)) return [false, 'Extension file tidak diizinkan.'];
        $mime = mime_content_type($file['tmp_name']);
        $isImage = str_starts_with((string)$mime, 'image/');
        if (!$isImage && $mime !== 'application/pdf') return [false, 'MIME file tidak valid.'];
        if ($isImage && @getimagesize($file['tmp_name']) === false) return [false, 'File gambar tidak valid.'];
        return [true, null];
    }
    public function randomName(string $originalName): string {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        return date('YmdHis') . '_' . bin2hex(random_bytes(12)) . ($ext ? '.' . $ext : '');
    }
}
