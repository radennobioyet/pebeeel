<?php
/**
 * includes/koneksi.php
 * Memuat konfigurasi dari .env, lalu membuat koneksi PDO ke PostgreSQL.
 * Juga menetapkan zona waktu PHP ke Asia/Jakarta.
 *
 * File ini TIDAK boleh menampilkan output apa pun bila dibuka langsung.
 */

if (defined('_KONEKSI_LOADED')) return;
define('_KONEKSI_LOADED', true);

// ── Zona waktu ────────────────────────────────────────────────────────────────
date_default_timezone_set('Asia/Jakarta');

// ── Muat .env ─────────────────────────────────────────────────────────────────
(function () {
    $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (!file_exists($envFile)) {
        // Jangan bocorkan path lengkap ke browser
        error_log('[SnapRent] File .env tidak ditemukan: ' . $envFile);
        http_response_code(500);
        exit('Konfigurasi server tidak lengkap. Hubungi administrator.');
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key]   = $value;
            putenv("$key=$value");
        }
    }
})();

// ── Ambil nilai konfigurasi ───────────────────────────────────────────────────
$_db_host = $_ENV['DB_HOST'] ?? 'localhost';
$_db_port = $_ENV['DB_PORT'] ?? '5432';
$_db_name = $_ENV['DB_NAME'] ?? '';
$_db_user = $_ENV['DB_USER'] ?? '';
$_db_pass = $_ENV['DB_PASS'] ?? '';

// ── Koneksi PDO ───────────────────────────────────────────────────────────────
try {
    $dsn = "pgsql:host={$_db_host};port={$_db_port};dbname={$_db_name}";
    $pdo = new PDO($dsn, $_db_user, $_db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    // Pastikan sesi DB juga menggunakan zona waktu yang sama
    $pdo->exec("SET timezone = 'Asia/Jakarta'");
} catch (PDOException $e) {
    error_log('[SnapRent] Koneksi DB gagal: ' . $e->getMessage());
    http_response_code(500);
    exit('Tidak dapat terhubung ke database. Hubungi administrator.');
}

// Bersihkan variabel sensitif dari scope global
unset($_db_host, $_db_port, $_db_name, $_db_user, $_db_pass, $dsn);
