# Cloud & Scaling Guide — ReVibe Market

## Single VPS
- Apache/Nginx + PHP-FPM
- MariaDB/MySQL managed atau lokal
- `STORAGE_DRIVER=local`
- `CACHE_DRIVER=file`
- `RATE_LIMIT_DRIVER=database`
- Cron queue worker aktif

## Upgrade ke 2 App Server
Gunakan komponen shared:
- Session: database/Redis-ready (`SESSION_DRIVER=database` atau Redis)
- Storage: S3/R2-ready (`STORAGE_DRIVER=s3`)
- Cache: Redis (`CACHE_DRIVER=redis`)
- Rate limit: Redis (`RATE_LIMIT_DRIVER=redis`)
- Queue: database/Redis-ready (`QUEUE_DRIVER=database` atau Redis)
- CDN: isi `CDN_URL`

## Health check load balancer
Gunakan:
```text
/health.php
```

Status 200 berarti:
- PHP OK
- DB OK
- storage private writable
- logs writable

## Hindari sticky session
Setelah session shared aktif, load balancer tidak perlu sticky session. Untuk XAMPP/local tetap gunakan file session.
