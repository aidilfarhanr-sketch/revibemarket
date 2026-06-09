<?php
class AuthService {
    public function passwordStrong(string $password): bool {
        return strlen($password) >= 8 && preg_match('/[A-Z]/', $password) && preg_match('/[a-z]/', $password) && preg_match('/[0-9]/', $password);
    }
    public function canUseAccount(array $user): bool {
        return ($user['status'] ?? 'active') !== 'blocked';
    }
}
