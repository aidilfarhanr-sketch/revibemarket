<?php
class OrderPolicy {
    private $conn; public function __construct($conn){$this->conn=$conn;}
    public function view(int $orderId, int $userId): bool { return function_exists('canViewOrder') ? canViewOrder($this->conn,$orderId,$userId) : false; }
    public function buyerOwns(int $orderId, int $userId): bool { $q=mysqli_query($this->conn,"SELECT id FROM orders WHERE id=".(int)$orderId." AND buyer_id=".(int)$userId." LIMIT 1"); return $q && mysqli_num_rows($q)>0; }
    public function sellerOwns(int $orderId, int $sellerId): bool { $q=mysqli_query($this->conn,"SELECT id FROM orders WHERE id=".(int)$orderId." AND seller_id=".(int)$sellerId." LIMIT 1"); return $q && mysqli_num_rows($q)>0; }
}
