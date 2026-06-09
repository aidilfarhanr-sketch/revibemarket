<?php
class CoinService {
    private $conn; public function __construct($conn) { $this->conn = $conn; }
    public function awardSellerCashback(int $orderId): bool { return function_exists('award_seller_cashback') ? award_seller_cashback($this->conn, $orderId) : false; }
}
