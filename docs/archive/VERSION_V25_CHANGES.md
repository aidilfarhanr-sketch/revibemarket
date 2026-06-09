# Perubahan V25

1. Menambahkan struktur production-ready: `app/Services`, `app/Repositories`, `app/Middleware`, `database/migrations`, `database/seeds`, `storage`, `scripts`, `logs`.
2. Memindahkan restore SQL besar dari webroot ke `database/legacy`.
3. Menambahkan `.env.example` dan `config/env.php` agar credential tidak hardcode.
4. Mengubah `config/db.php` agar membaca environment variable.
5. Menambahkan loader animasi logo RV di semua halaman view: `assets/css/loader.css` dan `assets/js/loader.js`.
6. Menambahkan logging JSON, rate limit service, mailer service, upload service, policy helper ownership.
7. Menghapus runtime DDL chat dari halaman dan memindahkannya ke migration.
8. Menambahkan migration logs/audit/login audit/order status/payment status/rate limit.
9. Menambahkan health check, Dockerfile, docker-compose, script backup/restore/cron/run migration.
10. Menambahkan README deployment, checklist production, dan GitHub Actions CI.
