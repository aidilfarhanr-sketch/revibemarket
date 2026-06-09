<?php
class FilePolicy {
    private $conn;
    public function __construct($conn){ $this->conn=$conn; }

    public function viewPrivate(string $folder, string $filename, int $userId): bool {
        if (($_SESSION['role'] ?? '') === 'admin') return true;
        $filename = basename($filename);
        if ($filename === '' || $userId <= 0) return false;

        if ($folder === 'payment_proofs' && function_exists('db_table_exists') && db_table_exists($this->conn, 'payments')) {
            $file = mysqli_real_escape_string($this->conn, $filename);
            $q = mysqli_query($this->conn, "SELECT o.buyer_id FROM payments p JOIN orders o ON p.order_id=o.id WHERE p.proof_file='{$file}' LIMIT 1");
            $row = $q ? mysqli_fetch_assoc($q) : null;

            return $row && (int)$row['buyer_id'] === $userId;
        }

        if ($folder === 'complaints' && function_exists('db_table_exists') && db_table_exists($this->conn, 'complaints')) {
            $file = mysqli_real_escape_string($this->conn, $filename);
            $q = mysqli_query($this->conn, "SELECT buyer_id, seller_id FROM complaints WHERE evidence_file='{$file}' LIMIT 1");
            $row = $q ? mysqli_fetch_assoc($q) : null;
            return $row && ((int)$row['buyer_id'] === $userId || (int)$row['seller_id'] === $userId);
        }

        return false;
    }
}
