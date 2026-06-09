# Rollback Checklist

- [ ] Keluarkan app server release bermasalah dari load balancer.
- [ ] Aktifkan release sebelumnya.
- [ ] Jalankan health/readiness.
- [ ] Restart worker/scheduler.
- [ ] Cek order/payment terakhir.
- [ ] Catat incident dan root cause.
