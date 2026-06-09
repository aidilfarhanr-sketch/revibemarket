ReVibe Market Security Patch V24

Yang sudah dipatch otomatis:
1. Session cookie memakai HttpOnly + SameSite=Strict + Secure saat HTTPS aktif.
2. db.php tidak lagi hardcode root; gunakan REVIBE_DB_HOST, REVIBE_DB_NAME, REVIBE_DB_USER, REVIBE_DB_PASS.
3. Pesan error database tidak lagi menampilkan detail MySQL ke user.
4. Root .htaccess ditambah CSP dasar dan blokir file sensitif (.sql, .env, .zip, .log, dll).
5. uploads/products dan uploads/profile ditambah .htaccess agar PHP/script tidak bisa dieksekusi.
6. Upload gambar ditambah validasi getimagesize().
7. Password registrasi disamakan minimal 8 karakter.
8. Rate limit ringan ditambah untuk registrasi, forgot password, dan chat.
9. Checkout tidak lagi mempercayai buyer_latitude/buyer_longitude dari form POST; server pakai koordinat profil.
10. SQL dump dihapus dari folder webroot. File SQL disediakan terpisah dan jangan diupload ke folder publik.

Yang masih wajib dikerjakan sebelum live:
- Buat user database khusus, contoh: revibe_app, jangan pakai root.
- Set environment variable database di hosting.
- Implementasi verifikasi email penuh untuk akun baru.
- Integrasi payment gateway Midtrans/Xendit agar tidak mengandalkan screenshot bukti transfer.
- Buat cron job auto-release escrow setelah batas waktu penerimaan.
- Tambahkan 2FA minimal untuk admin.
