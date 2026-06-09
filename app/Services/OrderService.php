<?php
class OrderService {
    private $conn;
    public function __construct($conn) { $this->conn = $conn; }
    public function canMoveStatus(string $from, string $to): bool {
        $flow = ['pending'=>0,'waiting_payment'=>1,'paid'=>2,'processing'=>3,'shipped'=>4,'completed'=>5,'cancelled'=>99,'disputed'=>98];
        if (!isset($flow[$from], $flow[$to])) return false;
        if (in_array($from, ['completed','cancelled'], true)) return false;
        return $flow[$to] >= $flow[$from] || in_array($to, ['cancelled','disputed'], true);
    }
}
