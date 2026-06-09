<?php
class WithdrawalPolicy { private $conn; public function __construct($conn){$this->conn=$conn;} public function request(int $sellerId,int $currentUserId): bool { return $sellerId === $currentUserId || (($_SESSION['role'] ?? '') === 'admin'); } }
