# CHANGELOG ReVibeMarket Cloudflare Demo 100

Versi ini fokus pada mobile responsive, public preview Cloudflare Tunnel, dan demo transaksi buyer ke admin ke seller.

File utama yang diubah:
config/functions.php
config/db.php
pages/checkout.php
pages/payment.php
pages/payment_upload.php
pages/buyer_orders.php
pages/invoice.php
pages/confirm_received.php
pages/detail.php
pages/seller_center.php
pages/seller_order_update.php
pages/seller_balance.php
pages/admin/orders.php
pages/admin/reports.php
pages/admin/rankings.php
assets/css/style.css
health.php
readiness.php
.env
.env.example
.env.cloudflare-demo.example
database/revibemarket_cloudflare_demo.sql
README_CLOUDFLARE_DEMO.md
assets/images/default.png
uploads/.htaccess

Bug yang diperbaiki:
Status paid_waiting_seller sekarang masuk flow seller.
Seller bisa lanjut paid_waiting_seller ke processing, lalu processing ke shipped, lalu shipped ke delivered.
Seller tidak bisa update order yang masih pending_payment untuk pembayaran non-COD.
Seller tidak bisa melompati urutan status.
Seller tidak bisa update order milik seller lain.
Tombol update seller tidak muncul pada status yang membingungkan.
Upload bukti bayar hanya menerima JPG, JPEG, PNG, dan WEBP.
Buyer tidak melihat tombol upload ulang ketika bukti bayar menunggu verifikasi.
Produk baru tidak lagi menampilkan dummy review.
Ranking reward admin memakai user_id sehingga tombol Beri Hadiah berjalan.
Withdrawal seller menghitung saldo tersedia setelah dikurangi pending withdrawal.
Error database public demo dibuat lebih ramah.

Perbaikan mobile responsive:
Overflow horizontal dicegah secara global.
Navbar dibuat lebih aman di layar kecil.
Card produk dipaksa rapi di HP 360px, 390px, 412px, dan 430px.
Detail produk, checkout, order, chat, tabel admin, seller center, buyer orders, form, modal, dan map dibuat lebih nyaman di touch device.
Tabel panjang memakai wrapper scroll horizontal.
Floating button ditata agar tidak menutupi tombol penting.
Input, select, textarea, dan button dibuat touch friendly.

Perbaikan public preview:
APP_DEBUG diarahkan false untuk demo public.
Payment manual memakai BANK DEMO REVIBE, 0000000000, dan REVIBE DEMO.
Catatan Demo saja, jangan transfer uang asli ditampilkan di alur payment.
Nomor WhatsApp publik memakai nilai dari env.
Fallback assets/images/default.png ditambahkan.
health.php dan readiness.php?mode=cloudflare-demo dibuat untuk cek PHP, database, env, uploads, tabel penting, service fee, cashback, payment mode, dummy review, dan default.png.

Perbaikan demo transaksi:
Seller posting produk dan menunggu ACC admin.
Admin ACC produk.
Buyer checkout.
Buyer upload bukti bayar manual.
Admin verifikasi pembayaran.
Status berubah menjadi paid_waiting_seller.
Seller proses order.
Buyer konfirmasi barang sampai.
Buyer memberi review asli setelah order selesai.
Admin report menampilkan ringkasan transaksi demo.

Perubahan biaya:
Biaya layanan berubah dari 10% menjadi 12%.
Cashback seller berubah dari 8% menjadi 6%.
Margin platform demo menjadi 6%.
Label yang dipakai adalah Biaya Layanan ReVibe 12%.
Istilah pajak pemerintah tidak digunakan.

Cara testing singkat:
Import database/revibemarket_cloudflare_demo.sql ke database revibemarket.
Copy .env.cloudflare-demo.example menjadi .env.
Buka localhost/revibemarket.
Login seller, posting produk.
Login admin, ACC produk.
Login buyer, checkout produk.
Pastikan total bayar benar.
Upload bukti bayar gambar.
Login admin, verifikasi pembayaran.
Login seller, update status hingga shipped.
Login buyer, konfirmasi barang sampai dan beri review.
Buka admin reports dan readiness.php?mode=cloudflare-demo.
