# ReVibe Market V28 Production Readiness Patch

Patch ini menerapkan revisi lanjutan dari V27 tanpa redesign dan tanpa mengubah PHP/MySQL menjadi HTML statis.

## Fokus patch

- Service layer lebih matang melalui `ServiceResult`, `JsonResponse`, Product/Chat/Complaint/Admin/SellerBalance service.
- Storage driver `local`, `s3`, dan `r2` di `StorageService` dengan fallback local untuk XAMPP.
- Redis session, Redis cache, dan Redis queue dengan fallback file/database/sync.
- Cache nyata untuk data public melalui `ProductService::latestPublic()` dan `ProductService::categories()`.
- Readiness endpoint untuk load balancer: `readiness.php`.
- Admin 2FA real: `pages/admin_2fa.php`, `pages/admin_2fa_verify.php`, `pages/admin_2fa_resend.php`.
- Ledger escrow diperbaiki dengan row lock `FOR UPDATE`, idempotency key, dan `balance_before/balance_after` akurat.
- Error masking production via `config/error_handler.php` dan halaman `pages/500.php`.
- Sentry/alerting via `ErrorTrackingService` dan `scripts/alert_test.php`.
- Script audit/test tambahan di folder `scripts/`.

## Cara menjalankan di XAMPP/local

1. Extract project ke `htdocs/revibe`.
2. Copy `.env.example` menjadi `.env`.
3. Isi database local:
   - `DB_HOST=localhost`
   - `DB_NAME=revibe_market`
   - `DB_USER=root`
   - `DB_PASS=`
4. Jalankan migration melalui phpMyAdmin atau:
   ```bash
   php scripts/run_migrations.php
   ```
5. Buka `http://localhost/revibe`.
6. Untuk local tanpa Redis/S3, biarkan:
   - `SESSION_DRIVER=file`
   - `CACHE_DRIVER=file`
   - `QUEUE_DRIVER=sync`
   - `STORAGE_DRIVER=local`

## Setup SMTP

Isi `.env`:

```env
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=your_user
SMTP_PASS=your_password
SMTP_SECURE=tls
MAIL_FROM=no-reply@revibe.local
MAIL_FROM_NAME="ReVibe Market"
```

Di local, jika SMTP belum aktif dan `APP_DEBUG=true`, OTP dicatat ke `logs/otp-development.log`.

## Setup WhatsApp provider

Untuk development:

```env
WHATSAPP_PROVIDER=log
```

Pesan WhatsApp masuk ke `logs/whatsapp.log`.

Untuk production, isi:

```env
WHATSAPP_PROVIDER=api
WHATSAPP_API_URL=https://provider.example/send
WHATSAPP_API_TOKEN=token_provider
WHATSAPP_SENDER_ID=sender_id
```

## Setup Cloudflare R2

```env
STORAGE_DRIVER=r2
STORAGE_R2_ACCOUNT_ID=account_id
STORAGE_R2_BUCKET=revibe-market
STORAGE_R2_ACCESS_KEY=access_key
STORAGE_R2_SECRET_KEY=secret_key
STORAGE_S3_REGION=auto
STORAGE_S3_USE_PATH_STYLE=true
STORAGE_PUBLIC_BASE_URL=https://cdn-domain.example
```

File private tetap diakses melalui controller/policy, bukan URL public.

## Setup S3-compatible storage

```env
STORAGE_DRIVER=s3
STORAGE_S3_ENDPOINT=https://s3.example.com
STORAGE_S3_REGION=ap-southeast-1
STORAGE_S3_BUCKET=revibe-market
STORAGE_S3_ACCESS_KEY=access_key
STORAGE_S3_SECRET_KEY=secret_key
STORAGE_S3_USE_PATH_STYLE=true
STORAGE_PUBLIC_BASE_URL=https://cdn-domain.example
```

Jika konfigurasi salah, sistem fallback ke local agar XAMPP tidak crash.

## Setup Redis

Install Redis server dan PHP extension redis. Lalu ubah `.env`:

```env
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DATABASE=0
SESSION_DRIVER=redis
CACHE_DRIVER=redis
QUEUE_DRIVER=redis
RATE_LIMIT_DRIVER=redis
```

Jika Redis mati, session/cache/queue fallback aman sesuai konfigurasi.

## Menjalankan queue worker

Local:

```bash
php scripts/queue_worker.php --limit=50
```

Cron VPS:

```cron
* * * * * /usr/bin/php /var/www/revibe/scripts/queue_worker.php --limit=50 >> /var/www/revibe/logs/queue.log 2>&1
```

Supervisor:

```ini
[program:revibe-queue]
command=/usr/bin/php /var/www/revibe/scripts/queue_worker.php --limit=100
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/revibe/logs/queue-worker.log
```

## Admin 2FA

Aktifkan:

```env
ADMIN_2FA_REQUIRED=true
```

Alur:

1. Admin login email/password.
2. Jika password benar, admin diarahkan ke `pages/admin_2fa.php`.
3. OTP 6 digit dikirim email.
4. OTP berlaku 10 menit, max attempts 5, cooldown resend 60 detik.
5. Setelah benar, session baru dibuat dan admin masuk dashboard.

## Audit escrow ledger

Cek mismatch:

```bash
php scripts/audit_seller_ledger.php
```

Fix mismatch jika diperlukan:

```bash
php scripts/audit_seller_ledger.php --fix
```

## Test Sentry/alert

```bash
php scripts/alert_test.php
```

Atur `.env`:

```env
SENTRY_DSN=https://public_key@sentry.io/project_id
ALERT_CHANNEL=webhook
ALERT_WEBHOOK_URL=https://example.com/webhook
```

## Test APP_DEBUG=false

```env
APP_ENV=production
APP_DEBUG=false
```

Buka halaman yang error. User hanya melihat pesan aman + request ID. Detail masuk `logs/error.log`.

## Deploy single VPS

- Gunakan Nginx/Apache + PHP-FPM.
- Set document root ke folder project.
- Jalankan migration.
- Pastikan `storage/`, `uploads/`, dan `logs/` writable.
- Jalankan cron/worker.
- Aktifkan HTTPS.
- Set `APP_DEBUG=false`.

## Upgrade multi-server/load balancing

Gunakan konfigurasi:

```env
SESSION_DRIVER=redis
STORAGE_DRIVER=r2
CACHE_DRIVER=redis
QUEUE_DRIVER=redis
RATE_LIMIT_DRIVER=redis
```

Gunakan `readiness.php` sebagai health check load balancer. Jika session sudah Redis, sticky session tidak wajib.

## Checklist target 85%

- [x] Service layer diperkuat.
- [x] S3/R2 driver tersedia dan fallback local.
- [x] Redis session/queue/cache tersedia.
- [x] Cache public nyata tersedia.
- [x] Load balancing readiness tersedia.
- [x] Admin 2FA aktif via env.
- [x] Ledger escrow lebih akurat dan idempotent.
- [x] Sentry/alerting tersedia.
- [x] Error masking production tersedia.
- [x] Penyatuan HTML/PHP didukung melalui include/layout helper dan komponen konsisten tanpa redesign.
