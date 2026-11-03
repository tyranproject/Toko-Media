<?php
// ============================================================================
// Unduhan produk digital (?token=...).
// Validasi: hash SHA-256 token cocok + milik pembeli (login) + belum
// kedaluwarsa + uses < max_uses + status active. Pemakaian ditambah dengan
// SELECT ... FOR UPDATE di dalam transaksi. File di-stream dari
// penyimpanan lokal server (proxy) — TIDAK PERNAH ada URL publik ke file.
// Kolom products.drive_file_id kini menyimpan NAMA FILE INTERNAL di disk
// (32 heksadesimal + ".bin", lihat app/storage.php), bukan ID layanan luar;
// nama kolom dipertahankan agar tidak perlu ALTER TABLE.
// ============================================================================
define('MARKETPLACE_APP', true);
require __DIR__ . '/app/config.php';
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Jakarta');
session_name(defined('SESSION_NAME') ? SESSION_NAME : 'mbu_session');
session_start();
require __DIR__ . '/app/db.php';
require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/storage.php';

$token = (string)($_GET['token'] ?? '');
if ($token === '') { http_response_code(400); exit('Token tidak ada.'); }
$user = current_user();
if (!$user) { http_response_code(403); exit('Silakan login dulu.'); }

$db = pdo();
$db->beginTransaction();
try {
    $st = $db->prepare('SELECT t.*, p.drive_file_id, p.drive_file_name FROM download_tokens t JOIN products p ON p.id = t.product_id WHERE t.token_hash = ? FOR UPDATE');
    $st->execute([hash('sha256', $token)]);
    $t = $st->fetch();
    if (!$t) { throw new RuntimeException('Token tidak ditemukan.'); }
    if ((int)$t['buyer_id'] !== (int)$user['id']) { throw new RuntimeException('Token bukan milik Anda.'); }
    if ($t['status'] !== 'active') { throw new RuntimeException('Token tidak aktif.'); }
    if (strtotime($t['expires_at']) <= time()) { throw new RuntimeException('Token kedaluwarsa.'); }
    if ((int)$t['uses'] >= (int)$t['max_uses']) { throw new RuntimeException('Batas pemakaian token habis.'); }
    if (empty($t['drive_file_id'])) { throw new RuntimeException('File produk belum tersedia.'); }
    // FIX-ALL C11: pastikan file ADA dan bisa dibaca SEBELUM uses di-commit.
    // Bila file hilang, tolak TANPA menghabiskan satu jatah unduhan.
    if (!(new LocalStorage())->exists($t['drive_file_id'])) { throw new RuntimeException('File produk tidak ditemukan di server. Jatah unduhan Anda tidak berkurang — hubungi admin.'); }
    $db->prepare('UPDATE download_tokens SET uses = uses + 1 WHERE id = ?')->execute([$t['id']]);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    http_response_code(403);
    exit($e->getMessage());
}

// Nama asli hanya dipakai untuk header unduhan; dibersihkan dari karakter
// yang bisa merusak header (tanda kutip, CR/LF) dan dari komponen path.
$fileName = basename((string)($t['drive_file_name'] ?: 'download'));
$fileName = str_replace(['"', "\r", "\n"], '', $fileName);
if ($fileName === '') { $fileName = 'download'; }
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: no-store');
try {
    (new LocalStorage())->stream($t['drive_file_id']);
} catch (Throwable $e) {
    // Header sudah terkirim; laporkan lewat log server.
    error_log('Local storage stream error: ' . $e->getMessage());
}
