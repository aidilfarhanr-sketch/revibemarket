<?php
require_once __DIR__ . '/BaseRepository.php';
class ProductRepository extends BaseRepository { public function findById(int $id) { $stmt=mysqli_prepare($this->conn,'SELECT * FROM products WHERE id=? LIMIT 1'); mysqli_stmt_bind_param($stmt,'i',$id); mysqli_stmt_execute($stmt); return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); } }
