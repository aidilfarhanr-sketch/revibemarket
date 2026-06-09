# HOSTING_100_CHECKLIST.md

## Local/shared sederhana

- [ ] Database ter-import.
- [ ] Folder writable.
- [ ] `.htaccess` aktif.
- [ ] Upload produk/profile berjalan.
- [ ] Checkout dan bukti pembayaran berjalan.

## Production multi-server

- [ ] Load balancer aktif.
- [ ] Minimal 2 app server.
- [ ] Managed DB aktif.
- [ ] Managed Redis aktif.
- [ ] S3/R2/Spaces aktif.
- [ ] Session/cache/rate limit/queue memakai Redis.
- [ ] Upload memakai object storage.
- [ ] Worker dan scheduler aktif.
- [ ] Backup offsite aktif.
- [ ] Sentry/alert/uptime aktif.
- [ ] Rollback release sebelumnya tersedia.
