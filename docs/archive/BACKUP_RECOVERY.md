# Backup & Recovery ReVibe Market

## Backup harian
```bash
bash scripts/backup_full.sh
```
Backup database masuk `backups/database`, storage/upload masuk `backups/storage`.

## Restore database
```bash
bash scripts/restore_db.sh backups/database/revibe_market_YYYYmmdd_HHMMSS.sql.gz
```

## Restore storage
```bash
bash scripts/restore_storage.sh backups/storage/storage_YYYYmmdd_HHMMSS.tar.gz
```

## Cron rekomendasi
```cron
0 2 * * * cd /path/revibe && bash scripts/backup_full.sh
30 2 * * * cd /path/revibe && bash scripts/backup_cleanup.sh
*/5 * * * * cd /path/revibe && php scripts/queue_worker.php 50
*/15 * * * * cd /path/revibe && php scripts/cron.php
```

Retention default: harian 7 hari dan mingguan 4 minggu dapat disesuaikan di strategi hosting.


## Restore Drill Checklist 85%

Lakukan minimal seminggu sekali atau sebelum demo besar:

1. Buat backup DB, storage, dan full zip.
2. Buat database test kosong, contoh `revibe_restore_test`.
3. Restore DB ke database test.
4. Restore folder `storage/private` dan `uploads`.
5. Copy `.env.example` menjadi `.env.restore-test`.
6. Jalankan:
   ```bash
   php scripts/run_migrations.php
   php scripts/deploy_check.php
   php scripts/permissions_check.php
   ```
7. Login admin.
8. Buka dashboard admin, order, payment proof private, dan log.
9. Buat order test, upload proof, verify payment, complete order, cek escrow release.
10. Catat hasil di tabel `backup_runs` atau dokumen internal.

## Offsite Backup Ready

Untuk production, simpan backup di luar server utama:
- S3/R2
- Google Drive
- VPS kedua
- Storage provider hosting

Jangan simpan backup `.sql`, `.zip`, `.tar.gz` di folder publik.
