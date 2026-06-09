<?php
class AdminService {
    private $conn;
    public function __construct($conn = null) { $this->conn = $conn; }
    public function isAdmin(): bool { return ($_SESSION['role'] ?? '') === 'admin'; }
    public function requireAdmin(): void { if (!$this->isAdmin()) { http_response_code(403); include dirname(__DIR__,2).'/pages/403.php'; exit; } }
    public function dashboardCounts(): array {
        $tables = ['users','products','orders','complaints','withdrawals']; $out=[];
        foreach ($tables as $t) {
            if (!$this->conn || !function_exists('db_table_exists') || !db_table_exists($this->conn,$t)) { $out[$t]=0; continue; }
            $q=mysqli_query($this->conn,"SELECT COUNT(*) total FROM `$t`"); $out[$t]=(int)(mysqli_fetch_assoc($q)['total']??0);
        }
        return $out;
    }
    public function logAction(string $action, string $entityType='', $entityId=null, array $context=[]): bool {
        return function_exists('revibe_audit_log') ? revibe_audit_log($this->conn,$action,$entityType,$entityId,$context,$_SESSION['user_id']??null) : false;
    }
}
