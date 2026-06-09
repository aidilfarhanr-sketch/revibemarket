<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
revibe_require_verified_account($conn, 'verification_required.php');
$user_id=(int)$_SESSION['user_id'];
$user=current_user($conn);
$storeAddress = revibe_user_full_address($user);
$storeRegion = revibe_user_region($user);
$lat = $user['latitude'] ?? '';
$lng = $user['longitude'] ?? '';
$hasCoord = revibe_valid_coordinate($lat,$lng);
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8">
    <title>Jual Barang - ReVibe Market</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../assets/css/loader.css?v=25">
</head>
<body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>

<div class="navbar">
    <a href="../index.php" class="btn">← Kembali</a>
    <a href="seller_center.php" class="btn">Seller Center</a>
</div>

<div class="sell-container extended-form sell-v6-container">
    <h2>Jual Barang Anda</h2>
    <p style="color:#555; margin-bottom:20px;">Barang bekas berkualitas dengan cerita baru. Lokasi produk otomatis mengikuti titik koordinat alamat/toko di profil kamu.</p>

    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert error"><?= e($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="seller-location-card <?= $hasCoord ? 'ready' : 'missing' ?>">
        <div>
            <span class="eyebrow">Lokasi Produk Otomatis</span>
            <h3><?= $hasCoord ? 'Alamat toko sudah siap dipakai' : 'Lengkapi titik koordinat dulu' ?></h3>
            <p><?= e($storeAddress ?: 'Alamat belum diatur') ?></p>
            <small>Titik lokasi: <span class="revibe-coord-name" data-lat="<?= e($lat) ?>" data-lng="<?= e($lng) ?>" data-fallback="<?= e($storeRegion ?: $storeAddress ?: 'Titik lokasi belum dipilih') ?>"><?= e($storeRegion ?: 'Mencari nama lokasi...') ?></span></small>
        </div>
        <a class="btn <?= $hasCoord ? 'secondary' : 'primary' ?>" href="edit_profile.php#alamat">Atur Alamat</a>
    </div>

    <form action="process_sell.php" method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <label>Foto Produk <small>(Minimal 1 foto, bebas banyak foto, maksimal 4MB per foto)</small></label>
        <input type="file" name="images[]" id="images" multiple accept="image/jpeg,image/png,image/webp" required>
        <div id="preview" class="sell-photo-preview-grid" aria-live="polite"></div>

        <label>Nama Produk</label>
        <input type="text" name="name" maxlength="255" required>

        <label>Kategori</label>
        <select name="category" required>
            <option value="pakaian">Pakaian</option>
            <option value="aksesoris">Aksesoris</option>
            <option value="pajangan">Pajangan</option>
            <option value="tanaman">Tanaman</option>
        </select>

        <label>Kondisi Barang</label>
        <select name="condition_status" required>
            <option value="Baru">Baru</option>
            <option value="Like New">Like New</option>
            <option value="Sangat Baik">Sangat Baik</option>
            <option value="Baik">Baik</option>
            <option value="Ada Minus Ringan">Ada Minus Ringan</option>
            <option value="Perlu Perbaikan">Perlu Perbaikan</option>
        </select>

        <label>Foto Minus / Cacat <small>(opsional, wajib kalau ada minus)</small></label>
        <input type="file" name="minus_photo" accept="image/jpeg,image/png,image/webp">

        <label>Tahun Pembelian</label>
        <input type="number" name="purchase_year" min="1990" max="<?= date('Y') ?>" placeholder="Contoh: 2023">

        <label>Kelengkapan Barang</label>
        <input type="text" name="completeness" placeholder="Contoh: box, dustbag, tag, charger, manual book">

        <label>Alasan Dijual</label>
        <textarea name="reason_sell" rows="3" placeholder="Contoh: ukuran tidak cocok, jarang dipakai, decluttering"></textarea>

        <label>Harga (Rp)</label>
        <input type="number" name="price" min="1000" required>

        <label>Deskripsi</label>
        <textarea name="description" rows="4" placeholder="Jelaskan kondisi asli, ukuran, bahan, minus, dan cerita produknya."></textarea>

        <label>Stok</label>
        <input type="number" name="stock" min="1" required>

        <label>Berat Paket <small>(gram, dipakai untuk estimasi ongkir JNE/J&T/SiCepat)</small></label>
        <input type="number" name="weight_gram" min="1" max="30000" value="1000" required>

        <label>Lokasi Barang</label>
        <div class="locked-location-box">
            <strong><?= e($storeRegion ?: 'Alamat profil belum lengkap') ?></strong>
            <span><?= e($storeAddress ?: 'Lengkapi alamat di profil agar barang bisa diposting.') ?></span>
            <small>Lokasi dikunci otomatis dari profil. User tidak bisa mengubah tag lokasi secara manual.</small>
        </div>
        <input type="hidden" name="location" value="<?= e($storeRegion ?: $storeAddress) ?>">

        <label>Metode Transaksi</label>
        <select name="shipping_option" required>
            <option value="shipping">Kirim saja</option>
            <option value="cod">COD saja</option>
            <option value="both">COD dan kirim</option>
        </select>

        <div class="info-box">
            Produk baru akan berstatus <strong>Menunggu Validasi Admin</strong>. Titik koordinat penjual disimpan otomatis dari profil kamu saat produk diposting.
        </div>

        <div class="form-action">
            <button type="submit" class="btn primary" <?= $hasCoord ? '' : 'onclick="return confirm(\'Titik lokasi belum lengkap. Kamu akan diarahkan untuk mengatur alamat dulu.\')"' ?>>Posting Barang</button>
            <a href="../index.php" class="btn secondary">Batal</a>
        </div>
    </form>
</div>

<script>
const imageInput = document.getElementById("images");
const preview = document.getElementById("preview");
let selectedProductFiles = [];

function syncSelectedProductFiles() {
    const transfer = new DataTransfer();
    selectedProductFiles.forEach(file => transfer.items.add(file));
    imageInput.files = transfer.files;
}

function renderSelectedProductFiles() {
    preview.innerHTML = "";
    if (selectedProductFiles.length === 0) {
        const empty = document.createElement("p");
        empty.className = "muted sell-preview-empty";
        empty.textContent = "Belum ada foto dipilih.";
        preview.appendChild(empty);
        return;
    }
    selectedProductFiles.forEach((file, index) => {
        const card = document.createElement("div");
        card.className = "sell-photo-preview-card";
        const img = document.createElement("img");
        img.alt = "Preview foto produk";
        const caption = document.createElement("small");
        caption.textContent = file.name.length > 24 ? file.name.slice(0, 21) + "..." : file.name;
        img.className = "sell-photo-preview-image";
        img.title = "Klik untuk lihat foto";
        img.addEventListener("click", function() {
            if (!img.src) return;
            const lightbox = document.getElementById("sellPhotoLightbox");
            const lightboxImg = document.getElementById("sellPhotoLightboxImg");
            if (!lightbox || !lightboxImg) return;
            lightboxImg.src = img.src;
            lightbox.style.display = "flex";
            lightbox.setAttribute("aria-hidden", "false");
            document.body.classList.add("rv-modal-open");
        });
        const removeBtn = document.createElement("button");
        removeBtn.type = "button";
        removeBtn.className = "sell-photo-remove-btn";
        removeBtn.innerHTML = "&times;";
        removeBtn.setAttribute("aria-label", "Hapus foto " + (index + 1));
        removeBtn.title = "Hapus foto";
        removeBtn.addEventListener("click", function() {
            selectedProductFiles.splice(index, 1);
            syncSelectedProductFiles();
            renderSelectedProductFiles();
        });
        card.appendChild(img);
        card.appendChild(removeBtn);
        card.appendChild(caption);
        preview.appendChild(card);
        const reader = new FileReader();
        reader.onload = function(e) { img.src = e.target.result; };
        reader.readAsDataURL(file);
    });
}

imageInput.addEventListener("change", function() {
    const incoming = Array.from(this.files || []);
    for (const file of incoming) {
        if (!file.type.startsWith("image/")) continue;
        if (file.size > 4 * 1024 * 1024) {
            alert("Ukuran setiap gambar maksimal 4MB.");
            this.value = "";
            selectedProductFiles = [];
            renderSelectedProductFiles();
            return;
        }
        selectedProductFiles.push(file);
    }
    selectedProductFiles = selectedProductFiles.slice(0, 15);
    syncSelectedProductFiles();
    renderSelectedProductFiles();
});

renderSelectedProductFiles();
</script>
<div id="sellPhotoLightbox" class="sell-photo-lightbox" aria-hidden="true" onclick="closeSellPhotoLightbox(event)">
    <button type="button" class="sell-photo-lightbox-close" aria-label="Tutup preview foto" onclick="closeSellPhotoLightbox(event)">&times;</button>
    <img id="sellPhotoLightboxImg" alt="Preview foto produk yang dipilih">
</div>
<script>
function closeSellPhotoLightbox(event) {
    const lightbox = document.getElementById("sellPhotoLightbox");
    const lightboxImg = document.getElementById("sellPhotoLightboxImg");
    if (!lightbox) return;
    if (event && event.target && event.target.id !== "sellPhotoLightbox" && !event.target.classList.contains("sell-photo-lightbox-close")) return;
    lightbox.style.display = "none";
    lightbox.setAttribute("aria-hidden", "true");
    if (lightboxImg) lightboxImg.src = "";
    document.body.classList.remove("rv-modal-open");
}

document.addEventListener("keydown", function(event) {
    if (event.key === "Escape") closeSellPhotoLightbox();
});
</script>

<script src="../assets/js/revibe-location.js"></script>
<?php render_revibe_floating_nav($conn); ?>
<script defer src="../assets/js/loader.js?v=25"></script>
</body>
</html>
