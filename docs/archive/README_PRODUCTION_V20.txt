REVIBE MARKET V20 - PRODUCTION HARDENING

Perubahan utama:
1. Saldo koin kini ledger-only dari coin_transactions. Tidak lagi dihitung dari products.sold.
2. Withdrawal koin memakai transaction, status lock, withdrawal_code, dan pending ledger.
3. Checkout memakai transaction + SELECT FOR UPDATE untuk mencegah overselling stok.
4. Payment verification dan admin actions memakai validasi status agar tidak double-process.
5. Settlement seller ditambahkan: seller_balances, seller_balance_transactions, platform_commissions, seller_withdrawals.
6. Dana penjualan non-COD masuk saldo seller setelah pembeli konfirmasi barang sampai.
7. COD dicatat sebagai transaksi langsung pembeli-seller; tidak masuk saldo escrow platform.
8. Upload file dipisahkan:
   - uploads/products
   - uploads/profile
   - uploads/payment_proofs (private)
   - uploads/complaints (private)
9. Bukti pembayaran dilihat admin lewat pages/admin/view_file.php, bukan direct URL public.
10. Forgot password tidak lagi menampilkan token di halaman. Token di-hash dan dikirim via mail().
11. Login lock 15 menit setelah 5x gagal.
12. Search navbar memakai e() untuk menutup celah XSS kecil.
13. Review sample diberi label sebagai contoh tampilan, bukan ulasan pembeli asli.

Cara update database lama:
1. Backup database lama dulu.
2. Import DATABASE_PRODUCTION_REVIBE_MARKET_V20.sql ke database revibe_market.
3. Ekstrak ZIP ke htdocs/revibe.
4. Refresh browser Ctrl+F5.

Catatan production:
- Untuk reset password benar-benar jalan di hosting, konfigurasi SMTP/PHP mail wajib aktif.
- Untuk ongkir 100% resmi, sambungkan API RajaOngkir/Biteship/kurir resmi.
- Jangan upload SQL dump berisi data pribadi ke GitHub/public.
