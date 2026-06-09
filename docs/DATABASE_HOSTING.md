# DATABASE_HOSTING.md

## Shared hosting

1. Buat database dari cPanel/Plesk.
2. Isi `.env`: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
3. Jika ada SSH, jalankan `php scripts/run_migrations.php`.
4. Jika tidak ada SSH, import SQL migrasi berurutan lewat phpMyAdmin.

## VPS/Docker

```bash
php scripts/run_migrations.php
php scripts/run_migrations.php # aman dijalankan ulang, migration yang sudah tercatat akan SKIP
```

## Managed MySQL

- Pakai host managed database.
- Aktifkan backup otomatis provider.
- Jika provider mewajibkan SSL, set `DB_SSL_MODE=required` dan sesuaikan koneksi bila sertifikat disediakan.

## Backup/restore

Baca `docs/BACKUP_RESTORE_100.md`.
