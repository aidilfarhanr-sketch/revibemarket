# REDIS_SETUP.md

Redis dipakai untuk session, cache, rate limit, queue, dan lock cron.

## ENV multi-server

```env
SESSION_DRIVER=redis
CACHE_DRIVER=redis
RATE_LIMIT_DRIVER=redis
QUEUE_DRIVER=redis
REDIS_HOST=host-redis
REDIS_PORT=6379
REDIS_PASSWORD=isi_di_env_asli
REDIS_DATABASE=0
REDIS_PREFIX=revibe_production:
```

Jika `MULTI_SERVER=true`, `readiness.php` akan gagal jika Redis belum tersedia untuk driver wajib.
