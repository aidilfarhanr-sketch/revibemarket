# ReVibe Market Final Revision - Deployment Guide

## Local XAMPP
1. Extract folder `revibe` ke `xampp/htdocs/revibe`.
2. Buat database MySQL/MariaDB, contoh `revibe_market`.
3. Copy `.env.example` menjadi `.env`.
4. Isi minimal: `APP_URL=http://localhost/revibe`, `DB_NAME=revibe_market`, `DB_USER=root`, `DB_PASS=`.
5. Import migration: buka terminal di folder project lalu jalankan `php scripts/run_migrations.php`.
6. Buka `http://localhost/revibe`.

## Import database tanpa hapus data lama
- Jangan drop database.
- Jalankan migration 010–023 melalui `php scripts/run_migrations.php` atau import file SQL satu per satu dari `database/migrations`.
- Semua migration final memakai pola additive: `CREATE TABLE IF NOT EXISTS` dan `ADD COLUMN IF NOT EXISTS`.

## Verifikasi email OTP
- Untuk local, set `SMTP_HOST=` kosong dan cek `logs/app.log`/`logs/whatsapp.log` atau notification queue.
- Untuk production, isi SMTP di `.env`.
- Register akun baru, sistem membuat `verification_codes`, mengirim OTP, lalu user masuk ke `pages/verify_email.php`.

## Verifikasi WhatsApp OTP
- Set `REQUIRE_PHONE_VERIFICATION=true` jika wajib.
- Untuk local gunakan `WHATSAPP_PROVIDER=log`.
- Untuk production isi `WHATSAPP_API_URL`, `WHATSAPP_API_TOKEN`, dan provider.

## Payment manual
- `PAYMENT_MODE=manual`, `PAYMENT_GATEWAY=manual`, `PAYMENT_FLOW=escrow`.
- Buyer upload bukti bayar.
- Admin verifikasi pembayaran.
- Seller hanya menerima pending balance sampai buyer konfirmasi barang sampai.

## Midtrans sandbox
- Set `PAYMENT_GATEWAY=midtrans`, `PAYMENT_SANDBOX=true`.
- Isi `MIDTRANS_SERVER_KEY` dan `MIDTRANS_CLIENT_KEY`.
- Webhook diarahkan ke `/api/payment_webhook_midtrans.php`.

## Xendit sandbox
- Set `PAYMENT_GATEWAY=xendit`, `PAYMENT_SANDBOX=true`.
- Isi `XENDIT_API_KEY` dan `XENDIT_WEBHOOK_TOKEN`.
- Webhook diarahkan ke `/api/payment_webhook_xendit.php`.

## Queue & cron
- Local boleh sync/manual: `php scripts/queue_worker.php`.
- Production gunakan cron/supervisor: lihat `deploy/supervisor-worker.conf`.

## Health & deploy check
- `http://domain/health.php`
- `php scripts/deploy_check.php`
- `php scripts/permissions_check.php`

## Folder permission
Pastikan writable: `storage`, `storage/private`, `storage/cache`, `logs`, `uploads`.

## Security production
- `APP_ENV=production`
- `APP_DEBUG=false`
- Jangan upload `.env`, backup, dump SQL production, logs, dan ZIP lama ke public web root.
