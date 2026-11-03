<?php
// Admin: settings, suspend/unsuspend user, kategori.
if (!defined('MARKETPLACE_APP')) { http_response_code(403); exit('Forbidden'); }

function admin_settings_save(): void
{
    csrf_check(); require_admin();
    $allowed = ['platform_fee_percent', 'cod_fee_flat', 'min_withdrawal', 'auto_release_days',
        'download_expiry_hours', 'download_max_uses', 'site_title', 'max_image_mb', 'max_digital_mb'];
    // FIX-ALL B7: validasi rentang SEBELUM menyimpan apa pun. Bila satu
    // nilai di luar rentang, SEMUA ditolak (tidak ada yang tersimpan).
    $ranges = [
        'platform_fee_percent' => [0, 100], 'cod_fee_flat' => [0, null],
        'min_withdrawal' => [0, null], 'auto_release_days' => [1, null],
        'download_expiry_hours' => [1, null], 'download_max_uses' => [1, null],
        'max_image_mb' => [1, null], 'max_digital_mb' => [1, null],
    ];
    foreach ($ranges as $k => [$min, $max]) {
        if (!isset($_POST[$k])) { continue; }
        $v = trim((string)$_POST[$k]);
        if (!is_numeric($v) || (float)$v < $min || ($max !== null && (float)$v > $max)) {
            flash('error', 'Pengaturan "' . $k . '" di luar rentang yang diizinkan' . ($max !== null ? ' (' . $min . '–' . $max . ')' : ' (minimal ' . $min . ')') . '. Tidak ada pengaturan yang disimpan.');
            redirect(url('admin_settings'));
        }
    }
    $db = pdo();
    foreach ($allowed as $k) {
        if (!isset($_POST[$k])) { continue; }
        $v = trim((string)$_POST[$k]);
        if ($k !== 'site_title' && !is_numeric($v)) { continue; }
        $db->prepare('INSERT INTO settings (`key`, `value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)')->execute([$k, $v]);
    }
    flash('success', 'Pengaturan disimpan.'); redirect(url('admin_settings'));
}

function admin_user_status(string $status): void
{
    csrf_check(); $admin = require_admin();
    $id = (int)($_POST['id'] ?? 0);
    if ($id === (int)$admin['id']) { flash('error', 'Tidak bisa menangguhkan diri sendiri.'); redirect(url('admin_users')); }
    pdo()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, $id]);
    flash('success', 'Status user diubah.'); redirect(url('admin_users'));
}

function category_save(): void
{
    csrf_check(); require_admin();
    $nama = post('nama');
    if ($nama === '') { flash('error', 'Nama kategori wajib diisi.'); redirect(url('admin_categories')); }
    $slug = slugify($nama);
    $db = pdo();
    $st = $db->prepare('SELECT id FROM categories WHERE slug = ?'); $st->execute([$slug]);
    if ($st->fetch()) { $slug .= '-' . substr(bin2hex(random_bytes(3)), 0, 6); }
    $db->prepare('INSERT INTO categories (nama, slug, deskripsi) VALUES (?,?,?)')
        ->execute([$nama, $slug, post('deskripsi') ?: null]);
    flash('success', 'Kategori ditambahkan.'); redirect(url('admin_categories'));
}
