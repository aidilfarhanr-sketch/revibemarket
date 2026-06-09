# ROLLBACK_100.md

## Rollback cepat

1. Stop traffic ke release bermasalah dari load balancer.
2. Aktifkan release ZIP/container sebelumnya.
3. Jalankan `health.php`.
4. Jalankan `readiness.php`.
5. Nyalakan worker/scheduler.
6. Cek log error dan transaksi terakhir.

## Catatan database

Rollback kode aman jika migration tidak destructive. Untuk perubahan database besar, backup database dulu dan buat rollback SQL terpisah.
