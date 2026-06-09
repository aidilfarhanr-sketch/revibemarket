<?php
class EscrowPolicy {
    private $conn;
    public function __construct($conn){ $this->conn=$conn; }

    public function release(int $orderId): bool {
        if (($_SESSION['role'] ?? '') !== 'admin') return false;
        $q = mysqli_query($this->conn, "SELECT id FROM orders WHERE id=".(int)$orderId." AND status='completed' LIMIT 1");
        return $q && mysqli_num_rows($q) > 0;
    }

    public function view(int $sellerId, int $currentUserId): bool {
        return (($_SESSION['role'] ?? '') === 'admin') || $sellerId === $currentUserId;
    }
}
