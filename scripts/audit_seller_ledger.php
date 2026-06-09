<?php

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
$fix = in_array('--fix', $argv ?? [], true);
$result = ['success'=>true,'fix_mode'=>$fix,'mismatches'=>[]];
if (!db_table_exists($conn,'seller_balances') || !db_table_exists($conn,'seller_ledger')) {
    echo json_encode(['success'=>false,'message'=>'Tabel seller_balances/seller_ledger belum ada.'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL; exit(1);
}
$q = mysqli_query($conn, "SELECT DISTINCT seller_id FROM seller_balances WHERE seller_id IS NOT NULL UNION SELECT DISTINCT seller_id FROM seller_ledger");
while ($q && ($s = mysqli_fetch_assoc($q))) {
    $sellerId = (int)$s['seller_id']; if ($sellerId <= 0) continue;
    $lastAvail = null; $lastPending = null;
    $l = mysqli_query($conn, "SELECT balance_type,balance_after FROM seller_ledger WHERE seller_id={$sellerId} ORDER BY id ASC");
    while ($l && ($row=mysqli_fetch_assoc($l))) {
        if (($row['balance_type'] ?? 'available') === 'pending') $lastPending = (int)$row['balance_after'];
        else $lastAvail = (int)$row['balance_after'];
    }
    $bq = mysqli_query($conn, "SELECT pending_balance,available_balance FROM seller_balances WHERE seller_id={$sellerId} OR user_id={$sellerId} LIMIT 1");
    $b = $bq ? mysqli_fetch_assoc($bq) : null;
    $actualAvail = (int)($b['available_balance'] ?? 0); $actualPending = (int)($b['pending_balance'] ?? 0);
    $expectedAvail = $lastAvail ?? $actualAvail; $expectedPending = $lastPending ?? $actualPending;
    if ($expectedAvail !== $actualAvail || $expectedPending !== $actualPending) {
        $result['mismatches'][] = ['seller_id'=>$sellerId,'expected_available'=>$expectedAvail,'actual_available'=>$actualAvail,'expected_pending'=>$expectedPending,'actual_pending'=>$actualPending];
        if ($fix) mysqli_query($conn, "UPDATE seller_balances SET available_balance={$expectedAvail}, pending_balance={$expectedPending}, balance={$expectedAvail}, updated_at=NOW() WHERE seller_id={$sellerId} OR user_id={$sellerId} LIMIT 1");
    }
}
$result['mismatch_count'] = count($result['mismatches']);
$result['success'] = $result['mismatch_count'] === 0 || $fix;
if ($result['mismatch_count'] > 0 && class_exists('ErrorTrackingService')) (new ErrorTrackingService())->alert('seller_ledger_mismatch', $result, 'warning');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['success'] ? 0 : 2);
