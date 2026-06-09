<?php
class PaymentPolicy {
    private $conn;
    public function __construct($conn){ $this->conn=$conn; }

    public function uploadProof(int $orderId, int $userId): bool {
        $q = mysqli_query($this->conn, "SELECT id FROM orders WHERE id=".(int)$orderId." AND buyer_id=".(int)$userId." AND status IN ('pending','pending_payment','waiting_payment','paid') LIMIT 1");
        return $q && mysqli_num_rows($q) > 0;
    }

    public function view(int $orderId, int $userId): bool {
        return function_exists('canViewOrder') ? canViewOrder($this->conn, $orderId, $userId) : false;
    }

    public function adminAction(): bool { return (($_SESSION['role'] ?? '') === 'admin'); }
}
