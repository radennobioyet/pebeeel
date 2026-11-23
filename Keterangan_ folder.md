# Panduan Struktur Kode Proyek SnapRent (SnapRent)
    
## 📁 Bedah Struktur Folder & File

### 1. File Utama (Root)
*   **`index.php`**: Halaman Home/Katalog Customer. Menampilkan form filter tanggal sewa, pencarian nama peralatan, filter kategori, dan grid daftar peralatan yang siap disewa dengan perhitungan sisa stok dinamis.
*   **`tentang.php`**: Halaman Tentang Kami. Menampilkan profil rental SnapRent, keunggulan/nilai layanan, alur sewa 4 langkah mudah, serta alamat fisik, kontak telepon/WhatsApp, dan jam operasional dinamis yang dapat diatur oleh Admin.
*   **`bantuan.php`**: Halaman Pusat Bantuan & FAQ. Menampilkan panduan dan pertanyaan umum dengan akordeon aksesibel (ramah keyboard), memuat informasi jaminan (1 KTP asli + 2 identitas pendukung), ketentuan sewa 24 jam, batas toleransi dan tarif denda keterlambatan progresif yang dibaca langsung dari tabel database PostgreSQL `denda`, alur verifikasi manual bukti pembayaran, pembaruan status via WhatsApp, dan tata cara pembatalan pemesanan.
*(Catatan: Halaman pelacakan `lacak.php` ditiadakan sesuai keputusan alur bisnis sistem, karena seluruh pembaruan status transaksi dikirimkan langsung oleh Admin ke nomor WhatsApp pelanggan).*

### 2. Folder `includes/` (Jantung Sistem)
Folder ini berisi file yang dipakai berulang-ulang di berbagai halaman untuk mencegah duplikasi kode.
*   **`koneksi.php`**: Jembatan penghubung antara sistem PHP dan PostgreSQL. Wajib di-*include* di setiap file yang butuh akses ke database.
*   **`auth.php`**: Satpam sistem keamanan. Berisi logika `session_start()` dan pengecekan sesi admin untuk mencegah penyusup masuk tanpa *login*.
*   **`header.php`**: Potongan desain UI navigasi atas (Navbar) khusus untuk halaman depan (Customer).
*   **`footer.php`**: Potongan desain UI bagian bawah (Footer) untuk halaman depan (Customer), berisi hak cipta dan alamat toko.
*   **`header_admin.php`**: Potongan desain UI *Sidebar* (Menu Samping) dan *Navbar* atas khusus untuk halaman Dasbor Admin.
*   **`ketersediaan.php`**: Berisi fungsi logika *backend* untuk mengecek jadwal kamera (mencegah *double booking*).
*   **`denda.php`**: Berisi fungsi algoritma untuk menghitung denda keterlambatan progresif secara otomatis.
*   **`csrf.php`**: Perisai keamanan *form* (*Form Protection*). Mem-*generate* token acak untuk mencegah serangan dari luar (CSRF).
*   **`helpers.php`**: Berisi fungsi bantuan kecil buatan Backend (seperti `formatRupiah()` dan `formatTanggalIndo()`) untuk merapikan tampilan teks.
*   **`upload.php`**: Fungsi untuk menangani dan memvalidasi unggahan *file*/gambar (seperti membatasi ukuran maksimal 2MB dan memastikan ekstensi `.jpg` / `.png`).

### 3. Sisi Customer (Penyewaan)
*   **📂 `keranjang/`** (Mengurus sistem belanja sementara via *Session* dan dipanggil oleh Javascript tanpa *refresh* halaman).
    *   `isi.php` (Frontend): Tampilan HTML *list* barang yang disuntikkan ke dalam *pop-up* keranjang belanja.
    *   `proses_tambah.php` (Backend): Logika menyimpan ID barang yang diklik ke dalam *session* peramban.
    *   `proses_hapus.php` (Backend): Logika membuang ID barang dari dalam *session*.
*   **📂 `booking/`** (Mengurus proses *checkout* dan konfirmasi).
    *   `detail.php` (Frontend): *Pop-up* informasi spesifikasi barang dan syarat jaminan.
    *   `checkout.php` (Frontend): Form pengisian biodata penyewa dan pilihan metode pembayaran.
    *   `proses_checkout.php` (Backend): Logika menyimpan data penyewa, transaksi, dan detail transaksi ke database PostgreSQL sekaligus.
    *   `sukses.php` (Frontend): Halaman struk/invoice akhir yang menampilkan Nomor Resi/Pesanan.

### 4. Sisi Admin (Manajemen Inti)
*   **📂 `auth/`** (Mengurus keamanan *login* Admin).
    *   `login.php` (Frontend): UI form *login* (*Username* & *Password*).
    *   `proses_login.php` (Backend): Cek validitas *username* dan *password* ke database.
    *   `logout.php` (Backend): Menghancurkan tiket *login* (sesi) admin.
*   **📂 `dashboard/`** (Pusat Kendali - Halaman pertama setelah Admin berhasil *login*).
    *   `index.php` (Frontend): UI tabel transaksi yang "sedang disewa" beserta pewarnaan indikator (Kuning/Merah).
    *   `data_dashboard.php` (Backend): Fungsi logika *query* database untuk ditarik datanya ke dalam `index.php`.
*   **📂 `barang/`** (Fitur CRUD Inventaris Fisik). *Catatan: Dropdown kategori mengambil data langsung dari database, bukan folder.*
    *   `list.php` (Frontend): Menampilkan UI tabel daftar data inventaris fisik kamera.
    *   `tambah.php` (Frontend): Menampilkan UI Form inputan unit kamera baru.
    *   `edit.php` (Frontend): Menampilkan UI Form untuk mengubah rincian harga/nama kamera.
    *   `proses_tambah.php` (Backend): Menangkap *submit* dari form untuk di-*insert* ke database.
    *   `proses_edit.php` (Backend): Menangkap *submit* dari form untuk meng-*update* data di database.
    *   `hapus.php` (Backend): Logika menghapus atau menonaktifkan kamera.
*   **📂 `transaksi/`** (Mengelola pesanan baru yang masuk).
    *   `list.php` (Frontend): UI tabel daftar seluruh pesanan baru.
    *   `detail.php` (Frontend): UI form untuk admin mencatat rincian/nomor 3 jenis jaminan fisik (KTP/SIM/KTM) milik penyewa.
    *   `proses_status.php` (Backend): Logika mengubah status pesanan (misal dari "menunggu" jadi "disewa").
    *   `proses_jaminan.php` (Backend): Logika menyimpan data inputan dokumen jaminan ke database.

### 5. Sisi Admin (Akhir Siklus)
*   **📂 `pengembalian/`** (Muara akhir transaksi).
    *   `kembali.php` (Frontend): UI form rincian pengembalian, mencakup pratinjau telat jam dan total denda.
    *   `proses_kembali.php` (Backend): Eksekusi menyimpan jam aktual kembali, total denda, dan mengubah status menjadi "Selesai".
    *   `riwayat.php` (Frontend & Backend): Halaman daftar arsip khusus menampilkan transaksi yang sudah sukses berstatus "Selesai".
*   **📂 `laporan/`** (Laporan keuangan/pendapatan).
    *   `index.php` (Frontend): UI filter bulan dan tahun pendapatan.
    *   `cetak.php` (Backend): Tampilan cetak layar polos (hanya tabel) untuk hasil *query* agregasi total pendapatan.
*   **📂 `pengaturan/`** (Manajemen akun admin & profil toko).
    *   `index.php` (Frontend): UI form ubah *password* admin dan form informasi profil toko (Jam Operasional, Lokasi, Kontak).
    *   `proses_update.php` (Backend): Logika memproses pembaruan *password* di database.
    *   `proses_update_toko.php` (Backend): Logika menyimpan informasi toko ke `config/pengaturan_toko.json`.

### 6. Folder Pendukung & Penyimpanan Fisik
*   **📂 `assets/`**: Wilayah murni milik Frontend. Tempat berkumpulnya *file* `.css` (`style.css` dan `admin.css`), *file* `.js` (`app.js`, `katalog.js`, `keranjang.js`, `checkout.js`), dan aset visual statis.
*   **📂 `config/`**: Berisi file konfigurasi sistem, termasuk `pengaturan_toko.json` untuk informasi operasional toko yang dinamis.
*   **📂 `uploads/barang/`**: Tempat penyimpanan gambar inventaris peralatan yang diunggah oleh admin.
*   **📂 `uploads/pembayaran/`**: Tempat penyimpanan file bukti pembayaran (transfer bank / QRIS) yang diunggah penyewa saat checkout.
*   **📂 `sql/`**: Wilayah murni milik Database Administrator (DBA). Berisi skema tabel (`01_create_tables.sql`), batasan dan indeks (`02_constraints_indexes.sql`), view, fungsi, dan trigger PostgreSQL (`03_views_functions_triggers.sql`), serta seed awal di folder `sql/seeds/`.

---
