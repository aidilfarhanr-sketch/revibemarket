<?php
class AdminPolicy {
    private $conn; public function __construct($conn){ $this->conn=$conn; }
    public function allow(string $action, array $resource = []): bool { return (($_SESSION['role'] ?? '') === 'admin'); }
}
