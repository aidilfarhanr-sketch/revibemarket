# BACKUP_RESTORE_100.md

## Backup

Backup minimal mencakup:

- Database.
- Metadata storage.
- File storage local jika `STORAGE_DRIVER=local`.
- Offsite copy ke S3/R2/Spaces/provider backup.

Jalankan:

```bash
bash scripts/backup_full.sh
```

Script membaca `.env`, membuat timestamp, tidak mencetak password, dan menjalankan retention cleanup.

## Restore

```bash
bash scripts/restore_full.sh database.sql.gz storage.tar.gz
php scripts/run_migrations.php
```

Sebelum restore production, aktifkan maintenance mode dari panel hosting/reverse proxy agar tidak ada transaksi setengah jalan.
