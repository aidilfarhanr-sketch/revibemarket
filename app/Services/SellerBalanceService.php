<?php
class SellerBalanceService {
    private $conn; public function __construct($conn = null) { $this->conn = $conn; }
    public function available(int $sellerId): int { return function_exists('revibe_seller_available_balance') ? (int)revibe_seller_available_balance($this->conn,$sellerId) : 0; }
    public function pending(int $sellerId): int { return function_exists('revibe_seller_pending_balance') ? (int)revibe_seller_pending_balance($this->conn,$sellerId) : 0; }
    public function audit(int $sellerId): array {
        $available=$this->available($sellerId); $pending=$this->pending($sellerId); $ledgerAvailable=null; $ledgerPending=null;
        if($this->conn && function_exists('db_table_exists') && db_table_exists($this->conn,'seller_ledger')){
            $q=mysqli_query($this->conn,"SELECT balance_type, balance_after FROM seller_ledger WHERE seller_id={$sellerId} ORDER BY id ASC");
            while($q && ($r=mysqli_fetch_assoc($q))){ if(($r['balance_type']??'available')==='pending') $ledgerPending=(int)$r['balance_after']; else $ledgerAvailable=(int)$r['balance_after']; }
        }
        return ['seller_id'=>$sellerId,'available_balance'=>$available,'pending_balance'=>$pending,'ledger_available_last'=>$ledgerAvailable,'ledger_pending_last'=>$ledgerPending,'available_match'=>$ledgerAvailable===null || $ledgerAvailable===$available,'pending_match'=>$ledgerPending===null || $ledgerPending===$pending];
    }
}
