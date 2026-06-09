<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

require_login('../index.php'); revibe_require_verified_account($conn, '../pages/verification_required.php'); $userId=(int)$_SESSION['user_id'];
if($_SERVER['REQUEST_METHOD']==='GET'){ $where=(($_SESSION['role']??'')==='admin' && isset($_GET['all']))?'1=1':"(user_id=$userId OR seller_id=$userId)"; $q=mysqli_query($conn,"SELECT * FROM seller_withdrawals WHERE $where ORDER BY id DESC LIMIT 50"); $rows=[]; while($q&&$r=mysqli_fetch_assoc($q)) $rows[]=$r; revibe_json_response(true,'Berhasil',['withdrawals'=>$rows,'available_balance'=>revibe_seller_available_balance($conn,$userId),'pending_balance'=>revibe_seller_pending_balance($conn,$userId)]); }
if($_SERVER['REQUEST_METHOD']==='POST'){ verify_csrf(); if(!revibe_rate_limit('withdrawal_request',5,3600)) revibe_json_response(false,'Terlalu banyak request withdrawal',[],'RATE_LIMITED',429); $amount=(int)($_POST['amount']??0); $method=trim($_POST['method']??''); $account=trim($_POST['account_number']??''); $name=trim($_POST['account_name']??''); try{ require_once __DIR__.'/../app/Services/WithdrawalService.php'; $id=(new WithdrawalService($conn))->request($userId,$amount,$method,$account,$name); revibe_json_response(true,'Withdrawal dibuat',['withdrawal_id'=>$id]); }catch(Throwable $e){ revibe_log('error','api withdrawal failed',['user_id'=>$userId,'error'=>$e->getMessage()]); revibe_json_response(false,revibe_is_debug()?$e->getMessage():'Withdrawal belum dapat diproses saat ini.',[],'WITHDRAWAL_FAILED',400); } }
revibe_json_response(false,'Method tidak didukung',[],'METHOD_NOT_ALLOWED',405);
