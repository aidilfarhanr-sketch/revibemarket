# Rollback ReVibe Market

1. Aktifkan maintenance page di hosting/VPS.
2. Restore file release sebelumnya dari backup artifact.
3. Restore database dengan `bash scripts/restore_db.sh backups/database/NAMA.sql.gz` jika migration bermasalah.
4. Restore storage dengan `bash scripts/restore_storage.sh backups/storage/NAMA.tar.gz` jika upload/private file berubah.
5. Jalankan `php scripts/deploy_check.php` dan cek `health.php`.
6. Nonaktifkan maintenance.

Catatan: migration final dibuat additive/non-destruktif, jadi rollback aplikasi biasanya cukup mengganti file project dan menjaga database tetap utuh.
