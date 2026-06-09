<?php
class ComplaintService {
    private $conn; public function __construct($conn){$this->conn=$conn;}
    public function holdEscrow(int $orderId,string $reason=''): bool { if(function_exists('revibe_audit_log')) revibe_audit_log($this->conn,'complaint_escrow_hold','order',$orderId,['reason'=>$reason]); return true; }
    public function create(int $orderId, int $userId, string $reason, string $description = ''): array {
        if(!$this->conn || $orderId<=0 || $userId<=0 || trim($reason)==='') return ['success'=>false,'message'=>'Data komplain tidak valid.','error_code'=>'INVALID_COMPLAINT'];
        mysqli_begin_transaction($this->conn);
        try{
            $stmt=mysqli_prepare($this->conn,"INSERT INTO complaints (order_id,user_id,reason,description,status,created_at) VALUES (?,?,?,?,'open',NOW())");
            if(!$stmt) throw new Exception('Tabel komplain belum siap.');
            mysqli_stmt_bind_param($stmt,'iiss',$orderId,$userId,$reason,$description); mysqli_stmt_execute($stmt);
            mysqli_query($this->conn,"UPDATE orders SET status='complaint', updated_at=NOW() WHERE id={$orderId}");
            $this->holdEscrow($orderId,$reason);
            mysqli_commit($this->conn); return ['success'=>true,'message'=>'Komplain berhasil dibuat.','data'=>['id'=>mysqli_insert_id($this->conn)]];
        }catch(Throwable $e){ mysqli_rollback($this->conn); if(function_exists('revibe_log')) revibe_log('error','complaint create failed',['error'=>$e->getMessage(),'order_id'=>$orderId]); return ['success'=>false,'message'=>'Komplain belum dapat diproses.','error_code'=>'COMPLAINT_FAILED']; }
    }
}
