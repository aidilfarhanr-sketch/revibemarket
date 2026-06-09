# Checklist Production ReVibe Market V25

- [ ] `.env` production sudah dibuat dan tidak ikut commit.
- [ ] `APP_DEBUG=false` di production.
- [ ] Database dibuat dari `database/migrations`, bukan restore SQL besar di webroot.
- [ ] `health.php` hijau.
- [ ] Folder `logs`, `storage`, dan `uploads` writable tetapi tidak listing publik.
- [ ] SMTP aktif untuk reset password dan email verification.
- [ ] Admin 2FA disiapkan (`ADMIN_2FA_REQUIRED=true`) sebelum live.
- [ ] Payment gateway production memakai Midtrans/Xendit, bukan screenshot manual.
- [ ] Webhook payment memverifikasi signature.
- [ ] Cron aktif untuk token expired, rate limit cleanup, pending payment expired, dan backup.
- [ ] CDN_URL sudah diisi jika memakai CDN.
- [ ] Backup database dan storage diuji restore.
- [ ] CI GitHub Actions berjalan tanpa error.
- [ ] File sensitif tidak ada di public release: `.env`, `.sql dump production`, `.zip`, `.log`.
