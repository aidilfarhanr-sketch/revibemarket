<?php
require_once __DIR__ . '/../../config/session.php';
include '../../config/db.php';
require_once '../../config/functions.php';
require_role('admin','../../index.php');
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=$_POST['action']??''; $id=(int)($_POST['id']??0); $adminId=(int)$_SESSION['user_id'];
    mysqli_begin_transaction($conn);
    try{
        $q=mysqli_query($conn,"SELECT * FROM seller_withdrawals WHERE id=$id FOR UPDATE");
        $w=$q?mysqli_fetch_assoc($q):null;
        if(!$w) throw new Exception('Pengajuan tidak ditemukan.');
        if(($w['status']??'')!=='pending') throw new Exception('Pengajuan sudah diproses.');
        $ref=mysqli_real_escape_string($conn,trim($_POST['transfer_reference']??''));
        $note=mysqli_real_escape_string($conn,trim($_POST['admin_note']??''));
        if($action==='approve'){
            mysqli_query($conn,"UPDATE seller_withdrawals SET status='approved', transfer_reference='$ref', admin_note='$note', processed_at=NOW(), processed_by=$adminId WHERE id=$id AND status='pending'");
            mysqli_query($conn,"UPDATE seller_balance_transactions SET status='success' WHERE reference_type='seller_withdrawal' AND reference_id=$id AND type='seller_withdraw' AND status='pending'");
            add_notification($conn,(int)$w['user_id'],'Tarik saldo penjualan berhasil','Admin sudah transfer saldo penjualan kamu.','settlement');
        }elseif($action==='reject'){
            mysqli_query($conn,"UPDATE seller_withdrawals SET status='rejected', admin_note='$note', processed_at=NOW(), processed_by=$adminId WHERE id=$id AND status='pending'");
            mysqli_query($conn,"UPDATE seller_balance_transactions SET status='failed' WHERE reference_type='seller_withdrawal' AND reference_id=$id AND type='seller_withdraw' AND status='pending'");
            add_notification($conn,(int)$w['user_id'],'Tarik saldo penjualan ditolak','Saldo penjualan kamu dikembalikan.','settlement');
        }
        revibe_seller_balance($conn,(int)$w['user_id']);
        log_admin_action($conn,$action.'_seller_withdrawal','seller_withdrawal',$id,$ref ?: $note);
        mysqli_commit($conn); $_SESSION['success']='Pengajuan saldo seller diproses.';
    }catch(Throwable $e){ mysqli_rollback($conn); revibe_log('error','seller withdrawal admin failed',['error'=>$e->getMessage()]); $_SESSION['error']=revibe_is_debug()?('Gagal: '.$e->getMessage()):'Aksi tidak dapat diproses. Jika masalah berlanjut, hubungi admin.'; }
    header('Location: seller_withdrawals.php'); exit;
}
$rows=db_table_exists($conn,'seller_withdrawals')?mysqli_query($conn,"SELECT w.*, u.first_name,u.last_name,u.email FROM seller_withdrawals w JOIN users u ON w.user_id=u.id ORDER BY w.id DESC LIMIT 100"):false;
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Withdraw Seller - Admin ReVibe</title><link rel="stylesheet" href="../../assets/css/style.css"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="stylesheet" href="../../assets/css/loader.css?v=25">
</head><body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>
<div class="navbar admin-navbar"><a href="index.php" class="btn">← Admin</a><a href="withdrawals.php" class="btn">Withdraw Koin</a></div><div class="page-shell"><div class="page-header"><h1>Tarik Saldo Penjualan Seller</h1><p>Admin transfer rupiah ke seller setelah pengajuan disetujui.</p></div><?php if(isset($_SESSION['error'])): ?><div class="alert error"><?= e($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?><?php if(isset($_SESSION['success'])): ?><div class="alert success"><?= e($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?><section class="panel-card"><div class="table-wrap"><table class="rv-table"><tr><th>Seller</th><th>Nominal</th><th>Tujuan</th><th>Status</th><th>Aksi</th></tr><?php if($rows && mysqli_num_rows($rows)): while($w=mysqli_fetch_assoc($rows)): ?><tr><td><?= e($w['first_name'].' '.$w['last_name']) ?><br><small><?= e($w['email']) ?></small><br><small><?= e($w['withdrawal_code']) ?></small></td><td><?= money($w['amount']) ?></td><td><?= e($w['method']) ?><br><small><?= e($w['account_number']) ?> • <?= e($w['account_name']) ?></small></td><td><span class="status-pill status-<?= e($w['status']) ?>"><?= e($w['status']) ?></span></td><td><?php if($w['status']==='pending'): ?><form method="POST" class="admin-withdraw-action"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><input name="transfer_reference" placeholder="Ref transfer"><input name="admin_note" placeholder="Catatan"><button class="btn primary" name="action" value="approve">Sudah Transfer</button><button class="btn danger" name="action" value="reject">Tolak</button></form><?php else: ?><?= e($w['transfer_reference'] ?? '-') ?><?php endif; ?></td></tr><?php endwhile; else: ?><tr><td colspan="5" class="muted">Belum ada pengajuan.</td></tr><?php endif; ?></table></div></section></div><?php render_revibe_floating_nav($conn); ?><script defer src="../../assets/js/loader.js?v=25"></script>
</body></html>
