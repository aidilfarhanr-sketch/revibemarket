<?php
require_once __DIR__ . '/../../config/session.php';
include '../../config/db.php';
require_once '../../config/functions.php';
require_role('admin','../../index.php');
verify_csrf();
if (!revibe_rate_limit('admin_actions', 120, 600)) {
    $_SESSION['error']='Aksi admin terlalu sering. Tunggu sebentar.';
    header('Location: index.php'); exit;
}
$action=$_POST['action']??'';
$id=(int)($_POST['id']??0);
$adminId=(int)$_SESSION['user_id'];

try{
    switch($action){
        case 'approve_product':
            mysqli_begin_transaction($conn);
            $q=mysqli_query($conn,"SELECT id, product_status, user_id, name FROM products WHERE id=$id FOR UPDATE");
            $p=$q?mysqli_fetch_assoc($q):null;
            if(!$p) throw new Exception('Produk tidak ditemukan.');
            if(($p['product_status']??'')!=='approved'){
                mysqli_query($conn,"UPDATE products SET product_status='approved', verified_at=NOW(), badges=CONCAT(COALESCE(NULLIF(badges,''),'Eco Choice'), ', Barang Terverifikasi Admin') WHERE id=$id");
                add_notification($conn,(int)$p['user_id'],'Produk disetujui admin','Produk '.($p['name']??'').' sudah tampil di marketplace.','product');
                log_admin_action($conn,'approve_product','product',$id);
            }
            mysqli_commit($conn);
            break;
        case 'reject_product':
            mysqli_query($conn,"UPDATE products SET product_status='rejected' WHERE id=$id AND product_status<>'rejected'");
            log_admin_action($conn,'reject_product','product',$id);
            break;
        case 'verify_payment':
            mysqli_begin_transaction($conn);
            $payQ=mysqli_query($conn,"SELECT p.*, o.status AS order_status, o.seller_id, o.order_code FROM payments p JOIN orders o ON p.order_id=o.id WHERE p.id=$id FOR UPDATE");
            $pay=$payQ?mysqli_fetch_assoc($payQ):null;
            if(!$pay) throw new Exception('Pembayaran tidak ditemukan.');
            if(($pay['status']??'')!=='waiting_verification') throw new Exception('Pembayaran sudah diproses atau belum upload bukti.');
            if(!in_array($pay['order_status'], ['paid','pending_payment','waiting_payment'], true)) throw new Exception('Status order tidak valid untuk verifikasi.');
            $oldPaymentStatus = $pay['status'] ?? 'waiting_verification';
            $oldOrderStatus = $pay['order_status'] ?? 'pending_payment';
            mysqli_query($conn,"UPDATE payments SET status='verified', verified_at=NOW(), paid_at=COALESCE(paid_at,NOW()) WHERE id=$id AND status='waiting_verification'");
            mysqli_query($conn,"UPDATE orders SET status='paid_waiting_seller', payment_status='paid', paid_at=NOW(), updated_at=NOW() WHERE id=".(int)$pay['order_id']." AND status IN ('paid','pending_payment','waiting_payment')");
            revibe_payment_status_history($conn, $id, (int)$pay['order_id'], $oldPaymentStatus, 'paid', 'manual_admin', 'Admin verifikasi pembayaran manual');
            revibe_order_status_history($conn, (int)$pay['order_id'], $oldOrderStatus, 'paid_waiting_seller', $adminId, 'Payment manual verified by admin');
            revibe_create_pending_seller_balance($conn, (int)$pay['order_id']);
            revibe_notify_user_event($conn,(int)$pay['seller_id'],'order_paid','Order Baru Masuk','Pembayaran order '.($pay['order_code']??('#'.$pay['order_id'])).' sudah diterima ReVibe. Dana masih tertahan di escrow. Silakan proses barang.',['order_id'=>(int)$pay['order_id']]);
            revibe_audit_log($conn,'verify_payment','payment',$id,['order_id'=>(int)$pay['order_id']]);
            log_admin_action($conn,'verify_payment','payment',$id);
            mysqli_commit($conn);
            break;
        case 'resolve_complaint':
            mysqli_begin_transaction($conn);
            $c=mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM complaints WHERE id=$id FOR UPDATE"));
            if(!$c) throw new Exception('Komplain tidak ditemukan.');
            if(!in_array($c['status'],['open','review'],true)) throw new Exception('Komplain sudah diproses.');
            mysqli_query($conn,"UPDATE complaints SET status='resolved', resolved_at=NOW() WHERE id=$id");
            mysqli_query($conn,"UPDATE orders SET status='processing', updated_at=NOW() WHERE id=".(int)$c['order_id']);
            log_admin_action($conn,'resolve_complaint','complaint',$id);
            mysqli_commit($conn);
            break;
        case 'refund_complaint':
            mysqli_begin_transaction($conn);
            $c=mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM complaints WHERE id=$id FOR UPDATE"));
            if(!$c) throw new Exception('Komplain tidak ditemukan.');
            if(!in_array($c['status'],['open','review','resolved'],true)) throw new Exception('Komplain sudah diproses.');
            mysqli_query($conn,"UPDATE complaints SET status='refunded', resolved_at=NOW() WHERE id=$id");
            mysqli_query($conn,"UPDATE orders SET status='refund', updated_at=NOW() WHERE id=".(int)$c['order_id']);
            if(db_table_exists($conn,'payments')) mysqli_query($conn,"UPDATE payments SET status='refunded' WHERE order_id=".(int)$c['order_id']);
            log_admin_action($conn,'refund_complaint','complaint',$id);
            mysqli_commit($conn);
            break;
        case 'approve_withdrawal':
            mysqli_begin_transaction($conn);
            $wQ=mysqli_query($conn,"SELECT * FROM withdrawals WHERE id=$id FOR UPDATE");
            $w=$wQ?mysqli_fetch_assoc($wQ):null;
            if(!$w) throw new Exception('Withdrawal tidak ditemukan.');
            if(($w['status']??'')!=='pending') throw new Exception('Withdrawal sudah diproses sebelumnya.');
            $ref=mysqli_real_escape_string($conn, trim($_POST['transfer_reference']??''));
            $note=mysqli_real_escape_string($conn, trim($_POST['admin_note']??''));
            $sets=["status='approved'", "processed_at=NOW()"];
            if(db_column_exists($conn,'withdrawals','transfer_reference')) $sets[]="transfer_reference='$ref'";
            if(db_column_exists($conn,'withdrawals','admin_note')) $sets[]="admin_note='$note'";
            if(db_column_exists($conn,'withdrawals','processed_by')) $sets[]="processed_by=$adminId";
            mysqli_query($conn,"UPDATE withdrawals SET ".implode(',', $sets)." WHERE id=$id AND status='pending'");
            mysqli_query($conn,"UPDATE coin_transactions SET status='success' WHERE reference_type='withdrawal' AND reference_id=$id AND type='withdraw' AND status='pending'");
            get_coin_balance($conn,(int)$w['user_id']);
            add_notification($conn,(int)$w['user_id'],'Penukaran koin berhasil','Admin sudah transfer rupiah untuk '.number_format((int)$w['amount']).' koin.','withdrawal');
            log_admin_action($conn,'approve_withdrawal','withdrawal',$id, $ref ? 'Ref: '.$ref : 'Transfer disetujui');
            mysqli_commit($conn);
            break;
        case 'reject_withdrawal':
            mysqli_begin_transaction($conn);
            $wQ=mysqli_query($conn,"SELECT * FROM withdrawals WHERE id=$id FOR UPDATE");
            $w=$wQ?mysqli_fetch_assoc($wQ):null;
            if(!$w) throw new Exception('Withdrawal tidak ditemukan.');
            if(($w['status']??'')!=='pending') throw new Exception('Withdrawal sudah diproses sebelumnya.');
            $note=mysqli_real_escape_string($conn, trim($_POST['admin_note']??'Ditolak admin'));
            $sets=["status='rejected'", "processed_at=NOW()"];
            if(db_column_exists($conn,'withdrawals','admin_note')) $sets[]="admin_note='$note'";
            if(db_column_exists($conn,'withdrawals','processed_by')) $sets[]="processed_by=$adminId";
            mysqli_query($conn,"UPDATE withdrawals SET ".implode(',', $sets)." WHERE id=$id AND status='pending'");
            mysqli_query($conn,"UPDATE coin_transactions SET status='failed' WHERE reference_type='withdrawal' AND reference_id=$id AND type='withdraw' AND status='pending'");
            get_coin_balance($conn,(int)$w['user_id']);
            add_notification($conn,(int)$w['user_id'],'Penukaran koin ditolak','Koin kamu sudah dikembalikan ke saldo ReVibe.','withdrawal');
            log_admin_action($conn,'reject_withdrawal','withdrawal',$id,$note);
            mysqli_commit($conn);
            break;
        case 'grant_rank_reward':
            mysqli_begin_transaction($conn);
            $userId=(int)($_POST['user_id']??0); $amount=(int)($_POST['amount']??0); $rankPosition=(int)($_POST['rank_position']??0); $soldCount=(int)($_POST['sold_count']??0); $period=mysqli_real_escape_string($conn,$_POST['period']??date('Y-m'));
            if($userId<=0||$amount<=0||$rankPosition<=0) throw new Exception('Data reward tidak valid.');
            $stmt=mysqli_prepare($conn,"INSERT INTO rank_rewards (user_id, period, rank_position, sold_count, reward_amount, status, processed_at) VALUES (?, ?, ?, ?, ?, 'paid', NOW())");
            mysqli_stmt_bind_param($stmt,'isiii',$userId,$period,$rankPosition,$soldCount,$amount);
            if(!mysqli_stmt_execute($stmt)) throw new Exception('Reward periode ini sudah pernah diberikan atau gagal disimpan.');
            $rewardId=mysqli_insert_id($conn);
            revibe_coin_ledger_add($conn,$userId,'rank_reward',$amount,'Reward peringkat '.$rankPosition.' periode '.$period,'ranking',$rewardId,'success');
            add_notification($conn,$userId,'Reward peringkat masuk','Kamu menerima reward peringkat '.number_format($amount).' koin.','reward');
            log_admin_action($conn,'grant_rank_reward','ranking',$rewardId,'Periode '.$period.' user '.$userId);
            mysqli_commit($conn);
            break;
        case 'block_user':
            mysqli_query($conn,"UPDATE users SET status='blocked' WHERE id=$id AND role<>'admin'"); log_admin_action($conn,'block_user','user',$id); break;
        case 'unblock_user':
            mysqli_query($conn,"UPDATE users SET status='active' WHERE id=$id"); log_admin_action($conn,'unblock_user','user',$id); break;
        default: throw new Exception('Aksi admin tidak dikenal.');
    }
    $_SESSION['success']='Aksi berhasil diproses dengan validasi production.';
}catch(Throwable $e){
    if(mysqli_errno($conn)) @mysqli_rollback($conn);
    revibe_log('error','admin action failed',['action'=>$action,'id'=>$id,'error'=>$e->getMessage()]);
    $_SESSION['error']=revibe_is_debug() ? ('Aksi gagal: '.$e->getMessage()) : 'Aksi tidak dapat diproses. Jika masalah berlanjut, hubungi admin.';
}
header('Location: '.($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit;
