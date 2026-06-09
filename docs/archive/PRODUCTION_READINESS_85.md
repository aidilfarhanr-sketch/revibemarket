# ReVibe Market — Production Readiness 85% Patch

Patch ini menerapkan instruksi revisi fokus 85% tanpa redesign. Desain, warna, layout besar, logo, loader RV, flow lama, dan fitur lama dipertahankan.

## Area yang dinaikkan

| Area | Target | Perbaikan utama |
|---|---:|---|
| APIs & Backend Logic | 85% | JSON response standar, service payment/storage/cache/rate-limit lebih matang, webhook transaction-safe |
| Database & Storage | 85% | migration 024, private storage di `storage/private`, metadata `storage_files`, backup run table |
| Auth & Permissions | 85% | OTP hash tetap, rate limit verifikasi, session device, FilePolicy dan InvoicePolicy |
| Cloud & Compute | 85% | driver local tetap XAMPP, Redis/S3/R2-ready dengan fallback aman |
| CI/CD | 85% | CI MariaDB, lint PHP, migration import, business-flow smoke test, deploy/permission check, artifact exclude sensitive |
| Security & RLS | 85% | application-level policy, private file controller, audit akses file, error handler production-safe |
| Rate Limiting | 85% | driver file/database/Redis-ready, endpoint penting diberi limit |
| Caching & CDN | 85% | CacheService file/Redis-ready, asset version/CDN via `.env` |
| Load Balancing & Scaling | 85% | health/readiness, shared driver ready, storage/cache/rate-limit/session database/Redis-ready |
| Error Tracking & Logs | 85% | Logger JSON, request_id, global error handler, Sentry-ready dari env |
| Availability & Recovery | 85% | backup scripts, restore scripts, retention docs, health ping docs |
| Payment Gateway | 85% | Midtrans Snap dan Xendit Invoice sandbox-ready jika key di `.env` |
| Escrow | 85% | webhook/manual paid memicu pending balance, release saat completed, idempotency ledger |
| OTP & Notifications | 85% | OTP hash, cooldown, queue retry, email/WhatsApp provider-ready |
| HTML/Tampilan | Dipertahankan | Tidak redesign; patch di backend/service/security |

## Cara jalan di XAMPP/local

1. Extract ZIP ke `C:\xampp\htdocs\revibe`.
2. Start Apache dan MySQL dari XAMPP.
3. Buat database `revibe_market` di phpMyAdmin.
4. Copy `.env.example` menjadi `.env`.
5. Pastikan:
   ```env
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost/revibe
   DB_NAME=revibe_market
   DB_USER=root
   DB_PASS=
   PAYMENT_GATEWAY=manual
   STORAGE_DRIVER=local
   CACHE_DRIVER=file
   RATE_LIMIT_DRIVER=file
   QUEUE_DRIVER=sync
   ```
6. Jalankan migration:
   ```bash
   cd C:\xampp\htdocs\revibe
   php scripts/run_migrations.php
   ```
7. Buka:
   ```text
   http://localhost/revibe
   ```

## Setup SMTP

Isi `.env`:
```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=emailkamu@gmail.com
SMTP_PASS=app-password
SMTP_SECURE=tls
MAIL_FROM=no-reply@revibe.local
MAIL_FROM_NAME="ReVibe Market"
```

Untuk Gmail gunakan App Password, bukan password login biasa.

## Setup WhatsApp provider

Local default:
```env
WHATSAPP_PROVIDER=log
```

Production:
```env
WHATSAPP_PROVIDER=fonnte
WHATSAPP_API_URL=https://api.fonnte.com/send
WHATSAPP_API_TOKEN=token-provider
```

Provider lain seperti Meta WhatsApp Cloud API, Twilio, Wablas, atau Fonnte bisa dimasukkan lewat service `WhatsAppService`.

## Setup payment manual

Default:
```env
PAYMENT_GATEWAY=manual
PAYMENT_MANUAL_MODE=true
PAYMENT_FLOW=escrow
```

Alur:
1. Buyer checkout.
2. Buyer upload bukti.
3. Admin verifikasi.
4. Order menjadi `paid_waiting_seller`.
5. Seller proses barang.
6. Buyer konfirmasi sampai.
7. Escrow release ke available balance seller.

## Setup Midtrans sandbox

1. Buat akun Midtrans sandbox.
2. Ambil Server Key dan Client Key.
3. Isi `.env`:
   ```env
   PAYMENT_GATEWAY=midtrans
   MIDTRANS_SERVER_KEY=SB-Mid-server-xxxx
   MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxx
   MIDTRANS_IS_PRODUCTION=false
   MIDTRANS_WEBHOOK_URL=http://localhost/revibe/api/payment_webhook_midtrans.php
   ```
4. Checkout produk non-COD.
5. Jika key benar, `payments.payment_url` dan `snap_token` akan terisi.
6. Set webhook Midtrans ke `api/payment_webhook_midtrans.php`.

## Setup Xendit sandbox

1. Buat akun Xendit test/sandbox.
2. Isi `.env`:
   ```env
   PAYMENT_GATEWAY=xendit
   XENDIT_API_KEY=xnd_development_xxxx
   XENDIT_WEBHOOK_TOKEN=token-webhook
   XENDIT_WEBHOOK_URL=http://localhost/revibe/api/payment_webhook_xendit.php
   ```
3. Checkout produk non-COD.
4. Jika key benar, invoice URL akan tersimpan di `payments.payment_url`.
5. Set callback token Xendit sesuai `XENDIT_WEBHOOK_TOKEN`.

## Test webhook

Midtrans:
```bash
curl -X POST http://localhost/revibe/api/payment_webhook_midtrans.php \
  -H "Content-Type: application/json" \
  -d '{"order_id":"INV-20260101-TEST","transaction_status":"pending"}'
```

Webhook paid asli wajib memiliki signature dari provider. Sistem menolak signature tidak valid.

## Test escrow release

1. Buyer checkout non-COD.
2. Admin verify payment manual atau webhook gateway paid.
3. Cek `seller_balances.pending_balance` bertambah.
4. Seller update status menjadi shipped/delivered.
5. Buyer klik konfirmasi sampai.
6. Cek `pending_balance` turun dan `available_balance` naik.
7. Cek `seller_ledger` memiliki idempotency key `escrow_release_order_{order_id}`.

## Test seller cashback

1. Selesaikan order sampai `completed`.
2. Cek `coin_transactions` type `cashback`.
3. Cek idempotency key `seller_coin_cashback_order_{order_id}` agar tidak double.
4. Ulangi confirm/cron tidak boleh menambah cashback kedua kali.

## Test withdrawal

1. Seller cek `seller_balance.php`.
2. Ajukan withdrawal dari saldo available, bukan pending.
3. Admin approve/reject di panel admin.
4. Cek `seller_ledger` dan audit log.

## Backup dan restore

Backup:
```bash
bash scripts/backup_db.sh
bash scripts/backup_storage.sh
bash scripts/backup_full.sh
bash scripts/backup_cleanup.sh
```

Restore:
```bash
bash scripts/restore_db.sh backups/nama_backup.sql
bash scripts/restore_storage.sh backups/nama_storage.tar.gz
bash scripts/restore_full.sh backups/nama_full.tar.gz
```

Checklist restore drill tersedia di `BACKUP_RECOVERY.md`.

## Deploy VPS/hosting

1. Upload project tanpa `.env`, `logs`, `storage/private`, `backups`, ZIP lama, dan SQL dump production.
2. Buat `.env` di server.
3. Import database atau jalankan `php scripts/run_migrations.php`.
4. Pastikan permission:
   ```bash
   php scripts/permissions_check.php
   php scripts/deploy_check.php
   ```
5. Pasang cron:
   ```cron
   * * * * * php /path/revibe/scripts/queue_worker.php 50
   */5 * * * * php /path/revibe/scripts/cron.php
   0 2 * * * bash /path/revibe/scripts/backup_db.sh
   10 2 * * * bash /path/revibe/scripts/backup_storage.sh
   20 2 * * * bash /path/revibe/scripts/backup_cleanup.sh
   ```
6. Arahkan document root ke folder project dan blok akses `app`, `config`, `database`, `scripts`, `storage/private`, `logs`.

## Checklist final

- [x] Desain ReVibe tidak diubah dari nol.
- [x] Fitur lama dipertahankan.
- [x] Private payment/complaint proof dipindah ke storage private.
- [x] Payment gateway sandbox-ready.
- [x] Webhook transaction-safe dan idempotent setelah sukses.
- [x] Rate limit endpoint penting.
- [x] Cache file/Redis-ready.
- [x] Storage local/S3/R2-ready.
- [x] Health/readiness check.
- [x] CI/CD diperkuat.
- [x] Backup/restore tersedia.
