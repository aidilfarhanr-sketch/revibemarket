# HOSTING_GUIDE.md — ReVibe Market

## Target hosting

ReVibe bisa dijalankan di:

1. Local/XAMPP untuk development.
2. Shared hosting PHP/MySQL untuk demo/production sederhana.
3. VPS single-server untuk production kecil.
4. Docker/container.
5. Multi-server dengan load balancer, managed DB, Redis, dan object storage.

## Shared hosting

- Upload source code tanpa `.env`, log, backup, dan private uploads.
- Buat `.env` dari `.env.example`.
- Set `APP_ENV=production`, `APP_DEBUG=false`, `FORCE_HTTPS=true`, `MULTI_SERVER=false`.
- Jalankan migration via SSH: `php scripts/run_migrations.php`, atau import SQL jika hosting tidak punya CLI.
- Pastikan `.htaccess` aktif.
- Cek `health.php` dan `readiness.php`.

## VPS

- PHP 8.1+, MariaDB/MySQL, Redis, Nginx/Apache, Supervisor, Cron.
- Gunakan `deploy/nginx-site.conf` atau `deploy/apache-vhost.conf`.
- Gunakan `deploy/supervisor-worker.conf` untuk worker.
- Tambahkan cron scheduler.
- Gunakan backup offsite.

## Multi-server

Baca `docs/MULTI_SERVER_GUIDE.md`.
