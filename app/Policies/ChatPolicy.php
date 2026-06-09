<?php
class ChatPolicy { private $conn; public function __construct($conn){$this->conn=$conn;} public function access(int $peerId,int $productId,int $userId): bool { return function_exists('canAccessChat') ? canAccessChat($this->conn,$peerId,$productId,$userId) : false; } }
