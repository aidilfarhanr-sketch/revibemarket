<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
$user_id=(int)$_SESSION['user_id'];
$user=current_user($conn);
$profileLocation = revibe_user_region($user) ?: revibe_user_full_address($user);
$profileAddress = revibe_user_full_address($user);
$profileLat = revibe_float_or_null($user['latitude'] ?? null);
$profileLng = revibe_float_or_null($user['longitude'] ?? null);
$id=(int)($_GET['id'] ?? $_POST['id'] ?? 0);
$q=mysqli_query($conn,"SELECT * FROM products WHERE id=$id AND user_id=$user_id LIMIT 1");
$p=$q?mysqli_fetch_assoc($q):null;
if(!$p){ $_SESSION['error']='Produk tidak ditemukan.'; header('Location: seller_center.php'); exit; }

function edit_upload_revibe_image($file) {
    return revibe_safe_upload($file, 'products', ['prefix'=>'rv_product','max_size'=>4*1024*1024]);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $name=trim($_POST['name']??'');
    $price=(int)($_POST['price']??0);
    $stock=(int)($_POST['stock']??0);
    $weight_gram=max(1,min(30000,(int)($_POST['weight_gram']??($p['weight_gram']??1000))));
    $desc=trim($_POST['description']??'');
    $location=$profileLocation;
    $condition=trim($_POST['condition_status']??'Baik');
    $shipping=trim($_POST['shipping_option']??'shipping');
    $complete=trim($_POST['completeness']??'');
    $reason=trim($_POST['reason_sell']??'');
    if($name===''||$price<=0||$stock<0||$location===''){
        $_SESSION['error']='Data produk belum valid atau alamat profil belum lengkap.'; header('Location: edit_product.php?id='.$id); exit;
    }
    if(!revibe_valid_coordinate($profileLat,$profileLng)){
        $_SESSION['error']='Lengkapi titik koordinat profil sebelum edit produk.'; header('Location: edit_profile.php#alamat'); exit;
    }
    $sets = ['name=?','price=?','stock=?','description=?','location=?','condition_status=?','shipping_option=?','completeness=?','reason_sell=?','product_status=\'pending_review\''];
    $types = 'siissssss';
    $vals = [$name,$price,$stock,$desc,$location,$condition,$shipping,$complete,$reason];
    if(db_column_exists($conn,'products','seller_latitude')){ $sets[]='seller_latitude=?'; $types.='d'; $vals[]=$profileLat; }
    if(db_column_exists($conn,'products','seller_longitude')){ $sets[]='seller_longitude=?'; $types.='d'; $vals[]=$profileLng; }
    if(db_column_exists($conn,'products','seller_address_snapshot')){ $sets[]='seller_address_snapshot=?'; $types.='s'; $vals[]=$profileAddress; }
    $sql = 'UPDATE products SET '.implode(', ', $sets).' WHERE id=? AND user_id=?';
    $types .= 'ii'; $vals[]=$id; $vals[]=$user_id;
    $stmt=mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,$types,...$vals);
    mysqli_stmt_execute($stmt);

    if(db_table_exists($conn,'product_images') && !empty($_POST['delete_images']) && is_array($_POST['delete_images'])){
        foreach($_POST['delete_images'] as $imgId){
            $imgId=(int)$imgId;
            $iq=mysqli_query($conn,"SELECT image FROM product_images WHERE id=$imgId AND product_id=$id LIMIT 1");
            if($iq && $img=mysqli_fetch_assoc($iq)){
                mysqli_query($conn,"DELETE FROM product_images WHERE id=$imgId AND product_id=$id");
                $filename = basename($img['image']);
                try { (new StorageService($conn))->delete('products/' . $filename); } catch (Throwable $e) { revibe_log('warning','product image remote delete failed',['file'=>$filename,'error'=>$e->getMessage()]); }
                foreach(['../uploads/products/','../assets/images/'] as $dir){
                    $file=$dir.$filename;
                    if(is_file($file)) @unlink($file);
                }
            }
        }
    }

    if(db_table_exists($conn,'product_images') && !empty($_FILES['images']['name'][0])){
        $files=$_FILES['images'];
        $totalFiles=min(count($files['name']), 15);
        for($i=0;$i<$totalFiles;$i++){
            $singleFile=['name'=>$files['name'][$i],'type'=>$files['type'][$i],'tmp_name'=>$files['tmp_name'][$i],'error'=>$files['error'][$i],'size'=>$files['size'][$i]];
            $filename=edit_upload_revibe_image($singleFile);
            if($filename){
                $stmtImg=mysqli_prepare($conn,"INSERT INTO product_images (product_id, image) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmtImg,'is',$id,$filename);
                mysqli_stmt_execute($stmtImg);
            }
        }
    }

    $countQ=mysqli_query($conn,"SELECT COUNT(*) total FROM product_images WHERE product_id=$id");
    if($countQ && (int)(mysqli_fetch_assoc($countQ)['total'] ?? 0)===0){
        $_SESSION['error']='Produk harus punya minimal 1 foto. Silakan tambah foto baru.'; header('Location: edit_product.php?id='.$id); exit;
    }
    $_SESSION['success']='Produk diperbarui, foto berhasil dikelola, dan menunggu validasi ulang admin.';
    header('Location: seller_center.php'); exit;
}
$images = db_table_exists($conn,'product_images') ? mysqli_query($conn,"SELECT * FROM product_images WHERE product_id=$id ORDER BY id ASC") : null;
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Edit Produk - ReVibe</title><link rel="stylesheet" href="../assets/css/style.css"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="stylesheet" href="../assets/css/loader.css?v=25">
</head><body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>
<div class="navbar"><a href="seller_center.php" class="btn">← Seller Center</a></div>
<div class="page-shell narrow"><div class="page-header"><h1>Edit Produk</h1><p><?= e($p['name']) ?></p></div><?php if(isset($_SESSION['error'])): ?><div class="alert error"><?= e($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
<form method="POST" enctype="multipart/form-data" class="form-card product-edit-v9"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
<label>Foto Produk Saat Ini</label><div class="edit-photo-grid">
<?php if($images && mysqli_num_rows($images)>0): while($img=mysqli_fetch_assoc($images)): ?>
    <label class="edit-photo-card"><img src="<?= e(revibe_public_file_url($img['image'], 'products')) ?>" alt="Foto produk"><span><input type="checkbox" name="delete_images[]" value="<?= (int)$img['id'] ?>"> Hapus foto ini</span></label>
<?php endwhile; else: ?><p class="muted">Belum ada foto produk.</p><?php endif; ?>
</div>
<label>Tambah Foto Baru <small>(boleh banyak foto, maksimal 4MB per foto)</small></label><input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp">
<label>Nama Produk</label><input name="name" value="<?= e($p['name']) ?>" required><label>Harga</label><input type="number" name="price" value="<?= (int)$p['price'] ?>" required><label>Stok</label><input type="number" name="stock" value="<?= (int)$p['stock'] ?>" required><label>Berat Paket (gram)</label><input type="number" name="weight_gram" min="1" max="30000" value="<?= (int)($p['weight_gram'] ?? 1000) ?>" required>
<label>Kondisi</label><select name="condition_status"><?php foreach(['Baru','Like New','Sangat Baik','Baik','Ada Minus Ringan','Perlu Perbaikan'] as $c): ?><option value="<?= e($c) ?>" <?= (($p['condition_status']??'')===$c?'selected':'') ?>><?= e($c) ?></option><?php endforeach; ?></select>
<label>Metode</label><select name="shipping_option"><option value="shipping" <?= (($p['shipping_option']??'')==='shipping'?'selected':'') ?>>Kirim</option><option value="cod" <?= (($p['shipping_option']??'')==='cod'?'selected':'') ?>>COD</option><option value="both" <?= (($p['shipping_option']??'')==='both'?'selected':'') ?>>COD & Kirim</option></select>
<label>Lokasi Produk</label><div class="locked-location-box"><strong><span class="revibe-coord-name" data-lat="<?= e($profileLat ?? '') ?>" data-lng="<?= e($profileLng ?? '') ?>" data-fallback="<?= e($profileLocation ?: $profileAddress ?: $p['location'] ?: 'Titik lokasi belum dipilih') ?>"><?= e($profileLocation ?: $p['location'] ?: 'Mencari nama lokasi...') ?></span></strong><span><?= e($profileAddress ?: 'Alamat profil belum lengkap') ?></span><small>Lokasi produk dikunci dari alamat profil. Ubah alamat lewat Edit Profil.</small></div><label>Kelengkapan</label><input name="completeness" value="<?= e($p['completeness']??'') ?>"><label>Alasan Dijual</label><textarea name="reason_sell" rows="3"><?= e($p['reason_sell']??'') ?></textarea><label>Deskripsi</label><textarea name="description" rows="5"><?= e($p['description']) ?></textarea><button class="btn primary full" type="submit">Simpan Perubahan</button></form></div><script src="../assets/js/revibe-location.js"></script><?php render_revibe_floating_nav($conn); ?>
<script defer src="../assets/js/loader.js?v=25"></script>
</body></html>
