<?php
class ChatService {
    private $conn; public function __construct($conn = null) { $this->conn = $conn; }
    public function normalizeMessage(string $message): string { return trim(strip_tags($message)); }
    public function sendMessage(int $senderId, int $receiverId, string $message, ?int $productId = null): array {
        $message = $this->normalizeMessage($message);
        if (!$this->conn || $senderId<=0 || $receiverId<=0 || $message==='') return ['success'=>false,'message'=>'Pesan tidak valid.','error_code'=>'INVALID_CHAT'];
        $stmt=mysqli_prepare($this->conn,"INSERT INTO chat_messages (sender_id,receiver_id,product_id,message,is_read,created_at) VALUES (?,?,?,?,0,NOW())");
        if(!$stmt) return ['success'=>false,'message'=>'Chat belum tersedia.','error_code'=>'CHAT_UNAVAILABLE'];
        mysqli_stmt_bind_param($stmt,'iiis',$senderId,$receiverId,$productId,$message);
        $ok=mysqli_stmt_execute($stmt);
        if($ok && function_exists('revibe_notify_user_event')) revibe_notify_user_event($this->conn,$receiverId,'chat','Pesan Baru ReVibe',$message,['sender_id'=>$senderId,'product_id'=>$productId]);
        return ['success'=>$ok,'message'=>$ok?'Pesan terkirim.':'Gagal mengirim pesan.','data'=>['id'=>mysqli_insert_id($this->conn)]];
    }
}
