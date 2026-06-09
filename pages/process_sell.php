<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
revibe_require_verified_account($conn, 'verification_required.php');

function upload_revibe_image($file) {
    return revibe_safe_upload($file, 'products', ['prefix'=>'rv_product','max_size'=>4*1024*1024]);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verify_csrf();
    if (!revibe_rate_limit('upload_product', 10, 600)) {
        $_SESSION['error'] = 'Terlalu sering upload produk. Tunggu beberapa menit.';
        header('Location: sell.php'); exit;
    }
    $user_id = (int)$_SESSION['user_id'];
    $sellerUser = current_user($conn);
    $sellerLat = revibe_float_or_null($sellerUser['latitude'] ?? null);
    $sellerLng = revibe_float_or_null($sellerUser['longitude'] ?? null);
    $sellerAddress = revibe_user_full_address($sellerUser);

    if (!revibe_valid_coordinate($sellerLat, $sellerLng)) {
        $_SESSION['error'] = 'Sebelum jual barang, atur alamat dan titik lokasi toko di profil kamu dulu. Titik lokasi ini dipakai untuk ongkir otomatis.';
        header('Location: edit_profile.php#alamat');
        exit;
    }

    $name        = trim($_POST['name'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $price       = (int)($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $stock       = (int)($_POST['stock'] ?? 0);
    $weight_gram = max(1, min(30000, (int)($_POST['weight_gram'] ?? 1000)));

    $location    = revibe_user_region($sellerUser) ?: $sellerAddress;
    $condition   = trim($_POST['condition_status'] ?? 'Baik');
    $purchase_year = !empty($_POST['purchase_year']) ? (int)$_POST['purchase_year'] : null;
    $reason_sell = trim($_POST['reason_sell'] ?? '');
    $completeness = trim($_POST['completeness'] ?? '');
    $shipping_option = trim($_POST['shipping_option'] ?? 'shipping');

    if ($location === '') {
        $_SESSION['error'] = 'Alamat profil belum lengkap. Lengkapi alamat dan koordinat sebelum jual barang.';
        header('Location: edit_profile.php#alamat');
        exit;
    }

    $allowedConditions = ['Baru','Like New','Sangat Baik','Baik','Ada Minus Ringan','Perlu Perbaikan'];
    $allowedShipping = ['shipping','cod','both'];

    if ($name === '' || $category === '' || $price <= 0 || $stock <= 0 || $location === '' || !in_array($condition, $allowedConditions, true) || !in_array($shipping_option, $allowedShipping, true)) {
        $_SESSION['error'] = 'Data tidak lengkap atau harga/stok tidak valid!';
        header('Location: sell.php');
        exit;
    }

    if (empty($_FILES['images']['name'][0])) {
        $_SESSION['error'] = 'Minimal upload 1 foto produk.';
        header('Location: sell.php');
        exit;
    }

    ensure_seller_profile($conn, $user_id);

    $minus_photo = null;
    if (!empty($_FILES['minus_photo']['name'])) {
        $minus_photo = upload_revibe_image($_FILES['minus_photo']);
    }

    $columns = ['name','category','price','description','location','stock','user_id'];
    $placeholders = ['?','?','?','?','?','?','?'];
    $types = 'ssissii';
    $values = [$name,$category,$price,$description,$location,$stock,$user_id];

    $optional = [
        'condition_status' => [$condition, 's'],
        'purchase_year' => [$purchase_year, 'i'],
        'reason_sell' => [$reason_sell, 's'],
        'completeness' => [$completeness, 's'],
        'shipping_option' => [$shipping_option, 's'],
        'weight_gram' => [$weight_gram, 'i'],
        'product_status' => ['pending_review', 's'],
        'badges' => ['Eco Choice', 's'],
        'minus_photo' => [$minus_photo, 's'],
        'seller_latitude' => [$sellerLat, 'd'],
        'seller_longitude' => [$sellerLng, 'd'],
        'seller_address_snapshot' => [$sellerAddress, 's'],
    ];
    foreach($optional as $col => $pack){
        if(db_column_exists($conn,'products',$col)){
            $columns[] = $col;
            $placeholders[] = '?';
            $types .= $pack[1];
            $values[] = $pack[0];
        }
    }

    $sql = "INSERT INTO products (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', $placeholders) . ")";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $_SESSION['error'] = 'Gagal menyiapkan data produk. Silakan coba lagi.';
        header('Location: sell.php');
        exit;
    }
    mysqli_stmt_bind_param($stmt, $types, ...$values);

    if (mysqli_stmt_execute($stmt)) {
        $product_id = mysqli_insert_id($conn);
        $files = $_FILES['images'];
        $totalFiles = min(count($files['name']), 15);
        for ($i = 0; $i < $totalFiles; $i++) {
            $singleFile = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];
            $filename = upload_revibe_image($singleFile);
            if ($filename && db_table_exists($conn, 'product_images')) {
                $stmtImg = mysqli_prepare($conn, "INSERT INTO product_images (product_id, image) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmtImg, 'is', $product_id, $filename);
                mysqli_stmt_execute($stmtImg);
            }
        }

        add_notification($conn, $user_id, 'Produk berhasil dikirim', 'Produk kamu sedang menunggu validasi admin sebelum tampil di beranda.', 'product');
        $_SESSION['success'] = 'Barang berhasil diposting. Lokasi produk mengikuti titik koordinat profil kamu.';
        header('Location: seller_center.php');
        exit;
    }

    $_SESSION['error'] = 'Gagal memposting barang. Silakan coba lagi.';
    header('Location: sell.php');
    exit;
}

header('Location: sell.php');
exit;
?>
