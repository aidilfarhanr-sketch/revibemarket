# CLOUD_STORAGE_SETUP.md

## Cloudflare R2

1. Buat bucket, contoh `revibe-production`.
2. Buat API token R2/S3 compatible.
3. Isi `.env`:

```env
STORAGE_DRIVER=r2
STORAGE_S3_ENDPOINT=https://ACCOUNT_ID.r2.cloudflarestorage.com
STORAGE_S3_REGION=auto
STORAGE_S3_BUCKET=revibe-production
STORAGE_S3_ACCESS_KEY=isi_di_env_asli
STORAGE_S3_SECRET_KEY=isi_di_env_asli
STORAGE_S3_USE_PATH_STYLE=true
STORAGE_PUBLIC_BASE_URL=https://cdn.domainmu.com
```

Private file seperti bukti pembayaran dan komplain tidak dibuka langsung dari public bucket. Aksesnya lewat controller aman/signed URL.

## DigitalOcean Spaces / AWS S3

Gunakan `STORAGE_DRIVER=spaces` atau `s3`, endpoint sesuai provider, bucket, access key, secret key, dan public base URL/CDN.
