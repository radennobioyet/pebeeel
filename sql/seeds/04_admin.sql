-- =====================================================================
-- SnapRent - 04_admin.sql
-- Seed akun admin awal untuk pengembangan (development).
-- CATATAN PENTING:
-- Akun ini HANYA untuk lingkungan pengembangan (development).
-- Segera ganti password akun ini melalui halaman Pengaturan setelah login!
--
-- Username : admin
-- Password : admin123
-- Hash dihasilkan dengan: php -r "echo password_hash('admin123', PASSWORD_DEFAULT);"
-- =====================================================================

INSERT INTO admin (username, password, nama_lengkap)
VALUES (
    'admin',
    '$2y$10$HnAFoGqU9F6aKIPSV3APCOL9DTCt04y3ygF9JN4nkyombs55mfNA6',
    'Administrator SnapRent'
)
ON CONFLICT (username) DO UPDATE
    SET password     = EXCLUDED.password,
        nama_lengkap = EXCLUDED.nama_lengkap;
