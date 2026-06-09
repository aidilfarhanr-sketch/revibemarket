# Panduan Upload ReVibeMarket ke GitHub

## 1. File yang aman di-upload

Project ini sudah disiapkan untuk GitHub. File lokal/runtime yang tidak perlu ikut commit sudah dibersihkan dari paket ini.

Yang boleh masuk GitHub:
- `index.php`
- `pages/`
- `api/`
- `app/`
- `config/`
- `assets/`
- `database/migrations/`
- `database/seeds/`
- `database/revibemarket_cloudflare_demo.sql`
- `docs/`
- `deploy/`
- `scripts/`
- `.env.example`
- `.env.cloudflare-demo.example`
- `.gitignore`
- `README.md`

Yang jangan di-upload:
- `.env`
- file upload user asli
- bukti pembayaran asli
- log
- backup database asli
- file ZIP proyek
- password/API key/payment gateway key asli

## 2. Cara upload dari CMD / Git Bash

Masuk ke folder project:

```bash
cd path/ke/revibemarket_github_ready
```

Inisialisasi Git:

```bash
git init
git branch -M main
git add .
git status
git commit -m "Initial commit ReVibeMarket"
```

Hubungkan ke repository GitHub:

```bash
git remote add origin https://github.com/USERNAME/NAMA-REPO.git
git push -u origin main
```

Ganti `USERNAME` dan `NAMA-REPO` sesuai akun dan repository GitHub kamu.

## 3. Cara update commit berikutnya

Setelah ada revisi baru:

```bash
git add .
git commit -m "Update ReVibeMarket"
git push
```

## 4. Cara clone di komputer lain

```bash
git clone https://github.com/USERNAME/NAMA-REPO.git
cd NAMA-REPO
copy .env.example .env
```

Lalu sesuaikan `.env`, buat database, dan jalankan migration/import SQL sesuai README.
