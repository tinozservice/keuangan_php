# Pencatat Keuangan

Aplikasi web pencatat keuangan **privat keluarga**: mencatat pemasukan/pengeluaran di dalam **workspace** — dipakai sendiri atau bersama anggota keluarga terdaftar. Dibangun dengan **PHP native + SQLite**, tanpa framework dan tanpa dependensi runtime (tanpa Composer/npm), berjalan di shared hosting cPanel.

- **Produksi:** `https://domain-anda.example/` (deploy manual via FTP FileZilla, tanpa staging)
- **Situs privat:** anti-indeks crawler di tiga lapis — `robots.txt` (`Disallow: /`), meta `noindex, nofollow, noarchive`, dan header `X-Robots-Tag`
- **PRD:** salinan kerja terbaru `obsidian/01-PRD/PRD — Pencatat Keuangan.md`; arsip beku `keuangan-PRD.md` (root)
- **Dokumentasi internal:** `AGENTS.md`, `INSTRUCTIONS.md`, dan catatan Vault di `obsidian/` (Hub + Log Progres)

## Fitur Utama

### Akun & autentikasi
- Registrasi username + email + sandi dengan verifikasi OTP 6 digit via email (SMTP Brevo).
- Login memakai email **atau** username; tersedia **Login/Daftar dengan Google** (OAuth native tanpa library vendor).
- Alur lupa kata sandi 3 langkah (email → OTP → sandi baru) dan fitur "Buat kata sandi" untuk akun Google.
- Pembatasan percobaan login: maksimum 5 kegagalan per akun/identifier dalam 15 menit, kunci sementara 15 menit.

### Workspace & kolaborasi
- Buat/hapus workspace, undang anggota terverifikasi berbasis persetujuan (email notifikasi), kelola keanggotaan.
- Peran pemilik/kolaborator dengan batas jumlah kolaborator.

### Transaksi
- Tambah/ubah/hapus transaksi: tanggal, jenis (masuk/keluar), nominal, deskripsi, rekening (opsional).
- **Pencarian 3 metode:** filter manual (rentang tanggal + kata kunci + rekening), prompt bahasa alami via AI, dan pencarian suara.
- **Input cepat AI:** rekam suara atau foto struk → isian formulir terisi otomatis, tetap dapat disunting sebelum disimpan.
- Statistik total masuk/keluar/selisih + rincian per rekening (termasuk baris Total semua rekening).
- Ekspor CSV (UTF-8, pemisah `;`) dan Excel `.xlsx` mengikuti filter yang aktif.
- Pagination terpusat 10 baris/halaman (urutan menurun); tampilan bawaan = seluruh transaksi.

### Rekening & log aktivitas
- CRUD rekening per workspace (nama unik, case-insensitive); menghapus rekening tidak menghapus transaksinya.
- Log aktivitas workspace (dilihat semua anggota) dan log tingkat akun (login & perubahan workspace) lengkap dengan **IP address + lokasi** (geolokasi ipwho.is dengan cache per IP).

### Panel admin (`/admin/`)
- **Ringkasan beranda:** statistik akun, workspace & transaksi, Pool AI, usage AI (bulan berjalan & 7 hari), keamanan login — plus daftar mini (pendaftaran terbaru, model AI bermasalah, percobaan masuk gagal terbaru).
- **Kelola pengguna:** peran (user/admin), verifikasi dua arah, hapus dengan konfirmasi; tab **Percobaan Masuk** menampilkan login gagal ke identifier tak dikenal.
- **Pool AI:** provider OpenAI-compatible (API key ter-mask), model + kapabilitas input/output + harga token, urutan prioritas fallback, aktif/nonaktif, uji kesehatan per model & massal.
- **Usage & Biaya:** agregat token & estimasi biaya per model (USD + Rupiah mengikuti kurs); pengaturan **Kurs USD→IDR**.

## Teknologi

| Lapisan | Keterangan |
|---|---|
| Bahasa | PHP ≥ 8.0 (disarankan 8.2), `declare(strict_types=1)` di semua berkas |
| Basis data | SQLite via PDO — `storage/keuangan.sqlite`, skema **auto-migrasi** dari `includes/db.php` saat request pertama |
| HTTP client | cURL (Google OAuth & provider AI OpenAI-compatible) |
| Email | SMTP Brevo (`includes/mail.php`) |
| UI | Tailwind via CDN + `assets/css/app.css` (design token), Font Awesome, JS vanilla `assets/js/app.js` |
| Dependensi | Tidak ada Composer/npm — pustaka ditulis sendiri di `includes/` |

Ekstensi PHP yang dibutuhkan: `pdo_sqlite`, `curl`, `mbstring`, `fileinfo`, `openssl`, `json`, `zlib`. Zona waktu proyek: **Asia/Jakarta (WIB)**.

## Menjalankan di Lokal

1. Salin konfigurasi lalu isi nilainya: `copy .env.example .env` (Windows).
2. Dari root proyek, jalankan:
   ```powershell
   php -S localhost:8080
   ```
3. Buka `http://localhost:8080`.

`.env` dan `storage/keuangan.sqlite` tidak pernah di-commit (lihat `.gitignore`); DB lokal beserta seluruh tabelnya dibuat otomatis saat request pertama.

### Variabel `.env`

| Variabel | Fungsi |
|---|---|
| `APP_ENV`, `APP_DEBUG` | mode aplikasi dan tampil/tidaknya pesan error |
| `DB_PATH` | jalur SQLite (default `storage/keuangan.sqlite`) |
| `MAIL_*` | SMTP Brevo untuk OTP & notifikasi undangan (`MAIL_ENABLED=true` untuk mengaktifkan) |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | kredensial Google OAuth |
| `GOOGLE_REDIRECT_URI` | URL callback; boleh beberapa URL dipisah koma — sistem memilih otomatis sesuai domain yang diakses |

## Struktur Direktori

```
├─ *.php                  # halaman root: index, login, register, verify, dashboard,
│                         #   workspace*, transaksi*, rekening-*, log, aktivitas,
│                         #   pengaturan, undangan, lupa-password, google-*, logout
├─ admin/                 # panel admin: index (ringkasan), user, login-gagal, ai*, ai-usage, ai-kurs
├─ assets/                # css/app.css + js/app.js
├─ includes/              # pustaka inti: init, env, db, auth, csrf, mail, google, workspace,
│                         #   transaksi, rekening, pagination, log, ai, ekspor, geo + partial head/header/footer
├─ storage/               # SQLite (akses web ditolak lewat .htaccess)
├─ tools/                 # skrip deploy lokal (tidak di-deploy)
├─ obsidian/              # dokumentasi Vault: PRD kerja, arsitektur, log progres
├─ referensi-desain/      # contoh desain (tidak di-deploy)
├─ .htaccess              # aturan Apache produksi (blokir berkas sensitif, anti-indeks)
├─ robots.txt             # Disallow: /
└─ keuangan-PRD.md        # arsip PRD (beku, tidak diubah)
```

## Keamanan

- Semua query memakai **prepared statement** (PDO); seluruh output di-escape (`e()`).
- Token **CSRF** untuk semua form mutasi; sesi cookie `httponly` + `SameSite Lax` (secure otomatis saat HTTPS).
- Sandi disimpan dengan `password_hash` (bcrypt); kode OTP disimpan ter-hash.
- Unggah foto struk divalidasi MIME via `finfo` (maks 8 MB) dan diproses tanpa disimpan permanen.
- Web server menolak akses `.env`, `*.md`, `*.log`, berkas SQLite, `.git`, serta daftar direktori (`Options -Indexes`).
- Pembatasan login 5 kegagalan/15 menit + pencatatan audit percobaan gagal (IP + lokasi).

## Deploy Produksi (FTP Manual)

Panduan lengkap: `obsidian/02-Architecture/Panduan Deploy FTP — Produksi.md`.

```powershell
# Tampilkan daftar berkas yang perlu diunggah sejak deploy terakhir
powershell -NoProfile -File tools\deploy-list.ps1

# Perbarui paket deploy (..\paket-deploy) + tandai commit terakhir yang di-deploy
powershell -NoProfile -File tools\deploy-list.ps1 -Sync
```

- Unggah **hanya berkas yang tercantum** via FileZilla, pertahankan struktur folder.
- **Jangan pernah menimpa `storage/keuangan.sqlite` di produksi** (berisi data nyata) — perubahan skema dimigrasi otomatis oleh aplikasi.
- `.env` hanya diperbarui bila kredensial/konfigurasi berubah.
- Tidak ikut di-deploy: `README.md`, `AGENTS.md`, `INSTRUCTIONS.md`, `keuangan-PRD.md`, `obsidian/`, `tools/`, `referensi-desain/`, `.env.example`, `.git/`.

## Lisensi & Privasi

Proyek privat keluarga — bukan untuk distribusi publik. Jangan menambahkan pengecualian indeks crawler pada `robots.txt`, meta halaman, maupun `.htaccess` (halaman yang dirayapi crawler dapat memicu pemakaian API AI berbiaya).
