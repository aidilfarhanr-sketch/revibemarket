# RELEASE_CHECKLIST_100.md

- [ ] `.env` production sudah benar.
- [ ] `APP_DEBUG=false`.
- [ ] `APP_URL=https://domain`.
- [ ] `ADMIN_2FA_REQUIRED=true`.
- [ ] `PAYMENT_SANDBOX=false`.
- [ ] Migration sukses.
- [ ] `health.php` sukses.
- [ ] `readiness.php` sukses.
- [ ] Worker aktif.
- [ ] Cron/scheduler aktif satu instance.
- [ ] Backup offsite aktif.
- [ ] Monitoring/alert aktif.
- [ ] Release artifact tidak mengandung `.env`, log, backup, dump, atau private upload.
