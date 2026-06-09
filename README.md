# ReVibe Market — Hosting 100% Multi-Server Ready

ReVibe Market adalah marketplace untuk produk preloved/upcycle dengan alur buyer, seller, admin, escrow/manual payment, cashback coin seller, chat, review, komplain, withdrawal, audit log, healthcheck, readiness check, worker, cron, dan dokumentasi deployment production.

Patch ini **tidak redesign tampilan** dan **tidak mengubah konsep utama**. Fokusnya hanya membuat proyek lebih siap hosting production, VPS, Docker, cloud, dan multi-server.

## Struktur penting

- `index.php`, `pages/`, `assets/` — frontend dan halaman marketplace.
- `api/` — endpoint API/payment/webhook.
- `app/Services/` — service layer storage, Redis, cache, queue, payment, escrow, ledger, alerting.
- `config/` — konfigurasi env, database, session, security, error handler.
- `database/migrations/` — migration berurutan dan aman dijalankan ulang lewat `scripts/run_migrations.php`.
- `scripts/` — migration, deploy check, permission check, backup/restore, cron, worker.
- `deploy/` — contoh konfigurasi Nginx, Apache, Supervisor, dan multi-server.
- `docs/` — panduan hosting production yang terarah.

## Cara jalan Local/XAMPP

1. Copy `.env.example` menjadi `.env`.
2. Pakai mode local: `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://localhost/revibe`.
3. Buat database `revibe_market` di phpMyAdmin.
4. Jalankan migration: `php scripts/run_migrations.php`.
5. Buka `http://localhost/revibe`.

## Shared hosting PHP/MySQL

1. Upload isi folder ke `public_html` atau subfolder hosting.
2. Buat database MySQL dari panel hosting.
3. Isi `.env` production sederhana: `APP_ENV=production`, `APP_DEBUG=false`, `FORCE_HTTPS=true`, `MULTI_SERVER=false`.
4. Import/migration database sesuai `docs/DATABASE_HOSTING.md`.
5. Pastikan folder `logs`, `storage`, `uploads`, dan `backups` writable.
6. Buka `health.php`, lalu `readiness.php`.

## VPS single server

1. Install PHP 8.1+, MySQL/MariaDB, Nginx/Apache, Redis opsional, Supervisor, Cron.
2. Pakai contoh `deploy/nginx-site.conf` atau `deploy/apache-vhost.conf`.
3. Jalankan `php scripts/run_migrations.php`.
4. Jalankan worker dengan Supervisor dari `deploy/supervisor-worker.conf`.
5. Tambahkan cron: `* * * * * php /var/www/revibe/scripts/cron.php`.

## Docker production

```bash
cp .env.example .env
# edit .env production
COMPOSE_FILE=docker-compose.production.yml docker compose up -d --build
```

`docker-compose.production.yml` berisi `app`, `worker`, `scheduler`, `redis`, dan `db-demo` opsional. Production serius disarankan memakai managed database eksternal.

## Multi-server production

Gunakan arsitektur:

Cloudflare DNS/CDN/WAF → Load Balancer → App Server 1/2 → Managed MySQL → Managed Redis → S3/R2/Spaces → Worker → Scheduler → Backup Offsite → Monitoring.

Wajib di `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domainmu.com
FORCE_HTTPS=true
MULTI_SERVER=true
SESSION_DRIVER=redis
CACHE_DRIVER=redis
RATE_LIMIT_DRIVER=redis
QUEUE_DRIVER=redis
STORAGE_DRIVER=r2
ADMIN_2FA_REQUIRED=true
PAYMENT_SANDBOX=false
```

Baca `docs/MULTI_SERVER_GUIDE.md` dan `deploy/multiserver/deploy-checklist.md`.

## Health dan readiness

- `health.php` ringan untuk load balancer. Return 200 jika app hidup.
- `readiness.php` mengecek database, migration, Redis, storage, env production, backup, monitoring, 2FA admin, payment sandbox, cron, dan worker.
- Untuk multi-server production, readiness akan gagal 503 jika Redis/storage remote belum benar.

## Checklist sebelum live

Baca `docs/HOSTING_100_CHECKLIST.md` dan `docs/RELEASE_CHECKLIST_100.md`.

## Catatan keamanan GitHub

Jangan commit `.env`, dump database, file backup, log, file private user, bukti pembayaran, dokumen komplain, ZIP lama, atau secret asli. `.gitignore`, `.dockerignore`, dan CI sudah dibuat untuk membantu mencegah ini.

## Cloudflare Demo 100

Untuk demo public lewat Cloudflare Tunnel, gunakan README_CLOUDFLARE_DEMO.md dan import database/revibemarket_cloudflare_demo.sql ke database revibemarket.

Payment pada paket ini manual demo. Demo saja, jangan transfer uang asli. Biaya Layanan ReVibe adalah 12%, cashback seller 6%, dan margin platform demo 6%.

## Patch seller delete upload
- Seller dapat menghapus produk dari Seller Center jika salah upload.
- Jika produk belum memiliki transaksi, produk dan foto produk dihapus permanen.
- Jika produk sudah memiliki transaksi, produk disembunyikan dan stok dibuat 0 agar riwayat order tetap aman.
- Form Jual Barang sekarang memiliki preview foto dengan tombol Hapus sebelum produk diposting.
