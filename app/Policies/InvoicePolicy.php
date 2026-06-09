<?php
class InvoicePolicy {
    private $conn;
    public function __construct($conn){ $this->conn=$conn; }

    public function view(int $invoiceIdOrOrderId, int $userId, bool $byOrderId = true): bool {
        if (($_SESSION['role'] ?? '') === 'admin') return true;
        if ($invoiceIdOrOrderId <= 0 || $userId <= 0) return false;
        $where = $byOrderId ? 'o.id='.(int)$invoiceIdOrOrderId : 'i.id='.(int)$invoiceIdOrOrderId;
        $sql = "SELECT o.buyer_id, o.seller_id FROM orders o ";
        if (!$byOrderId) $sql .= "JOIN invoices i ON i.order_id=o.id ";
        $sql .= "WHERE {$where} LIMIT 1";
        $q = mysqli_query($this->conn, $sql);
        $row = $q ? mysqli_fetch_assoc($q) : null;
        return $row && ((int)$row['buyer_id'] === $userId || (int)$row['seller_id'] === $userId);
    }
}
