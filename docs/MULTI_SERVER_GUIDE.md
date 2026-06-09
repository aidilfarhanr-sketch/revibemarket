# MULTI_SERVER_GUIDE.md

## Arsitektur final

Cloudflare DNS/CDN/WAF → Load Balancer → App Server 1 + App Server 2 → Managed MySQL → Managed Redis → S3/R2/Spaces → Queue Worker → Scheduler/Cron tunggal → Backup Offsite → Sentry/Healthchecks.

## Syarat wajib

- App server harus stateless.
- Session, cache, rate limit, dan queue harus memakai Redis.
- Upload harus memakai S3/R2/Spaces.
- Database tidak boleh berada di app server.
- Cron hanya jalan di satu scheduler instance.
- Worker boleh lebih dari satu, tetapi queue harus Redis/database.
- Backup tidak disimpan hanya di app server.

## ENV minimal

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domainmu.com
FORCE_HTTPS=true
MULTI_SERVER=true
TRUSTED_PROXIES=10.0.0.0/8,172.16.0.0/12,192.168.0.0/16
SESSION_DRIVER=redis
CACHE_DRIVER=redis
RATE_LIMIT_DRIVER=redis
QUEUE_DRIVER=redis
STORAGE_DRIVER=r2
STORAGE_PUBLIC_BASE_URL=https://cdn.domainmu.com
ADMIN_2FA_REQUIRED=true
PAYMENT_SANDBOX=false
```

## Scaling

Untuk menambah app server, clone release yang sama, isi `.env` yang sama, arahkan ke DB/Redis/storage yang sama, lalu masukkan ke load balancer. User tetap login karena session di Redis, file tetap ada karena storage remote.
