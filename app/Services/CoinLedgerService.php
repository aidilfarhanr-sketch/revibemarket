<?php
class CoinLedgerService {
    private $conn; public function __construct($conn = null) { $this->conn=$conn; }
    public function ledgerOnly(): bool { return true; }
    public function add(int $userId,string $type,int $amount,string $description='',?string $referenceType=null,?int $referenceId=null,string $status='success',?string $idempotencyKey=null): bool {
        return function_exists('revibe_coin_ledger_add') ? revibe_coin_ledger_add($this->conn,$userId,$type,$amount,$description,$referenceType,$referenceId,$status,$idempotencyKey) : false;
    }
}
