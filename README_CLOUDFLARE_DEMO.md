# ReVibeMarket Cloudflare Demo

ReVibeMarket tetap berjalan sebagai proyek PHP + MySQL. Cloudflare yang dipakai untuk demo adalah Cloudflare Tunnel, bukan Cloudflare Pages statis. Payment masih manual demo dan semua transaksi uang asli belum aktif.

Catatan utama: Demo saja, jangan transfer uang asli.

## Akun Demo

Admin: admin@revibe.local / Admin12345
Buyer: buyer@revibe.local / Buyer12345
Seller: seller@revibe.local / Seller12345

## Konfigurasi Biaya Demo

Biaya Layanan ReVibe 12% dari harga barang.
Cashback seller 6% dari harga barang.
Estimasi margin platform demo 6% dari harga barang.
Total bayar buyer = harga barang + ongkir + Biaya Layanan ReVibe 12%.
Biaya layanan ini adalah simulasi fee platform untuk demo.

## Langkah Install Lokal XAMPP

1. Install dan jalankan XAMPP.
2. Aktifkan Apache dan MySQL.
3. Copy folder revibemarket ke htdocs.
4. Buka phpMyAdmin.
5. Buat database baru bernama revibemarket.
6. Import file database/revibemarket_cloudflare_demo.sql.
7. Copy .env.cloudflare-demo.example menjadi .env.
8. Sesuaikan DB_NAME, DB_USER, dan DB_PASS.
9. Jalankan website dari http://localhost/revibemarket.
10. Test login admin, buyer, dan seller.

## Langkah Cloudflare Tunnel Public Demo

1. Install cloudflared di komputer.
2. Pastikan website lokal sudah berjalan di XAMPP.
3. Jalankan perintah: cloudflared tunnel --url http://localhost/revibemarket
4. Copy URL trycloudflare.com yang muncul.
5. Ubah APP_URL di .env sesuai URL Cloudflare Tunnel.
6. Buka public link dari HP atau laptop lain.
7. Test public preview dan alur transaksi lengkap.

## Checklist Public Preview

Home page tampil.
Produk tampil.
Detail produk tampil.
Register dan login berjalan.
Asset CSS, JS, gambar, logo, video, dan icon tampil normal.
Tidak ada error PHP mentah.
Tidak ada path localhost yang bocor di tampilan public.
APP_DEBUG false.
Database terhubung.
Folder uploads writable.
File assets/images/default.png tersedia.
Payment manual memakai rekening demo.
Catatan Demo saja, jangan transfer uang asli tampil di halaman pembayaran.

## Checklist Alur Buyer Admin Seller

Seller login.
Seller posting produk.
Produk masuk status menunggu ACC admin.
Admin login.
Admin ACC produk.
Produk muncul di halaman publik.
Buyer login atau register.
Buyer buka detail produk.
Produk baru tidak punya ulasan palsu.
Buyer checkout.
Checkout menampilkan harga barang, ongkir, Biaya Layanan ReVibe 12%, total bayar, cashback seller 6%, dan margin demo 6%.
Buyer upload bukti pembayaran gambar JPG, PNG, atau WEBP.
Admin melihat pembayaran masuk.
Admin verifikasi pembayaran.
Status order menjadi paid_waiting_seller.
Seller melihat order masuk.
Seller update paid_waiting_seller ke processing.
Seller update processing ke shipped.
Buyer melihat status shipped.
Buyer konfirmasi barang sampai.
Status menjadi completed.
Buyer memberi review setelah order selesai.
Review tampil sebagai ulasan asli di detail produk.
Rating dan review_count produk berubah sesuai review asli.
Admin report menampilkan service fee 12%, cashback 6%, dan margin demo 6%.

## Health dan Readiness

Buka health.php untuk cek cepat PHP dan database.
Buka readiness.php?mode=cloudflare-demo untuk cek kesiapan demo Cloudflare Tunnel.

## Batasan Demo

Jangan pakai uang asli.
Jangan pakai rekening asli.
Jangan pakai QR pembayaran asli.
Payment gateway asli seperti Midtrans, Xendit, Stripe, PayPal belum diaktifkan.
Cloudflare Tunnel hanya membuka public link ke server lokal atau hosting PHP yang sedang berjalan.
