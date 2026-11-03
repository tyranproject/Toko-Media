<?php
// Profil WhatsApp: satu-satunya field yang bisa diubah dari halaman ini
// adalah nomor WhatsApp pengguna. Nomor ini dipakai tombol "Hubungi
// penjual via WhatsApp" di halaman produk. Tidak ada field profil lain
// yang diubah di batch ini.
if (!defined('MARKETPLACE_APP')) { http_response_code(403); exit('Forbidden'); }

// Normalisasi nomor HP Indonesia ke format wa.me (digit saja, 62...).
// Kembalikan: string digit bila valid, null bila kosong, false bila
// tidak valid. Aturan: buang semua non-digit; awalan 0 -> 62; awalan 8
// (tanpa 0, mis. 812...) -> 62; awalan 62 dipertahankan. Hasil akhir
// harus 62 + 8-13 digit (total 10-15 digit).
function normalize_whatsapp(string $raw): string|false|null
{
    $d = preg_replace('/\D+/', '', $raw) ?? '';
    if ($d === '') { return null; }
    if (str_starts_with($d, '0')) { $d = '62' . substr($d, 1); }
    elseif (str_starts_with($d, '8')) { $d = '62' . $d; }
    if (!preg_match('/^62[0-9]{8,13}$/', $d)) { return false; }
    return $d;
}

function profile_whatsapp_save(): void
{
    csrf_check(); $u = require_login();
    $norm = normalize_whatsapp(post('whatsapp'));
    if ($norm === false) {
        flash('error', 'Nomor WhatsApp tidak valid. Tulis nomor HP Indonesia, mis. 0812... atau 62812...');
        redirect(url('whatsapp'));
    }
    pdo()->prepare('UPDATE users SET whatsapp = ? WHERE id = ?')->execute([$norm, (int)$u['id']]);
    flash('success', $norm === null
        ? 'Nomor WhatsApp dihapus. Tombol WA tidak lagi tampil di produk Anda.'
        : 'Nomor WhatsApp disimpan.');
    redirect(url('whatsapp'));
}
