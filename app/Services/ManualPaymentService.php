<?php
class ManualPaymentService {
    private $conn; public function __construct($conn=null){$this->conn=$conn;}
    public function createInstruction(array $order): array {
        return ['gateway'=>'manual','method'=>'manual_transfer','bank_name'=>(string)(function_exists('revibe_env')?revibe_env('ADMIN_MERCHANT_BANK_NAME',''):''),'account'=>(string)(function_exists('revibe_env')?revibe_env('ADMIN_MERCHANT_BANK_ACCOUNT',''):''),'holder'=>(string)(function_exists('revibe_env')?revibe_env('ADMIN_MERCHANT_ACCOUNT_HOLDER',''):'')];
    }
}
