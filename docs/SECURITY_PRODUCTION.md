# SECURITY_PRODUCTION.md

## Wajib production

- `APP_DEBUG=false`.
- `FORCE_HTTPS=true`.
- `ADMIN_2FA_REQUIRED=true`.
- `PAYMENT_SANDBOX=false`.
- `.env`, `config/`, `database/`, `docs/`, `scripts/`, `logs/`, `storage/private/`, dan `backups/` diblokir dari web.
- Session cookie secure, httponly, SameSite Strict.
- CSRF aktif pada form sensitif.
- Error detail masuk log, user hanya melihat pesan aman.
- Trusted proxy diisi jika di balik load balancer.
