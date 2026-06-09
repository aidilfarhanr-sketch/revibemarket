# CHANGELOG_PATCH_HOSTING_DOCS.md

Patch lanjutan dari `revibe_market_cadangan ke 5.zip`.

## Fokus patch

- Menyatukan README utama.
- Mengarsipkan dokumentasi lama ke `docs/archive/` tanpa menghapus isinya.
- Menambahkan `PROJECT_FLOW.md`.
- Menambahkan `HOSTING_GUIDE.md`.
- Merapikan `.env.example` agar tidak ada duplikasi key yang membingungkan.
- Memperkuat `.gitignore` untuk file sensitif.
- Memperkuat `.htaccess` agar aman di XAMPP subfolder maupun hosting.
- Memastikan `index.php` memakai `APP_URL` untuk canonical/base/asset utama.
- Memastikan `health.php` mengembalikan `ok` dan `success`.
- Membuat `readiness.php` tetap mengembalikan JSON walaupun database belum connect.
- Memperbaiki Docker healthcheck agar kompatibel dengan response health terbaru.

## Batasan patch

- Tidak redesign total.
- Tidak rewrite sistem dari nol.
- Tidak menghapus migration lama.
- Tidak mengubah flow buyer/seller/admin/payment/chat/complaint/coin/escrow.
- Tidak mengubah database secara merusak.
