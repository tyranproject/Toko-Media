<?php
// ============================================================================
// Marketplace Bekasi Utara — Contoh konfigurasi (INCREMENT 1)
// CARA PAKAI: salin file ini menjadi config.php DI SERVER (bukan di repo),
// lalu isi nilai sebenarnya. JANGAN PERNAH commit config.php berisi nilai asli.
// File config.php WAJIB dijaga oleh konstanta MARKETPLACE_APP (lihat bawah):
// akses langsung ke config.php mengembalikan 403.
// ============================================================================
if (!defined('MARKETPLACE_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

// --- Database (isi sesuai panel hosting; nama DB memakai prefix akun) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'NAMADB');
define('DB_USER', 'USERDB');
define('DB_PASS', 'PASSDB');

// --- Aplikasi ---
define('APP_URL', 'https://domain.com'); // URL publik aplikasi
define('APP_TIMEZONE', 'Asia/Jakarta');
define('SESSION_NAME', 'mbu_session');

// --- Kunci proteksi endpoint (buat nilai acak yang panjang di server) ---
define('CRON_KEY', 'GANTICRONKET');
define('SETUP_KEY', 'GANTISETUPKEY');

// --- DOKU (QRIS) ---
// MUST-CONFIRM: base URL endpoint, path API, dan skema signature WAJIB
// divalidasi terhadap dokumentasi DOKU sandbox SEBELUM produksi.
// Nilai default di sini sengaja KOSONG: tanpa konfigurasi, pembuatan
// pembayaran DOKU akan gagal dengan pesan yang jelas (bukan diam-diam).
define('DOKU_MODE', 'sandbox'); // 'sandbox' | 'production'
define('DOKU_BASE_URL', 'https://api-sandbox.doku.com');    // MUST-CONFIRM, mis. URL sandbox DOKU
define('DOKU_CLIENT_ID', 'BRN-=====-======');   // MUST-CONFIRM: dari dashboard DOKU
define('DOKU_SECRET_KEY', 'SK-===');  // MUST-CONFIRM: dari dashboard DOKU
define('DOKU_MERCHANT_ID', ''); // MUST-CONFIRM: bila diperlukan DOKU

// --- Penyimpanan file digital (server sendiri, bukan Google Drive) ---
// Folder penyimpanan file digital. UTAMAKAN folder DI LUAR docroot, mis.
// /home/akun/mbu-digital/ — di luar docroot tidak ada URL yang bisa
// mengakses file. Unduhan tetap HANYA lewat download.php + token.
// Bila konstanta ini kosong, aplikasi memakai folder fallback di dalam
// docroot (digital-files/) yang diproteksi .htaccess "Require all denied"
// (dibuat otomatis oleh app/storage.php). Bila konstanta ini diisi tapi
// foldernya tidak bisa dibuat/ditulis, upload gagal dengan pesan jelas.
define('DIGITAL_FILES_DIR', '/home/vipse877/mbu-digital/');
