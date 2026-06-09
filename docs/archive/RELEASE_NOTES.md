# Release Notes — ReVibe Market v27 Production Readiness 85

## Fokus
Patch ini bukan redesign. Patch memperkuat production-readiness sesuai instruksi final 85%: backend, database/storage, auth/permissions, cloud readiness, CI/CD, security/RLS, rate limiting, caching/CDN, scaling, logs, recovery, payment gateway, escrow, OTP, dan notification queue.

## File penting yang berubah
- `app/Services/StorageService.php`
- `app/Services/CacheService.php`
- `app/Services/RateLimitService.php`
- `app/Services/PaymentGatewayService.php`
- `app/Services/MidtransService.php`
- `app/Services/XenditService.php`
- `app/Services/PaymentService.php`
- `api/payment_webhook.php`
- `pages/checkout.php`
- `pages/payment_upload.php`
- `pages/complaint.php`
- `pages/admin/view_file.php`
- `.github/workflows/ci.yml`
- `.env.example`

## File baru
- `config/error_handler.php`
- `app/Policies/FilePolicy.php`
- `app/Policies/InvoicePolicy.php`
- `database/migrations/024_final_85_hardening.sql`
- `scripts/test_business_flow.php`
- `scripts/cleanup_rate_limits.php`
- `scripts/health_ping.php`
- `PRODUCTION_READINESS_85.md`
- `deploy/CLOUD_SCALING.md`
- `deploy/PAYMENT_GATEWAY_SANDBOX.md`

## Migration baru
- `024_final_85_hardening.sql`

## Catatan
- `.env` asli tetap tidak disertakan.
- Payment proof dan complaint proof baru disimpan di `storage/private`.
- File lama di `uploads/payment_proofs` dan `uploads/complaints` masih bisa dibuka sebagai fallback lewat controller berizin.
