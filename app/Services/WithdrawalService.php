<?php
class WithdrawalService {
    private $conn; public function __construct($conn) { $this->conn = $conn; }
    public function request(int $sellerId, int $amount, string $method, string $account, string $name): int {
        if (!function_exists('revibe_seller_available_balance')) throw new Exception('Service saldo belum siap.');
        mysqli_begin_transaction($this->conn);
        try {
            $available = revibe_seller_available_balance($this->conn, $sellerId);
            if ($amount < 10000) throw new Exception('Minimal withdrawal Rp10.000.');
            if ($amount > $available) throw new Exception('Saldo available tidak cukup.');
            $code = 'SW-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
            $stmt = mysqli_prepare($this->conn, "INSERT INTO seller_withdrawals (withdrawal_code,user_id,seller_id,amount,method,payout_method,account_number,account_name,account_holder_name,status,requested_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,'pending',NOW(),NOW())");
            if ($stmt) {
                $sellerId2=$sellerId; $bank=$method;
                mysqli_stmt_bind_param($stmt, 'siiisssss', $code, $sellerId, $sellerId2, $amount, $method, $bank, $account, $name, $name);
            } else {
                $stmt = mysqli_prepare($this->conn, "INSERT INTO seller_withdrawals (withdrawal_code,user_id,amount,method,account_number,account_name,status) VALUES (?,?,?,?,?,?,'pending')");
                mysqli_stmt_bind_param($stmt,'siisss',$code,$sellerId,$amount,$method,$account,$name);
            }
            if (!$stmt || !mysqli_stmt_execute($stmt)) throw new Exception('Gagal membuat withdrawal.');
            $wid = mysqli_insert_id($this->conn);
            if (function_exists('revibe_seller_ledger_add')) revibe_seller_ledger_add($this->conn,$sellerId,'seller_withdrawal_requested',$amount,'Pengajuan withdrawal '.$code,'seller_withdrawal',$wid,'pending');
            mysqli_commit($this->conn); return $wid;
        } catch (Throwable $e) { mysqli_rollback($this->conn); throw $e; }
    }
}
