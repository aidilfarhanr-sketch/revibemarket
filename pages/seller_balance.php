<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
revibe_require_verified_account($conn, 'verification_required.php');
$user_id=(int)$_SESSION['user_id'];
if(!db_table_exists($conn,'seller_withdrawals')){ $_SESSION['error']='Import SQL production V20 dulu untuk fitur saldo seller.'; header('Location: seller_center.php'); exit; }

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $amount=(int)($_POST['amount']??0);
    $method=trim($_POST['method']??'');
    $account=trim($_POST['account_number']??'');
    $name=trim($_POST['account_name']??'');
    mysqli_begin_transaction($conn);
    try{
        mysqli_query($conn,"INSERT IGNORE INTO seller_balances (user_id,balance) VALUES ($user_id,0)");
        mysqli_query($conn,"SELECT balance FROM seller_balances WHERE user_id=$user_id FOR UPDATE");
        $pendingWithdraw=revibe_seller_pending_withdrawal_total($conn,$user_id);
        $balance=revibe_seller_available_balance($conn,$user_id);
        if($amount<10000) throw new Exception('Minimal penarikan saldo penjualan Rp10.000.');
        if($pendingWithdraw>0 && $amount>$balance) throw new Exception('Masih ada penarikan pending. Saldo tersedia sudah dikurangi nominal pending.');
        if($amount>$balance) throw new Exception('Saldo penjualan tidak cukup.');
        if($method===''||$account===''||$name==='') throw new Exception('Data tujuan transfer wajib lengkap.');
        $code='SW-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
        $stmt=mysqli_prepare($conn,"INSERT INTO seller_withdrawals (withdrawal_code,user_id,amount,method,account_number,account_name,status) VALUES (?,?,?,?,?,?,'pending')");
        mysqli_stmt_bind_param($stmt,'siisss',$code,$user_id,$amount,$method,$account,$name);
        mysqli_stmt_execute($stmt);
        $wid=mysqli_insert_id($conn);
        revibe_seller_ledger_add($conn,$user_id,'seller_withdraw',$amount,'Pengajuan penarikan saldo '.$code,'seller_withdrawal',$wid,'pending');
        revibe_seller_balance($conn,$user_id);
        mysqli_commit($conn);
        $_SESSION['success']='Pengajuan tarik saldo penjualan berhasil. Admin akan transfer rupiah ke tujuan kamu.';
    }catch(Throwable $e){ mysqli_rollback($conn); revibe_log('error','seller balance failed',['error'=>$e->getMessage()]); $_SESSION['error']=revibe_is_debug()?('Gagal: '.$e->getMessage()):'Aksi tidak dapat diproses. Jika masalah berlanjut, hubungi admin.'; }
    header('Location: seller_balance.php'); exit;
}
$pendingWithdraw=revibe_seller_pending_withdrawal_total($conn,$user_id);
$balance=revibe_seller_available_balance($conn,$user_id);
$history=mysqli_query($conn,"SELECT * FROM seller_withdrawals WHERE user_id=$user_id ORDER BY id DESC LIMIT 20");
$ledger=mysqli_query($conn,"SELECT * FROM seller_balance_transactions WHERE user_id=$user_id ORDER BY id DESC LIMIT 20");
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Saldo Penjualan - ReVibe</title><link rel="stylesheet" href="../assets/css/style.css"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="stylesheet" href="../assets/css/loader.css?v=25">
</head><body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>

<div class="navbar"><a href="seller_center.php" class="btn">← Seller Center</a><a href="../index.php" class="btn">Beranda</a></div>
<div class="page-shell withdraw-page-v19">
<?php if(isset($_SESSION['error'])): ?><div class="alert error"><?= e($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
<?php if(isset($_SESSION['success'])): ?><div class="alert success"><?= e($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
<div class="page-header withdraw-hero-v19"><h1>Saldo Penjualan Seller</h1><p>Saldo tersedia siap tarik: <strong><?= money($balance) ?></strong> • Pending withdrawal: <strong><?= money($pendingWithdraw) ?></strong> • Pending escrow: <strong><?= money(revibe_seller_pending_balance($conn,$user_id)) ?></strong></p><small>Dana buyer ditahan dulu di escrow ReVibe. Saldo baru menjadi available setelah pembeli konfirmasi barang sampai. COD dibayar langsung ke seller. Jika masih ada penarikan pending, saldo tersedia sudah dikurangi nominal pending.</small></div>
<div class="withdraw-grid-v19"><section class="form-card withdraw-form-card-v19"><h2>Ajukan Tarik Saldo</h2><form method="POST"><?= csrf_field() ?><label>Nominal Rupiah</label><input type="number" name="amount" min="10000" max="<?= (int)$balance ?>" required><label>Metode</label><select name="method"><option>BCA</option><option>DANA</option><option>Bank Lain</option><option>OVO</option><option>GoPay</option></select><label>Nomor Tujuan</label><input name="account_number" required><label>Nama Pemilik</label><input name="account_name" required><button class="btn primary full" <?= $balance<10000?'disabled':'' ?>>Ajukan Tarik Saldo</button></form></section><section class="panel-card"><h2>Aturan Settlement</h2><p>Dana masuk saldo seller hanya dari pembayaran Transfer/E-wallet yang sudah selesai. Admin transfer manual setelah pengajuan disetujui.</p><p>Masih ada penarikan pending: <strong><?= money($pendingWithdraw) ?></strong>. Saldo tersedia sudah dikurangi nominal pending.</p></section></div>
<section class="panel-card"><h2>Riwayat Pengajuan</h2><div class="table-wrap"><table class="rv-table"><tr><th>Kode</th><th>Nominal</th><th>Tujuan</th><th>Status</th><th>Info Admin</th></tr><?php if($history && mysqli_num_rows($history)): while($w=mysqli_fetch_assoc($history)): ?><tr><td><?= e($w['withdrawal_code']) ?></td><td><?= money($w['amount']) ?></td><td><?= e($w['method']) ?><br><small><?= e($w['account_number']) ?> • <?= e($w['account_name']) ?></small></td><td><span class="status-pill status-<?= e($w['status']) ?>"><?= e($w['status']) ?></span></td><td><?= e($w['transfer_reference'] ?? '-') ?><br><small><?= e($w['admin_note'] ?? '') ?></small></td></tr><?php endwhile; else: ?><tr><td colspan="5" class="muted">Belum ada pengajuan.</td></tr><?php endif; ?></table></div></section>
<section class="panel-card"><h2>Ledger Saldo Penjualan</h2><div class="table-wrap"><table class="rv-table"><tr><th>Tipe</th><th>Nominal</th><th>Status</th><th>Keterangan</th></tr><?php if($ledger && mysqli_num_rows($ledger)): while($l=mysqli_fetch_assoc($ledger)): ?><tr><td><?= e($l['type']) ?></td><td><?= money($l['amount']) ?></td><td><?= e($l['status']) ?></td><td><?= e($l['description']) ?></td></tr><?php endwhile; else: ?><tr><td colspan="4" class="muted">Belum ada transaksi saldo.</td></tr><?php endif; ?></table></div></section>
</div><?php render_revibe_floating_nav($conn); ?><script defer src="../assets/js/loader.js?v=25"></script>
</body></html>
