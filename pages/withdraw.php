<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
revibe_require_verified_account($conn, 'verification_required.php');
$user_id=(int)$_SESSION['user_id'];

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    if (!revibe_rate_limit('withdrawal_request', 5, 3600)) {
        $_SESSION['error']='Terlalu sering mengajukan withdrawal. Coba lagi nanti.';
        header('Location: withdraw.php'); exit;
    }
    $amount=(int)($_POST['amount']??0);
    $method=trim($_POST['method']??'');
    $account=trim($_POST['account_number']??'');
    $name=trim($_POST['account_name']??'');
    if($amount < 1000){ $_SESSION['error']='Minimal penukaran 1.000 koin.'; header('Location: withdraw.php'); exit; }
    if($method===''||$account===''||$name===''){ $_SESSION['error']='Metode, nomor rekening/e-wallet, dan nama pemilik wajib diisi.'; header('Location: withdraw.php'); exit; }
    if(!db_table_exists($conn,'withdrawals') || !db_table_exists($conn,'coin_transactions')){ $_SESSION['error']='Fitur penukaran belum siap. Import SQL production V20 dulu.'; header('Location: withdraw.php'); exit; }

    mysqli_begin_transaction($conn);
    try{
        mysqli_query($conn,"INSERT IGNORE INTO coins (user_id,balance) VALUES ($user_id,0)");
        mysqli_query($conn,"SELECT balance FROM coins WHERE user_id=$user_id FOR UPDATE");
        $available = get_coin_balance($conn,$user_id);
        if($amount > $available) throw new Exception('Saldo koin tidak cukup.');
        $code='WD-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
        $stmt=mysqli_prepare($conn,"INSERT INTO withdrawals (withdrawal_code,user_id,amount,method,account_number,account_name,status) VALUES (?,?,?,?,?,?,'pending')");
        if(!$stmt) throw new Exception('Gagal menyiapkan withdrawal. Detail teknis masuk log.');
        mysqli_stmt_bind_param($stmt,'siisss',$code,$user_id,$amount,$method,$account,$name);
        mysqli_stmt_execute($stmt);
        $wid=mysqli_insert_id($conn);
        revibe_coin_ledger_add($conn,$user_id,'withdraw',$amount,'Pengajuan penukaran koin '.$code,'withdrawal',$wid,'pending');
        get_coin_balance($conn,$user_id);
        mysqli_commit($conn);
        add_notification($conn,$user_id,'Pengajuan penukaran dibuat','Kode '.$code.' sedang menunggu admin transfer.','withdrawal');
        $_SESSION['success']='Pengajuan penukaran berhasil dibuat. Koin ditahan sementara sampai admin memproses.';
    }catch(Throwable $e){
        mysqli_rollback($conn);
        revibe_log('error','withdraw request failed',['user_id'=>$user_id,'error'=>$e->getMessage()]);
        $_SESSION['error']=revibe_is_debug()?('Pengajuan gagal: '.$e->getMessage()):'Pengajuan belum dapat diproses saat ini. Silakan coba beberapa saat lagi.';
    }
    header('Location: withdraw.php'); exit;
}
$balance=get_coin_balance($conn,$user_id);
$history=db_table_exists($conn,'withdrawals')?mysqli_query($conn,"SELECT * FROM withdrawals WHERE user_id=$user_id ORDER BY id DESC LIMIT 20"):false;
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Tukar Koin - ReVibe</title><link rel="stylesheet" href="../assets/css/style.css"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="stylesheet" href="../assets/css/loader.css?v=25">
</head><body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>
<div class="navbar"><a href="profile.php" class="btn">← Profil</a><a href="../index.php" class="btn">Beranda</a></div>
<div class="page-shell withdraw-page-v19">
<?php if(isset($_SESSION['error'])): ?><div class="alert error"><?= e($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
<?php if(isset($_SESSION['success'])): ?><div class="alert success"><?= e($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
    <div class="page-header withdraw-hero-v19">
        <h1>Tukar Koin ke Rupiah</h1>
        <p>Saldo tersedia: <strong>🪙 <?= number_format($balance) ?></strong> = <strong><?= money($balance) ?></strong></p>
        <small>1 koin = Rp1. Koin akan ditahan dulu saat pengajuan, lalu admin transfer rupiah ke rekening/e-wallet kamu.</small>
    </div>
    <div class="withdraw-grid-v19">
        <section class="form-card withdraw-form-card-v19">
            <h2>Ajukan Penukaran</h2>
            <form method="POST" id="withdrawForm"><?= csrf_field() ?>
                <label>Nominal Koin</label>
                <input id="withdrawAmount" type="number" name="amount" max="<?= (int)$balance ?>" min="1000" placeholder="Contoh: 25000" required>
                <div class="withdraw-quick-v19">
                    <button type="button" data-amount="10000">10 rb</button><button type="button" data-amount="25000">25 rb</button><button type="button" data-amount="50000">50 rb</button><button type="button" data-amount="<?= (int)$balance ?>">Semua</button>
                </div>
                <div class="withdraw-preview-v19"><span>Estimasi rupiah diterima</span><strong id="withdrawPreview">Rp 0</strong></div>
                <label>Metode Penerimaan</label><select name="method" required><option value="BCA">BCA</option><option value="DANA">DANA</option><option value="OVO">OVO</option><option value="GoPay">GoPay</option><option value="ShopeePay">ShopeePay</option><option value="Bank Lain">Bank Lain</option></select>
                <label>Nomor Rekening / E-Wallet</label><input name="account_number" placeholder="Nomor tujuan transfer" required>
                <label>Nama Pemilik</label><input name="account_name" placeholder="Nama sesuai rekening/e-wallet" required>
                <button class="btn primary full" type="submit" <?= $balance<1000?'disabled':'' ?>>Ajukan Penukaran</button>
            </form>
        </section>
        <section class="panel-card withdraw-flow-v19"><h2>Alur Aman</h2><ol><li><b>User ajukan</b><span>Koin ditahan sebagai transaksi pending.</span></li><li><b>Admin cek</b><span>Admin transfer rupiah manual ke tujuan yang diisi.</span></li><li><b>Status selesai</b><span>Jika ditolak, koin otomatis dikembalikan karena transaksi pending dibatalkan.</span></li></ol></section>
    </div>
    <section class="panel-card withdraw-history-v19"><h2>Riwayat Penukaran</h2><div class="table-wrap"><table class="rv-table"><tr><th>Kode</th><th>Nominal</th><th>Tujuan</th><th>Status</th><th>Info Admin</th><th>Tanggal</th></tr><?php if($history && mysqli_num_rows($history)>0): while($w=mysqli_fetch_assoc($history)): ?><tr><td><?= e($w['withdrawal_code'] ?? '#'.$w['id']) ?></td><td><?= money($w['amount']) ?><br><small><?= number_format($w['amount']) ?> koin</small></td><td><b><?= e($w['method']) ?></b><br><small><?= e($w['account_number']) ?> • <?= e($w['account_name']) ?></small></td><td><span class="status-pill status-<?= e($w['status']) ?>"><?= e($w['status']==='approved'?'Sudah ditransfer':($w['status']==='rejected'?'Ditolak':'Menunggu admin')) ?></span></td><td><?php if(!empty($w['transfer_reference'])): ?><b>Ref:</b> <?= e($w['transfer_reference']) ?><br><?php endif; ?><?= e($w['admin_note'] ?? '-') ?></td><td><?= e(date('d M Y H:i', strtotime($w['created_at'] ?? 'now'))) ?></td></tr><?php endwhile; else: ?><tr><td colspan="6" class="muted">Belum ada pengajuan penukaran.</td></tr><?php endif; ?></table></div></section>
</div>
<script>
(function(){const amount=document.getElementById('withdrawAmount'),preview=document.getElementById('withdrawPreview'),max=<?= (int)$balance ?>;function money(n){return 'Rp '+Number(n||0).toLocaleString('id-ID')}function update(){let v=Math.max(0,Math.min(max,parseInt(amount.value||0)));preview.textContent=money(v)}document.querySelectorAll('.withdraw-quick-v19 button').forEach(btn=>btn.onclick=()=>{amount.value=btn.dataset.amount;update()});amount&&amount.addEventListener('input',update);})();
</script><?php render_revibe_floating_nav($conn); ?><script defer src="../assets/js/loader.js?v=25"></script>
</body></html>
