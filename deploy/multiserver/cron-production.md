# Cron Production

Jalankan cron hanya di satu scheduler instance:

```cron
* * * * * cd /var/www/revibe && /usr/bin/php scripts/cron.php >> logs/cron.log 2>&1
```

Script memiliki Redis/file lock agar tidak dobel, tetapi tetap rekomendasi production adalah satu scheduler instance.
