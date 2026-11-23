# KONTEKS AI — Proyek SnapRent (Sisi Admin)

Dokumen ini menyimpan seluruh konteks proyek, skema database, logika di level database, aturan bisnis, aturan teknis, dan kondisi aktual kode hasil verifikasi agar sesi AI berikutnya dapat langsung melanjutkan pekerjaan secara konsisten.

---

## 1. Informasi Umum Proyek
- **Nama Proyek**: SnapRent (LensaGO) — Website Penyewaan Kamera.
- **Stack Teknologi**: PHP 8.1 native (tanpa framework, tanpa library PHP eksternal) + PostgreSQL 14 (PDO) + JavaScript vanilla + JSON + Tailwind CSS (via CDN) + CSS kustom (`assets/css/admin.css`).
- **Lokasi Proyek**: `D:\Isya\Semester 3\PBL\snaprent`
- **Lingkungan Jalan**: Laragon, dijalankan dari root folder proyek dengan:
  ```bash
  php -S localhost:8000
  ```
  (`APP_URL=http://localhost:8000` di `.env`).
- **Struktur Folder Khusus**:
  - **Tidak ada** folder `public/`.
  - Folder skema & seed database bernama `sql/` (`sql/schema/` dan `sql/seeds/`).
  - Kredensial database berada di `.env` (**JANGAN** pernah menampilkan, mengulang isinya, atau meng-commit `.env`).

---

## 2. Batasan Wilayah Kerja (Scope)
- **Fokus Tugas Besar**: Mengerjakan **SISI ADMIN** secara bertahap.
- **JANGAN MENYENTUH Sisi Customer** kecuali diminta secara eksplisit:
  - `index.php` (root)
  - `keranjang/` (`isi.php`, `proses_tambah.php`, `proses_hapus.php`)
  - `booking/` (`detail.php`, `checkout.php`, `proses_checkout.php`, `sukses.php`)
  - `includes/header.php`, `includes/footer.php`
  - `assets/css/style.css`
  - `assets/js/katalog.js`, `assets/js/keranjang.js`, `assets/js/checkout.js`, `assets/js/app.js`
- **Aturan File**: Isi file yang sudah ada di proyek; **jangan** mengganti nama file atau memindahkan lokasinya.

---

## 3. Skema Database (`sql/schema/`)

Sumber kebenaran ada di `sql/schema/01_create_tables.sql` dan `sql/schema/02_constraints_indexes.sql`. Semua Primary Key tunggal memakai `INTEGER GENERATED ALWAYS AS IDENTITY`.

1. **`admin`**
   - `id_admin` (PK), `username` (VARCHAR(50) UNIQUE NOT NULL), `password` (VARCHAR(255) NOT NULL — hash `password_hash()`), `nama_lengkap` (VARCHAR(100) NOT NULL)
2. **`penyewa`**
   - `id_penyewa` (PK), `nama_penyewa` (VARCHAR(100) NOT NULL), `no_telepon` (VARCHAR(15) UNIQUE NOT NULL, constraint `ck_penyewa_telepon`: regex `^\+?[0-9]{8,14}$`)
3. **`kategori`**
   - `id_kategori` (PK), `nama_kategori` (VARCHAR(50) UNIQUE NOT NULL), `syarat_jaminan` (VARCHAR(255) NOT NULL)
4. **`barang`**
   - `id_barang` (PK), `id_kategori` (FK → `kategori` ON UPDATE CASCADE ON DELETE RESTRICT), `nama_barang` (VARCHAR(100) NOT NULL), `foto_barang` (VARCHAR(255) NULL), `kondisi_fisik` (VARCHAR(255) NULL), `harga_sewa_per_hari` (NUMERIC(12,2) NOT NULL >= 0), `stok` (INTEGER NOT NULL DEFAULT 1 >= 0), `status_barang` (VARCHAR(15) NOT NULL DEFAULT `'aktif'`, CHECK IN: `'aktif'`, `'rusak'`, `'maintenance'`, `'nonaktif'`)
5. **`transaksi`**
   - `id_transaksi` (PK)
   - `nomor_transaksi` (VARCHAR(20) UNIQUE NOT NULL — diisi otomatis oleh trigger: `YYYYMMDD` + urut 2 digit, misal `2026111101`)
   - `id_penyewa` (FK → `penyewa` ON UPDATE CASCADE ON DELETE RESTRICT)
   - `id_admin` (FK → `admin` NULL ON UPDATE CASCADE ON DELETE RESTRICT)
   - `id_denda` (FK → `denda` NULL ON UPDATE CASCADE ON DELETE RESTRICT)
   - `waktu_pemesanan` (TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)
   - `waktu_mulai_sewa` (TIMESTAMP NOT NULL)
   - `waktu_rencana_kembali` (TIMESTAMP NOT NULL, CHECK `> waktu_mulai_sewa`)
   - `waktu_ambil_aktual` (TIMESTAMP NULL — diisi saat admin klik "Mulai Sewa")
   - `waktu_aktual_kembali` (TIMESTAMP NULL, CHECK `IS NULL OR >= waktu_mulai_sewa`)
   - `jenis_pembayaran` (VARCHAR(10) NOT NULL, CHECK IN: `'transfer'`, `'qris'`, `'tunai'`)
   - `skema_pembayaran` (VARCHAR(10) NOT NULL, CHECK IN: `'dp_50'`, `'lunas'`)
   - `bukti_pembayaran` (VARCHAR(255) NOT NULL)
   - `setuju_syarat` (BOOLEAN NOT NULL, CHECK `setuju_syarat`)
   - `status_pembayaran` (VARCHAR(20) NOT NULL DEFAULT `'menunggu_verifikasi'`, CHECK IN: `'menunggu_verifikasi'`, `'belum_lunas'`, `'lunas'`, `'ditolak'`, `'hangus'`)
   - `status_transaksi` (VARCHAR(20) NOT NULL DEFAULT `'menunggu_verifikasi'`, CHECK IN: `'menunggu_verifikasi'`, `'menunggu_diambil'`, `'disewa'`, `'selesai'`, `'dibatalkan'`)
   - `total_tagihan` (NUMERIC(12,2) NOT NULL DEFAULT 0 >= 0) — turunan: `sewa + nominal_denda + biaya_kerusakan`
   - `nominal_bayar_awal` (NUMERIC(12,2) NOT NULL DEFAULT 0 >= 0) — turunan: DP 50% atau lunas saat checkout
   - `jumlah_dibayar` (NUMERIC(12,2) NOT NULL DEFAULT 0 >= 0) — akumulasi pembayaran terverifikasi
   - `nominal_denda` (NUMERIC(12,2) NOT NULL DEFAULT 0 >= 0) — denda keterlambatan
   - `biaya_kerusakan` (NUMERIC(12,2) NOT NULL DEFAULT 0 >= 0) — biaya perbaikan/penggantian
   - `catatan_kerusakan` (TEXT NULL)
   - Constraint tambahan (`02_constraints_indexes.sql`):
     - `ck_transaksi_selesai`: `status_transaksi <> 'selesai' OR waktu_aktual_kembali IS NOT NULL`
     - `ck_transaksi_selesai_lunas`: `status_transaksi <> 'selesai' OR status_pembayaran = 'lunas'`
     - `ck_transaksi_admin`: `status_transaksi IN ('menunggu_verifikasi', 'dibatalkan') OR id_admin IS NOT NULL`
     - `ck_transaksi_ambil`: `status_transaksi NOT IN ('disewa', 'selesai') OR waktu_ambil_aktual IS NOT NULL`
6. **`detail_transaksi`**
   - PK Gabungan: `(id_transaksi, id_barang)`
   - `id_transaksi` (FK → `transaksi` ON UPDATE CASCADE ON DELETE CASCADE)
   - `id_barang` (FK → `barang` ON UPDATE CASCADE ON DELETE RESTRICT)
   - `jumlah` (INTEGER NOT NULL DEFAULT 1 >= 1) — jumlah unit di keranjang
   - `harga_satuan` (NUMERIC(12,2) NOT NULL >= 0) — snapshot tarif per hari saat transaksi
   - `subtotal` (NUMERIC(12,2) NOT NULL >= 0) — turunan: `harga_satuan * jumlah * fn_hitung_hari(...)`
   - `kondisi_awal` (VARCHAR(255) NULL) — hasil cek fisik saat pengambilan
   - `kondisi_akhir` (VARCHAR(255) NULL) — hasil cek fisik saat pengembalian
7. **`jaminan`**
   - `id_jaminan` (PK)
   - `id_transaksi` (FK → `transaksi` ON UPDATE CASCADE ON DELETE CASCADE)
   - `jenis_jaminan` (VARCHAR(20) NOT NULL, CHECK IN: `'KTP'`, `'SIM'`, `'KTM'`, `'STNK'`, `'Paspor'`, `'Kartu Pelajar'`, `'Lainnya'`)
   - `no_identitas` (VARCHAR(30) NOT NULL)
   - `status_jaminan` (VARCHAR(15) NOT NULL DEFAULT `'ditahan'`, CHECK IN: `'ditahan'`, `'dikembalikan'`)
   - `waktu_diterima` (TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)
   - `waktu_dikembalikan` (TIMESTAMP NULL)
   - `catatan` (TEXT NULL)
   - Constraint & Indeks Unik:
     - `uq_jaminan_per_transaksi UNIQUE (id_transaksi, jenis_jaminan, no_identitas)`
     - `ck_jaminan_kembali`: `status_jaminan <> 'dikembalikan' OR waktu_dikembalikan IS NOT NULL`
     - `uq_jaminan_satu_ktp`: unique index `(id_transaksi) WHERE jenis_jaminan = 'KTP'`
8. **`denda`**
   - `id_denda` (PK)
   - `jenis_denda` (VARCHAR(15) NOT NULL, CHECK IN: `'nominal_tetap'`, `'sewa_harian'`)
   - `batas_min_jam` (INTEGER NOT NULL >= 0) — batas bawah eksklusif
   - `batas_max_jam` (INTEGER NULL, CHECK `IS NULL OR > batas_min_jam`) — batas atas inklusif; NULL = tanpa batas atas
   - `nilai_denda` (NUMERIC(12,2) NOT NULL DEFAULT 0 >= 0)
   - `keterangan` (VARCHAR(255) NULL)

### Data Seed (`sql/seeds/`)
- `01_kategori.sql`: 19 kategori peralatan kamera dengan syarat jaminan `'1 KTP asli + 2 kartu identitas pendukung'`.
- `02_barang.sql`: 173 item barang sesuai pricelist (masing-masing `stok` default `1`).
- `03_denda.sql`:
  1. `0 < jam <= 1`: `nominal_tetap`, `0` (Masa toleransi, tanpa denda)
  2. `1 < jam <= 3`: `nominal_tetap`, `50000` (Terlambat lebih dari 1 sampai 3 jam)
  3. `jam > 3` (`batas_max_jam IS NULL`): `sewa_harian`, `0` (Terlambat lebih dari 3 jam dihitung sewa 1 hari penuh)
- `04_admin.sql`: Akun default `username = 'admin'`, `password = 'admin123'` (bcrypt), `nama_lengkap = 'Administrator SnapRent'`.

---

## 4. Logika yang Sudah Ada di Database (`sql/schema/03_views_functions_triggers.sql`)
**PENTING**: Panggil dari PHP, **jangan** tulis ulang logika intinya di PHP, dan **jangan** ubah skema database.

1. **`fn_hitung_hari(p_mulai, p_kembali) RETURNS INTEGER`**
   - Menghitung lama sewa dalam hari, dibulatkan ke atas per 24 jam (`GREATEST(1, CEIL(selisih_detik / 86400))`).
2. **Trigger `trg_transaksi_before_insert` (`BEFORE INSERT ON transaksi`)**
   - Mengisi `nomor_transaksi` otomatis dengan format `YYYYMMDD` + urut 2 digit apabila NULL.
3. **`fn_stok_terpakai(p_id_barang, p_mulai, p_kembali, p_kecuali_transaksi DEFAULT NULL) RETURNS INTEGER` & `fn_sisa_stok(...) RETURNS INTEGER`**
   - Menghitung puncak pemakaian unit barang secara bersamaan pada transaksi berstatus `'menunggu_verifikasi'`, `'menunggu_diambil'`, atau `'disewa'`, serta mengembalikan sisa stok (`GREATEST(stok - fn_stok_terpakai(...), 0)`).
4. **Trigger `trg_detail_before_write` (`BEFORE INSERT OR UPDATE ON detail_transaksi`)**
   - Mengunci baris barang (`FOR NO KEY UPDATE`), menolak barang yang `status_barang <> 'aktif'` atau `jumlah > fn_sisa_stok(...)`.
   - Mengisi `harga_satuan` otomatis dari `barang.harga_sewa_per_hari` bila NULL.
   - Menghitung `subtotal = harga_satuan * jumlah * fn_hitung_hari(waktu_mulai_sewa, waktu_rencana_kembali)`.
5. **Trigger `trg_detail_after_write` (`AFTER INSERT OR UPDATE OR DELETE ON detail_transaksi`) & `fn_refresh_total(p_id_transaksi)`**
   - Menghitung ulang `total_tagihan = sewa + nominal_denda + biaya_kerusakan`.
   - Mengisi `nominal_bayar_awal` (50% untuk `dp_50` atau 100% untuk `lunas`) selama `status_transaksi = 'menunggu_verifikasi'`.
   - Menyinkronkan `status_pembayaran` (`'lunas'` bila `jumlah_dibayar >= total_tagihan`, selain itu `'belum_lunas'`) saat status `'menunggu_diambil'` atau `'disewa'`.
6. **Trigger `trg_transaksi_before_update` & `trg_transaksi_after_update` (`ON transaksi`)**
   - Menegakkan mesin status transaksi:
     - `menunggu_verifikasi` → `menunggu_diambil` | `dibatalkan`
     - `menunggu_diambil` → `disewa` | `dibatalkan` (pembatalan dari `menunggu_diambil` hanya diizinkan bila `CURRENT_DATE > waktu_mulai_sewa::DATE`)
     - `disewa` → `selesai`
   - Syarat masuk ke `'disewa'`: pengambilan wajib pada Hari-H (`waktu_ambil_aktual::DATE = waktu_mulai_sewa::DATE`), `status_pembayaran = 'lunas'`, dan minimal 3 jaminan berstatus `'ditahan'` termasuk minimal 1 `'KTP'`.
   - Syarat masuk ke `'selesai'`: `status_pembayaran = 'lunas'` dan tidak ada lagi jaminan berstatus `'ditahan'`.
   - Bila jadwal berubah, memeriksa ulang kecukupan stok seluruh barang dan menghitung ulang subtotal detail.
7. **Prosedur Alur Admin (`CALL ...`)**:
   - `sp_setujui_booking(p_id_transaksi, p_id_admin)`: mengubah `menunggu_verifikasi` → `menunggu_diambil`, mengisi `id_admin`, `jumlah_dibayar = nominal_bayar_awal`, dan `status_pembayaran` (`lunas`/`belum_lunas`).
   - `sp_batalkan_booking(p_id_transaksi, p_id_admin)`: membatalkan transaksi dari `menunggu_verifikasi` (`status_pembayaran = 'ditolak'`) atau `menunggu_diambil` setelah lewat Hari-H (`status_pembayaran = 'hangus'`).
   - `sp_catat_pembayaran(p_id_transaksi, p_nominal DEFAULT NULL)`: menambah `jumlah_dibayar` (jika `p_nominal` NULL, melunasi seluruh sisa tagihan) pada transaksi `menunggu_diambil`/`disewa`, lalu memanggil `fn_refresh_total`.
   - `sp_mulai_sewa(p_id_transaksi, p_waktu_ambil DEFAULT CURRENT_TIMESTAMP)`: mengubah `menunggu_diambil` → `disewa` dan mencatat `waktu_ambil_aktual`.
   - `sp_proses_pengembalian(p_id_transaksi, p_waktu_kembali DEFAULT CURRENT_TIMESTAMP)`: pada transaksi `'disewa'`, menghitung keterlambatan dalam jam, mencocokkan aturan `denda` (`nominal_tetap` → `nilai_denda`; `sewa_harian` → `SUM(harga_satuan * jumlah)`), mengisi `waktu_aktual_kembali`, `id_denda`, `nominal_denda`, lalu memanggil `fn_refresh_total` (status transaksi tetap `'disewa'` sampai diselesaikan).
   - `sp_catat_kerusakan(p_id_transaksi, p_id_barang, p_kondisi_akhir, p_biaya DEFAULT 0, p_status_barang DEFAULT NULL)`: mencatat kondisi akhir barang, memperbarui `barang.kondisi_fisik` (dan opsional `status_barang`), menambah `biaya_kerusakan` pada transaksi, lalu memanggil `fn_refresh_total`.
   - `sp_kembalikan_jaminan(p_id_transaksi)`: mengubah seluruh jaminan `'ditahan'` pada transaksi menjadi `'dikembalikan'` dan mengisi `waktu_dikembalikan = CURRENT_TIMESTAMP`.
   - `sp_selesaikan_transaksi(p_id_transaksi)`: mengubah status transaksi `'disewa'` (yang sudah memiliki `waktu_aktual_kembali`) menjadi `'selesai'`.
8. **Fungsi Katalog & Pelacakan**:
   - `fn_barang_tersedia(p_mulai, p_kembali, p_id_kategori DEFAULT NULL)`: mengembalikan `(id_barang, id_kategori, nama_barang, foto_barang, harga_sewa_per_hari, sisa_stok)` untuk barang `'aktif'` dengan `sisa_stok > 0`.
   - `fn_lacak_pesanan(p_nomor, p_no_telepon)`: mengembalikan rincian status dan tagihan pesanan berdasarkan kombinasi `nomor_transaksi` dan `no_telepon`.
9. **View**:
   - `v_dasbor_admin`: menampilkan `id_transaksi, nomor_transaksi, nama_penyewa, no_telepon, status_transaksi, status_pembayaran, skema_pembayaran, waktu_mulai_sewa, waktu_rencana_kembali, waktu_aktual_kembali, total_tagihan, jumlah_dibayar, sisa_tagihan, lewat_batas_ambil, mendekati_batas, jam_terlambat, level_baris ('hijau'|'merah'|'kuning'|'normal')`.
   - `v_reminder_pengembalian`: menampilkan `id_transaksi, nomor_transaksi, nama_penyewa, no_telepon, waktu_rencana_kembali, level_reminder, mendekati_batas, jam_terlambat` untuk transaksi `'disewa'` yang `waktu_aktual_kembali IS NULL`.
   - `v_status_barang_live`: menampilkan `id_barang, nama_barang, stok, unit_disewa, unit_menunggu_diambil, unit_tersedia, status_live ('tersedia'|'menunggu_diambil'|'disewa'|status_barang)`.
   - `v_pendapatan_harian` & `v_pendapatan_bulanan`: rekap jumlah transaksi dan `SUM(total_tagihan)` dari transaksi `'selesai'`.

---

## 5. Aturan Bisnis
1. **Jaminan per Transaksi**: Wajib **1 KTP asli + 2 kartu identitas pendukung** (total minimal 3 kartu identitas fisik, maksimal 1 KTP per transaksi). Dicatat oleh **ADMIN** dengan status awal `'ditahan'`. Saat barang dikembalikan, status jaminan diubah menjadi `'dikembalikan'` (beserta `waktu_dikembalikan`) melalui `CALL sp_kembalikan_jaminan(...)`.
2. **Alur Status Transaksi (7 Tahap)**:
   - Alur normal: `menunggu_verifikasi` → `menunggu_diambil` → `disewa` → `selesai`.
   - **Verifikasi Booking (`sp_setujui_booking`)**: Admin memverifikasi bukti pembayaran awal (`dp_50` atau `lunas`) dan menyetujui pesanan menjadi `'menunggu_diambil'`.
   - **Pembatalan (`sp_batalkan_booking`)**: Dari `'menunggu_verifikasi'` menghasilkan status pembayaran `'ditolak'`; dari `'menunggu_diambil'` hanya dapat dilakukan setelah lewat Hari-H (`CURRENT_DATE > waktu_mulai_sewa::DATE`) dan menghasilkan status pembayaran `'hangus'`.
   - **Pengambilan & Mulai Sewa (`sp_mulai_sewa`)**: Wajib dilakukan pada Hari-H tanggal mulai sewa, pembayaran harus sudah `'lunas'` (`sp_catat_pembayaran` bila awalnya `dp_50`), dan 3 kartu jaminan fisik (termasuk 1 KTP) sudah tercatat `'ditahan'`.
   - **Pengembalian & Penyelesaian**: Waktu kembali aktual dan denda dihitung melalui `CALL sp_proses_pengembalian(...)` (opsional `sp_catat_kerusakan(...)` bila ada kerusakan dan `sp_catat_pembayaran(...)` bila timbul tagihan tambahan), jaminan dikembalikan melalui `CALL sp_kembalikan_jaminan(...)`, lalu transaksi ditutup menjadi `'selesai'` melalui `CALL sp_selesaikan_transaksi(...)`.
3. **Perhitungan Denda**:
   - Keterlambatan (jam) `> batas_min_jam` dan `<= batas_max_jam`.
   - Tepat waktu (atau dalam masa toleransi 0–1 jam) = tanpa denda (`Rp 0`).
   - Jenis `nominal_tetap` = memakai `nilai_denda` (misal >1 s.d. 3 jam = `Rp 50.000`).
   - Jenis `sewa_harian` = jumlah `(harga_satuan * jumlah)` semua barang pada transaksi tersebut (1 hari penuh).
   - Untuk pratinjau denda di `pengembalian/kembali.php` (sebelum `sp_proses_pengembalian` dieksekusi), gunakan helper di `includes/denda.php` yang membaca aturan dari tabel `denda`.

---

## 6. Aturan Teknis (Wajib di Semua Langkah)
1. **Database**: PDO + prepared statement, `PDO::ERRMODE_EXCEPTION`, tanpa library PHP eksternal.
2. **Environment**: Konfigurasi dari `.env`; `.env` tidak boleh ter-commit; `.env.example` tanpa nilai rahasia.
3. **XSS Protection**: Semua output HTML wajib di-escape dengan `e()`.
4. **CSRF & HTTP Method**: Setiap form POST memakai token CSRF (`csrf_field()` & `csrf_verifikasi()`); semua aksi yang mengubah data (termasuk logout) **hanya lewat POST**.
5. **Autentikasi Halaman Admin**: Setiap halaman admin memanggil `includes/auth.php` di baris pertama (dan `wajib_login()`).
6. **Password**: Wajib menggunakan `password_hash()` dan `password_verify()`.
7. **Path & URL**: Path `require`/`require_once` memakai `__DIR__`; URL internal wajib lewat `base_url()`.
8. **Keamanan File Include**: Semua file di `includes/` tidak boleh menampilkan output apa pun bila dibuka langsung.
9. **Lokalisasi**: Antarmuka Bahasa Indonesia; format Rupiah `"Rp 350.000"` (`format_rupiah()`); format tanggal `"dd/mm/yyyy HH:mm"` (`format_tanggal()`); zona waktu `Asia/Jakarta`.
10. **Notifikasi**: Pesan sukses dan error memakai flash message session (`flash_set()` dan `flash_get()`).
11. **UI/UX**: Tailwind CSS via CDN + `assets/css/admin.css`, JavaScript vanilla, font `Poppins`.
12. **Asumsi**: Keputusan yang tidak tertulis dipilih opsi paling sederhana dan dicatat di `README.md` bagian `"Asumsi"`.
13. **Prosedur Akhir Langkah**: Di akhir setiap langkah: tampilkan daftar file yang diubah, cara menguji, lalu **BERHENTI**.

---

## 7. Kondisi Kode Saat Ini (Hasil Verifikasi Aktual Tahap C7)

### A. Status Modul Sisi Admin (SELESAI 100%)
- **`auth/`**: `login.php`, `proses_login.php`, `logout.php` (POST + CSRF, noindex, limit 5x gagal + blokir 60 detik).
- **`dashboard/`**: `index.php`, `data_dashboard.php` (Auto-refresh 60 detik, Jobgio monokrom + Quick Stats, grafik 12 bulan, reminder pengembalian).
- **`barang/`**: `list.php`, `tambah.php`, `proses_tambah.php`, `edit.php`, `proses_edit.php`, `hapus.php` (CRUD inventaris 173 unit, upload foto MIME finfo maks 2MB, safe delete nonaktif).
- **`transaksi/`**: `list.php`, `detail.php`, `proses_status.php`, `proses_jaminan.php` (Mesin status transaksi, inspeksi visual bukti bayar transfer/QRIS, pencatatan 3 kartu jaminan fisik: 1 KTP + 2 pendukung).
- **`pengembalian/`**: `kembali.php`, `proses_kembali.php`, `riwayat.php` (Pratinjau denda real-time, eksekusi `sp_proses_pengembalian`, riwayat selesai).
- **`laporan/`**: `index.php`, `cetak.php` (Laporan bulanan berbasis `waktu_aktual_kembali`, cetak print-friendly).
- **`pengaturan/`**: `index.php`, `proses_update.php`, `proses_update_toko.php` (Ubah password hash admin & pengaturan profil toko dinamis ke JSON).
- **Pengujian Unit Denda**: `tests/uji_denda.php` lulus 6/6 skenario.

### B. Status Modul Sisi Pelanggan (Tahap C1 - C6 SELESAI)
- **`index.php`**: Katalog lengkap, filter rentang tanggal sewa, pencarian nama, filter kategori, kartu pengganti foto, badge stok live (`fn_sisa_stok`), dan pagination.
- **`keranjang/`**:
  - `isi.php`: Render pop-up keranjang dengan empty state, kontrol ubah kuantitas unit barang, tombol ubah durasi hari sewa, subtotal & perkiraan total, dan tombol kosongkan keranjang.
  - `proses_tambah.php`: Validasi ketersediaan stok server-side via `ambil_sisa_stok()` dan penambahan kuantitas ke session.
  - `proses_hapus.php`: Penghapusan 1 item atau pengosongan seluruh isi keranjang.
- **`booking/`**:
  - `detail.php`: Pop-up modal `<dialog>` pratinjau spesifikasi alat, syarat jaminan kategori, status ketersediaan, dan tombol sewa.
  - `checkout.php`: Form biodata pelanggan, ringkasan belanja, pilihan pembayaran non-tunai (Transfer BCA & QRIS), input unggah bukti pembayaran dengan live image preview, syarat jaminan 1 KTP + 2 kartu pendukung, dan CSRF token.
  - `proses_checkout.php`: Validasi ketat nama (3-100 kar), normalisasi telepon regex, unggah bukti bayar (finfo maks 5MB), eksekusi atomik 1 transaksi database (`penyewa`, `transaksi`, `detail_transaksi`), penanganan bentrok trigger PostgreSQL, dan redirect ke `sukses.php`.
  - `sukses.php`: Tampilan struk invoice privat berbasis `$_SESSION['checkout_id_transaksi']` (format `SNP-%06d`), rincian sewa, status verifikasi admin, panduan serah terima fisik, dan tombol cetak bukti pesanan.
- **`tentang.php`**: Profil SnapRent, nilai layanan, panduan alur sewa 4 langkah, dan informasi operasional toko yang dinamis.
- **`bantuan.php`**: Pusat FAQ interaktif akordeon (keyboard-accessible), aturan denda progresif yang dibaca langsung dari tabel `denda`, panduan jaminan fisik, pembayaran, dan pembatalan pesanan.
- **`config/pengaturan_toko.json`**: Penyimpanan data Jam Operasional, Lokasi, dan Kontak toko yang dapat dikelola oleh Admin.
- **`assets/`**:
  - `css/style.css`: Palet warna konsisten dengan `admin.css`, responsif penuh di breakpoint 375px, 768px, 1280px.
  - `js/app.js`: Manajemen dialog modal aksesibel, trap focus, Esc key, dan toast notifikasi.
  - `js/katalog.js`, `js/keranjang.js`, `js/checkout.js`: Interaksi vanilla JS tanpa framework.

### C. Catatan Arsitektur & Fitur Tertunda
- **`lacak.php`**: Ditiadakan secara eksplisit berdasarkan keputusan bisnis pengguna, karena seluruh pembaruan status transaksi dikirimkan langsung oleh Admin melalui WhatsApp ke nomor penyewa. Fungsi database `fn_lacak_pesanan(p_nomor, p_no_telepon)` tetap tersedia sebagai utilitas internal jika sewaktu-waktu dibutuhkan.
- **Integritas Sintaks**: Seluruh berkas PHP lulus `php -l` tanpa kesalahan sintaks.
- **Kebersihan Database**: Data uji transaksi, penyewa, dan detail transaksi selalu di-rollback/dibersihkan setelah pengujian.
