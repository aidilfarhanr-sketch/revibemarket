<?php
require_once __DIR__ . '/../../config/session.php';
include '../../config/db.php';
require_once '../../config/functions.php';
require_role('admin', '../../index.php');
$status = $_GET['status'] ?? '';
$where = $status !== '' ? "WHERE w.status='".mysqli_real_escape_string($conn,$status)."'" : '';
$withdrawals = mysqli_query($conn,"SELECT w.*, u.first_name, u.last_name, u.email, u.phone FROM withdrawals w JOIN users u ON w.user_id=u.id $where ORDER BY w.id DESC LIMIT 100");
$summary = ['pending'=>0,'approved'=>0,'rejected'=>0,'nominal_pending'=>0];
$sumQ = mysqli_query($conn,"SELECT status, COUNT(*) total, COALESCE(SUM(amount),0) nominal FROM withdrawals GROUP BY status");
if($sumQ) while($row=mysqli_fetch_assoc($sumQ)){
    $summary[$row['status']] = (int)$row['total'];
    if($row['status']==='pending') $summary['nominal_pending'] = (int)$row['nominal'];
}
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Penukaran Koin - Admin ReVibe</title><link rel="stylesheet" href="../../assets/css/style.css"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="stylesheet" href="../../assets/css/loader.css?v=25">
</head><body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>

<div class="navbar admin-navbar compact-dashboard-navbar"><a href="index.php" class="btn">← Admin</a><div class="admin-nav-links"><a href="index.php" class="btn">Dashboard</a><a href="products.php" class="btn">Produk</a><a href="orders.php" class="btn">Transaksi</a><a href="withdrawals.php" class="btn primary">Penukaran Koin</a><a href="seller_withdrawals.php" class="btn">Saldo Seller</a><a href="rankings.php" class="btn">Peringkat</a><a href="reports.php" class="btn">Laporan</a></div></div>
<?php if(isset($_SESSION['success'])): ?><div class="rv-toast success"><?= e($_SESSION['success']); unset($_SESSION['success']); ?><button onclick="this.parentElement.remove()">✕</button></div><?php endif; ?>
<div class="page-shell admin-withdraw-page-v19">
    <div class="page-header"><h1>Kontrol Penukaran Koin</h1><p>Admin memeriksa pengajuan, transfer rupiah ke user, lalu menandai status sebagai sudah ditransfer.</p></div>
    <div class="stats-grid revibe-stats"><div class="stat-card"><h2><?= (int)$summary['pending'] ?></h2><p>Menunggu</p></div><div class="stat-card"><h2><?= money($summary['nominal_pending']) ?></h2><p>Nominal Pending</p></div><div class="stat-card"><h2><?= (int)$summary['approved'] ?></h2><p>Sudah Ditransfer</p></div><div class="stat-card"><h2><?= (int)$summary['rejected'] ?></h2><p>Ditolak</p></div></div>
    <div class="withdraw-admin-tabs-v19"><a class="btn <?= $status===''?'primary':'' ?>" href="withdrawals.php">Semua</a><a class="btn <?= $status==='pending'?'primary':'' ?>" href="withdrawals.php?status=pending">Pending</a><a class="btn <?= $status==='approved'?'primary':'' ?>" href="withdrawals.php?status=approved">Sudah Transfer</a><a class="btn <?= $status==='rejected'?'primary':'' ?>" href="withdrawals.php?status=rejected">Ditolak</a></div>
    <section class="panel-card"><h2>Daftar Pengajuan</h2><div class="withdraw-admin-list-v19">
    <?php if($withdrawals && mysqli_num_rows($withdrawals)>0): while($w=mysqli_fetch_assoc($withdrawals)): ?>
        <article class="withdraw-admin-card-v19">
            <div class="withdraw-user-v19"><div class="avatar-letter small"><?= e(strtoupper(substr($w['first_name'] ?? 'U',0,1))) ?></div><div><strong><?= e(($w['first_name']??'').' '.($w['last_name']??'')) ?></strong><small><?= e($w['email'] ?? '') ?><?= !empty($w['phone']) ? ' • '.e($w['phone']) : '' ?></small></div></div>
            <div class="withdraw-money-v19"><strong><?= money($w['amount']) ?></strong><span><?= number_format($w['amount']) ?> koin</span></div>
            <div class="withdraw-destination-v19"><b><?= e($w['method']) ?></b><span><?= e($w['account_number']) ?></span><small>a.n. <?= e($w['account_name']) ?></small></div>
            <div><span class="status-pill status-<?= e($w['status']) ?>"><?= e($w['status']==='approved'?'Sudah Ditransfer':($w['status']==='rejected'?'Ditolak':'Menunggu Transfer')) ?></span><small><?= e(date('d M Y H:i', strtotime($w['created_at'] ?? 'now'))) ?></small></div>
            <?php if($w['status']==='pending'): ?>
            <div class="withdraw-action-v19">
                <form method="POST" action="actions.php" class="withdraw-approve-form-v19"><?= csrf_field() ?><input type="hidden" name="action" value="approve_withdrawal"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><input name="transfer_reference" placeholder="No. referensi transfer"><textarea name="admin_note" rows="2" placeholder="Catatan admin, contoh: sudah ditransfer via BCA"></textarea><button class="btn primary" type="submit">Sudah Transfer & Setujui</button></form>
                <form method="POST" action="actions.php" class="withdraw-reject-form-v19"><?= csrf_field() ?><input type="hidden" name="action" value="reject_withdrawal"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><input name="admin_note" placeholder="Alasan penolakan"><button class="btn danger" type="submit">Tolak & Kembalikan Koin</button></form>
            </div>
            <?php else: ?>
            <div class="withdraw-note-v19"><?php if(!empty($w['transfer_reference'])): ?><span>Ref: <?= e($w['transfer_reference']) ?></span><?php endif; ?><small><?= e($w['admin_note'] ?? '-') ?></small><?php if(!empty($w['processed_at'])): ?><small>Diproses: <?= e(date('d M Y H:i', strtotime($w['processed_at']))) ?></small><?php endif; ?></div>
            <?php endif; ?>
        </article>
    <?php endwhile; else: ?><div class="empty-state"><h3>Belum ada pengajuan</h3><p>Pengajuan penukaran koin user akan muncul di sini.</p></div><?php endif; ?>
    </div></section>
</div><?php render_revibe_floating_nav($conn); ?>
<script defer src="../../assets/js/loader.js?v=25"></script>
</body></html>
