# SnapRent (LensaGO) — Website Penyewaan Kamera & Peralatan Fotografi

Aplikasi web penyewaan kamera dan perlengkapan fotografi berbasis **PHP 8.1 Native (tanpa framework)**, **PostgreSQL 14 (PDO)**, **JavaScript Vanilla**, dan **Tailwind CSS**. Seluruh aturan bisnis kritis (anti-bentrok jadwal per unit barang, kalkulasi subtotal & total tagihan otomatis, serta perhitungan denda pengembalian progresif) ditegakkan langsung di level database PostgreSQL melalui *constraint*, *view*, *function*, *trigger*, dan *stored procedure*.

---

## 1. Langkah Menjalankan dari Nol

### Prasyarat Lingkungan
- **PHP 8.1+** dengan ekstensi `pdo_pgsql`, `pgsql`, `fileinfo`, dan `mbstring` aktif (misalnya melalui Laragon).
- **PostgreSQL 12+** (direkomendasikan PostgreSQL 14).

### Tahap 1: Buat Database PostgreSQL
Buka terminal / `psql` atau pgAdmin, lalu buat database bernama `snaprent`:
```sql
CREATE DATABASE snaprent ENCODING 'UTF8';
```

### Tahap 2: Jalankan Skema & Data Seed Secara Berurutan
Dari root folder proyek (`D:\Isya\Semester 3\PBL\snaprent`), jalankan seluruh file di `sql/schema/` terlebih dahulu, diikuti oleh `sql/seeds/` secara berurutan:

```powershell
# 1. Jalankan Skema Database (Tabel, Constraint/Index, View/Function/Trigger/Procedure)
psql -U postgres -d snaprent -f sql/schema/01_create_tables.sql
psql -U postgres -d snaprent -f sql/schema/02_constraints_indexes.sql
psql -U postgres -d snaprent -f sql/schema/03_views_functions_triggers.sql

# 2. Jalankan Data Awal (Seeds)
psql -U postgres -d snaprent -f sql/seeds/00_admin.sql
psql -U postgres -d snaprent -f sql/seeds/01_kategori.sql
psql -U postgres -d snaprent -f sql/seeds/02_barang.sql
psql -U postgres -d snaprent -f sql/seeds/03_denda.sql
psql -U postgres -d snaprent -f sql/seeds/04_admin.sql
```

### Tahap 3: Konfigurasi File `.env`
Salin file `.env.example` menjadi `.env`:
```powershell
Copy-Item .env.example .env
```
Sesuaikan isi `.env` dengan kredensial PostgreSQL lokal Anda (`DB_HOST`, `DB_PORT`, `DB_NAME=snaprent`, `DB_USER`, `DB_PASS`, dan `APP_URL=http://localhost:8000`). **Jangan** meng-commit file `.env` ke repositori Git.

### Tahap 4: Jalankan Built-in Web Server PHP
Dari root folder proyek, jalankan server pengembangan PHP:
```powershell
php -S localhost:8000
```
- **Halaman Katalog Customer**: `http://localhost:8000/`
- **Halaman Login Admin**: `http://localhost:8000/auth/login.php`
- **Dashboard Admin**: `http://localhost:8000/dashboard/index.php`

### Tahap 5: Pengujian Otomatis Perhitungan Denda (CLI)
Untuk memverifikasi kesesuaian fungsi pratinjau denda PHP (`hitung_denda_preview`) dengan stored procedure PostgreSQL (`sp_proses_pengembalian`), jalankan:
```powershell
php tests/uji_denda.php
```

---

## 2. Akun Admin Awal (Pengembangan)

Terdapat akun administrator awal dari file seed yang **hanya ditujukan untuk pengembangan** (segera ganti password melalui menu **Pengaturan** setelah login):

| Sumber Seed | Username | Password Awal | Nama Lengkap |
|---|---|---|---|
| `sql/seeds/04_admin.sql` | `admin_dev` | `admin123` | `Admin Pengembangan` |
| `sql/seeds/00_admin.sql` | `admin` | `admin` | `Administrator` |

> **Catatan**: Jika `04_admin.sql` dijalankan setelah `00_admin.sql` dan memiliki `ON CONFLICT (username) DO UPDATE`, akun `admin` juga diperbarui menggunakan hash dari `admin123`.

---

## 3. Daftar Fitur Sisi Admin yang Selesai

1. **Autentikasi & Keamanan Sesi (`auth/`)**
   - Login admin (`auth/login.php` & `auth/proses_login.php`) menggunakan `password_verify()`, regenerasi session ID (`session_regenerate_id(true)`), pesan gagal generik (*"Username atau password salah"*), serta pembatasan **maksimal 5 kali percobaan gagal berturut-turut** dengan penguncian sesi selama **60 detik**.
   - Logout (`auth/logout.php`) wajib melalui metode `POST` + token CSRF, menghapus data session dan cookie session (`HttpOnly`, `SameSite=Strict`).
2. **Dasbor Admin Real-Time (`dashboard/`)**
   - Kartu ringkasan statistik cepat (Booking Menunggu, Unit Kamera Aktif, Sedang Disewa, Pendapatan Bulan Ini, Jatuh Tempo $\le 1$ Jam, Terlambat Kembali).
   - Grafik batang ganda 12 bulan terakhir (Pendapatan vs Transaksi Masuk).
   - Tabel padat **Barang Sedang Disewa** dari view `v_reminder_pengembalian` dengan pewarnaan baris kuning (*Hampir jatuh tempo*) dan merah (*Terlambat* beserta jam keterlambatan) serta tombol langsung **Proses Pengembalian**.
   - Daftar & tabel **Booking Menunggu Verifikasi** (5 terbaru).
   - Pembaruan otomatis (*auto-refresh*) setiap **60 detik** via `fetch` ke `dashboard/data_dashboard.php` tanpa memuat ulang halaman.
3. **Kelola Inventaris Barang (`barang/`)**
   - Daftar seluruh barang (173 unit seed) dengan pencarian nama, filter kategori, filter status (`aktif`, `rusak`, `maintenance`, `nonaktif`), dan paginasi 20 baris per halaman.
   - Tambah & ubah barang lengkap dengan pratinjau foto dan validasi unggahan sisi server (`includes/upload.php`: maksimal 2 MB, hanya MIME `image/jpeg` dan `image/png` melalui `finfo`).
   - Penghapusan cerdas (`barang/hapus.php` via POST + CSRF): bila barang belum pernah dipakai di `detail_transaksi`, baris dan file foto dihapus fisik; bila sudah pernah dipakai (terikat foreign key `RESTRICT`), status barang otomatis diubah menjadi `nonaktif` tanpa menghapus riwayat transaksi.
4. **Pengelolaan Transaksi & Jaminan Fisik (`transaksi/` & `includes/ketersediaan.php`)**
   - Daftar seluruh transaksi (`transaksi/list.php`) dengan penekanan visual pada status `menunggu`, filter status, pencarian nama/telepon/kode `#SR-XXXX`, dan paginasi 20 baris.
   - Pencatatan 3 kartu identitas jaminan fisik (`transaksi/detail.php` & `transaksi/proses_jaminan.php`): Baris 1 dikunci wajib `KTP`, Baris 2 dan 3 kartu pendukung (`KTP`/`SIM`/`KTM`/`Kartu Pelajar`/`Lainnya`) dalam satu transaksi database.
   - Mesin status transaksi (`transaksi/proses_status.php`): validasi ketat di sisi server (`menunggu -> disetujui -> disewa`, serta pembatalan hanya dari `menunggu` atau `disetujui`). Persetujuan (`disetujui`) mewajibkan minimal 3 kartu jaminan (termasuk minimal 1 KTP) dan `status_pembayaran = 'lunas'`. Menangkap error trigger anti-bentrok jadwal PostgreSQL dan menampilkan pesan manusiawi.
   - Pembungkus fungsi ketersediaan (`includes/ketersediaan.php`) untuk `fn_ada_bentrok` dan `fn_barang_tersedia`.
5. **Proses Pengembalian & Perhitungan Denda (`pengembalian/`, `includes/denda.php`, `tests/uji_denda.php`)**
   - Fungsi `hitung_denda_preview()` di `includes/denda.php` dengan logika identik `sp_proses_pengembalian` (`0 < jam <= 1`: Rp 0 toleransi; `1 < jam <= 3`: Rp 50.000; `jam > 3`: sewa 1 hari penuh = `SUM(harga_satuan)` seluruh barang pada transaksi).
   - Form pengembalian (`pengembalian/kembali.php`) dengan pratinjau denda *real-time* saat input waktu kembali diubah, pengingat daftar jaminan, dan opsi pengembalian kartu identitas.
   - Eksekusi pengembalian (`pengembalian/proses_kembali.php`) memanggil `CALL sp_proses_pengembalian(:id, :waktu)` dan memperbarui status jaminan menjadi `'dikembalikan'` dalam satu transaksi database, lalu menampilkan rincian denda final yang dibaca langsung dari database.
   - Halaman riwayat transaksi selesai (`pengembalian/riwayat.php`) dengan pencarian, filter rentang tanggal kembali, ringkasan denda, dan tautan detail hanya-baca.
6. **Laporan Pendapatan & Pengaturan Akun (`laporan/` & `pengaturan/`)**
   - Laporan bulanan (`laporan/index.php`) dengan filter bulan & tahun berdasarkan `waktu_aktual_kembali`, 4 kartu ringkasan (Jumlah Transaksi Selesai, Total Pendapatan Sewa, Total Denda, Total Pendapatan Akhir), tabel rincian transaksi selesai, dan tabel ringkasan per kategori barang.
   - Halaman cetak (`laporan/cetak.php`) bersih tanpa sidebar dengan CSS `@media print` rapi dan angka yang identik dengan halaman laporan.
   - Pengaturan ubah password admin (`pengaturan/index.php` & `pengaturan/proses_update.php`) dengan verifikasi `password_verify()`, syarat minimal 8 karakter, penyimpanan `password_hash()`, dan `session_regenerate_id(true)`.

---

## 4. Asumsi yang Digunakan Selama Pengerjaan

1. **Desain Antarmuka Dasbor**: Menggabungkan gaya monokrom *Jobgio* (sidebar putih dengan menu *pill* hitam, kapsul statistik vertikal, dan grafik 12 bulan) dengan seluruh spesifikasi fungsional Tahap 3 (tabel padat *Barang Sedang Disewa* berindikator kuning/merah dari `v_reminder_pengembalian`, *Booking Menunggu Verifikasi*, dan *auto-refresh* JSON 60 detik).
2. **Pencatatan Ulang Jaminan Sebelum Selesai**: Selama transaksi masih berstatus `menunggu`, `disetujui`, atau `disewa`, penyimpanan form 3 baris jaminan di `transaksi/proses_jaminan.php` memperbarui daftar jaminan transaksi tersebut secara atomik (`DELETE` lalu `INSERT` 3 baris dalam satu transaksi DB) agar selalu menghormati constraint `uq_jaminan_per_transaksi`.
3. **Akses Menu Pengembalian Tanpa Parameter `id`**: Karena menu sidebar **Pengembalian** mengarah ke `pengembalian/kembali.php` tanpa parameter `?id=`, halaman tersebut menampilkan daftar pemilih transaksi yang sedang berstatus `disewa` bila dibuka tanpa `?id=`, dan langsung membuka formulir pengembalian bila dibuka dengan `?id=`.
4. **Rincian Final Setelah Pengembalian**: Setelah `pengembalian/proses_kembali.php` selesai mengeksekusi `CALL sp_proses_pengembalian`, sistem mengarahkan admin ke `pengembalian/kembali.php?id=X&hasil=1` (pola *Post/Redirect/Get*) yang membaca rincian denda dan total tagihan akhir langsung dari tabel `transaksi` dan `denda` di database agar aman dari pengiriman ulang form (*resubmission*) saat halaman di-*refresh*.
5. **Dasar Periode Laporan Pendapatan**: Sesuai spesifikasi Tahap 7, seluruh angka pada `laporan/index.php` dan `laporan/cetak.php` difilter berdasarkan bulan dan tahun dari `waktu_aktual_kembali` pada transaksi yang berstatus `selesai`.
6. **Fondasi Sisi Pelanggan (Tahap C1)**:
   - Sesuai aturan bisnis bahwa penyewa tidak memiliki akun login (`guest checkout` berbasis `no_telepon`), ikon akun pada navbar wireframe tidak ditampilkan di `includes/header.php`. Satu-satunya tautan menuju halaman login admin (`auth/login.php`) diletakkan di baris paling bawah `includes/footer.php` berukuran `12px`.
   - Alamat dan kontak toko pada `includes/footer.php` menggunakan teks pengganti jelas `[isi alamat toko]` dan `[isi kontak toko]` agar dapat disesuaikan dengan lokasi operasional toko.
8. **Katalog & Filter Ketersediaan (Tahap C2)**:
   - Filter rentang waktu di katalog memanggil fungsi PostgreSQL `fn_sisa_stok()` sehingga hanya barang yang benar-benar memiliki sisa stok $\ge 1$ yang ditampilkan atau diizinkan disewa.
   - Apabila kolom `foto_barang` kosong atau file gambar fisik belum ada di `uploads/barang/`, antarmuka otomatis menampilkan kartu pengganti elegan dengan ikon kamera SVG monokrom.
9. **Pop-up Detail & Keranjang Multi-Unit (Tahap C3)**:
   - Data keranjang disimpan dalam sesi peramban (`$_SESSION['keranjang']`) dengan struktur item per `id_barang` beserta kuantitasnya.
   - Pelanggan diizinkan menyewa lebih dari 1 unit barang yang sama selama tidak melampaui sisa stok riil pada rentang tanggal terpilih.
   - Pengaturan durasi sewa diletakkan di dalam keranjang belanja dengan pembulatan kelipatan 24 jam (1 hari sewa). Waktu jam mulai sewa tidak dipilih di keranjang karena perhitungan jam sewa aktual baru dimulai saat Admin menekan tombol *Mulai Sewa* di panel admin saat serah terima barang fisik.
10. **Checkout Atomik & Keamanan Halaman Sukses (Tahap C4)**:
    - Checkout diproses dalam satu transaksi database tunggal (`BEGIN ... COMMIT`). Jika ada bentrok stok di level database (melalui trigger `trg_detail_transaksi_before_insert`), sistem melakukan rollback penuh dan mengembalikan pelanggan ke halaman keranjang dengan pesan kesalahan yang jelas dan manusiawi.
    - Format kode pesanan di tampilan antarmuka diseragamkan dengan format `SNP-%06d` (berdasarkan `id_transaksi`).
    - Halaman `booking/sukses.php` hanya dapat diakses melalui `$_SESSION['checkout_id_transaksi']` yang langsung dibersihkan setelah dibaca. URL `sukses.php` tidak menerima parameter `?id=` agar pihak ketiga tidak dapat mengintip rincian pesanan pelanggan lain.
11. **Metode Pembayaran Non-Tunai & Unggah Bukti Bayar**:
    - Opsi pembayaran tunai ditiadakan dari form checkout pelanggan; pilihan yang tersedia adalah **Transfer Bank (BCA)** dan **QRIS**.
    - Pelanggan wajib mengunggah file bukti pembayaran (maksimal 5 MB, format JPG/PNG/WEBP/PDF yang divalidasi MIME type-nya menggunakan `finfo`). File disimpan di `uploads/pembayaran/`. Admin dapat melihat pratinjau gambar dan link bukti bayar tersebut langsung di `transaksi/detail.php`.
12. **Konfigurasi Profil Toko Dinamis oleh Admin**:
    - Pengaturan Jam Operasional, Lokasi/Alamat Lengkap, dan Kontak WhatsApp/Telepon dapat dikonfigurasi melalui panel admin di `pengaturan/index.php`.
    - Nilai pengaturan disimpan secara persisten dalam format JSON di `config/pengaturan_toko.json` tanpa memodifikasi skema SQL `sql/`, lalu disuntikkan secara dinamis ke `tentang.php`, `bantuan.php`, dan `includes/footer.php`.
13. **Peniadaan Halaman Pelacakan (`lacak.php`)**:
    - Sesuai rancangan alur bisnis SnapRent, halaman `lacak.php` ditiadakan karena seluruh pembaruan status transaksi (verifikasi bukti pembayaran, persetujuan sewa, persiapan barang, pengingat jadwal sewa, hingga pengembalian) dikirimkan secara langsung dan personal oleh Admin ke nomor WhatsApp penyewa yang terdaftar saat checkout.
    - Pelanggan cukup menyimpan struk bukti pesanan berisikan Kode Pesanan `SNP-%06d` dari `booking/sukses.php` untuk ditunjukkan saat pengambilan fisik barang di toko. Fungsi database `fn_lacak_pesanan` tetap dipertahankan sebagai utilitas lookup internal jika diperlukan.
