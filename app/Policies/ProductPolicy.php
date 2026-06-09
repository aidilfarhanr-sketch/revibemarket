<?php
class ProductPolicy { private $conn; public function __construct($conn){$this->conn=$conn;} public function update(int $productId,int $userId): bool { return function_exists('canUpdateProduct') ? canUpdateProduct($this->conn,$productId,$userId) : false; } }
