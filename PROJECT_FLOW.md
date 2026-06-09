# PROJECT_FLOW.md - Alur Sistem ReVibe Market

Dokumen ini menjelaskan alur sistem ReVibe Market setelah patch dokumentasi/hosting. Sistem tetap memakai PHP Native, MySQL/MariaDB, halaman PHP existing, dan database existing. Tidak ada redesign total dan tidak ada rewrite dari nol.

---

## 1. Entry aplikasi

1. User membuka `index.php`.
2. `index.php` memuat:
   - `config/session.php`
   - `config/db.php`
   - `config/functions.php`
3. Koneksi database dibaca dari `.env` melalui `config/env.php`.
4. Produk, navbar, cart count, chat count, profile popup, dan tampilan homepage dirender sebagai HTML oleh PHP.
5. Asset tetap berasal dari `assets/`, `uploads/`, dan helper path yang sudah ada.

---

## 2. Alur register dan login

1. User membuka popup login/register dari homepage.
2. Register diproses oleh `pages/register_process.php`.
3. Login diproses oleh `pages/login_process.php`.
4. Session disimpan lewat konfigurasi `config/session.php`.
5. Jika verifikasi email/phone diaktifkan, user diarahkan ke halaman verifikasi.
6. Role admin masuk ke admin panel, role user tetap bisa membeli dan menjual.

---

## 3. Alur buyer

1. Buyer mencari produk di homepage.
2. Buyer membuka detail produk di `pages/detail.php`.
3. Buyer dapat memasukkan produk ke wishlist atau cart.
4. Cart dikelola melalui `pages/cart.php`, `pages/add_to_cart.php`, `pages/update_cart.php`, dan `pages/remove_cart.php`.
5. Checkout dilakukan melalui `pages/checkout.php`.
6. Order dan payment record dibuat di database.
7. Buyer upload bukti pembayaran melalui `pages/payment_upload.php` atau form di halaman order/payment.
8. Bukti pembayaran masuk ke `storage/private/payment_proofs`, bukan public URL langsung.
9. Buyer memantau pesanan melalui `pages/buyer_orders.php`.
10. Setelah barang sampai, buyer konfirmasi di `pages/confirm_received.php`.
11. Buyer memberi review melalui `pages/review.php`.

---

## 4. Alur seller

1. User masuk ke `pages/seller_center.php`.
2. User upload produk melalui `pages/sell.php` dan `pages/process_sell.php`.
3. Produk menunggu validasi admin jika statusnya pending.
4. Seller dapat melihat order masuk di seller center.
5. Seller update status order melalui `pages/seller_order_update.php`.
6. Setelah order completed, sistem memproses settlement/ledger dan coin cashback.
7. Seller dapat melihat saldo di `pages/seller_balance.php`.
8. Seller dapat mengajukan withdrawal melalui `pages/withdraw.php`.

---

## 5. Alur admin

1. Admin login seperti user biasa, tetapi memiliki role `admin`.
2. Admin masuk ke `pages/admin/index.php`.
3. Admin panel dilindungi `config/admin_auth.php`.
4. Jika `ADMIN_2FA_REQUIRED=true`, admin harus melewati 2FA.
5. Admin dapat:
   - validasi produk,
   - validasi pembayaran,
   - kelola order,
   - kelola user,
   - kelola withdrawal,
   - kelola ranking,
   - melihat bukti pembayaran private,
   - melihat audit log.
6. Action admin diproses oleh `pages/admin/actions.php`.

---

## 6. Alur payment dan escrow

1. Buyer checkout dan memilih metode pembayaran.
2. Payment manual tetap tersedia untuk XAMPP/local.
3. Midtrans/Xendit ready melalui konfigurasi `.env` dan endpoint webhook di `api/`.
4. Bukti pembayaran divalidasi admin.
5. Order masuk ke status berbayar/menunggu seller.
6. Dana ditahan dalam konsep escrow sampai order selesai.
7. Jika ada complaint, settlement dapat ditahan.
8. Jika order completed, ledger seller balance dan coin cashback diproses.

---

## 7. Alur coin cashback 8%

1. Seller mendapat coin cashback setelah order completed.
2. Nilai cashback dikontrol oleh `.env`:

```env
SELLER_COIN_CASHBACK_ENABLED=true
SELLER_COIN_CASHBACK_TYPE=percentage
SELLER_COIN_CASHBACK_PERCENT=8
```

3. Ledger coin dicatat di `coin_transactions` dan tabel terkait jika tersedia.
4. Saldo coin disinkronkan lewat helper `get_coin_balance()`.
5. Withdrawal coin diproses melalui request withdrawal dan validasi admin.

---

## 8. Alur chat

1. Buyer memulai chat dengan seller melalui halaman produk atau order.
2. Chat diproses lewat halaman `pages/chat.php`, `pages/messages.php`, dan `pages/start_chat.php`.
3. API chat juga tersedia di `api/chat.php` jika dibutuhkan untuk integrasi.
4. Jumlah unread chat ditampilkan di navbar jika user login.

---

## 9. Alur complaint/dispute

1. Buyer membuka complaint melalui `pages/complaint.php`.
2. Complaint terhubung ke order, buyer, seller, alasan, detail, dan evidence.
3. Evidence disimpan private di `storage/private/complaints`.
4. Admin melihat complaint terbuka di admin panel.
5. Admin dapat resolve complaint atau refund sesuai action yang tersedia.
6. Akses file private melewati `pages/admin/view_file.php` dan policy di `app/Policies/FilePolicy.php`.

---

## 10. Alur private/public storage

### Public

Digunakan untuk file yang boleh tampil langsung:

- gambar produk,
- foto profile,
- asset gambar/video.

Folder utama:

```text
uploads/products/
uploads/profile/
assets/
```

### Private

Digunakan untuk file sensitif:

- bukti pembayaran,
- bukti complaint,
- dokumen KYC jika nanti dipakai.

Folder utama:

```text
storage/private/payment_proofs/
storage/private/complaints/
```

File private tidak boleh dibuka langsung dari URL folder. File dibuka lewat controller yang mengecek session dan policy.

---

## 11. Alur queue/notification

1. Notifikasi in-app, email, atau WhatsApp dapat dimasukkan ke queue.
2. Local default memakai sync/file.
3. Production dapat memakai Redis/database queue.
4. Worker dijalankan lewat:

```bash
php scripts/queue_worker.php
```

5. Cron utama:

```bash
php scripts/cron.php
```

---

## 12. Alur monitoring

- `health.php`: mengecek aplikasi hidup.
- `readiness.php`: mengecek database, folder writable, storage, cache, queue, dan env.
- `scripts/deploy_check.php`: mengecek kesiapan deployment.
- `scripts/permissions_check.php`: mengecek permission folder.
- `scripts/alert_test.php`: test alerting jika dikonfigurasi.

---

## 13. Prinsip patch lanjutan

- Jangan redesign total.
- Jangan rewrite sistem dari nol.
- Jangan menghapus migration lama.
- Jangan memutus database.
- Jangan membuat HTML statis yang tidak terhubung database.
- Jika butuh tambahan struktur database, buat migration baru yang additive.
- Pertahankan flow buyer, seller, admin, payment, escrow, chat, complaint, coin, dan dashboard.
