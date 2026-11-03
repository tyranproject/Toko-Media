<?php
// Helper bersama: escape, uang, auth, CSRF, settings, dan operasi dompet.
// Semua nama kolom di sini merujuk persis ke database/schema.sql.
if (!defined('MARKETPLACE_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

// --- Output & format -------------------------------------------------------
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function rp($v): string { return 'Rp ' . number_format((float)$v, 0, ',', '.'); }
function money($v): float { return round((float)$v, 2); }

// --- Flash & redirect ------------------------------------------------------
function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg]; }
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function redirect(string $url): void { header('Location: ' . $url); exit; }
function url(string $page, array $q = []): string {
    $qs = array_merge(['page' => $page], $q);
    return 'index.php?' . http_build_query($qs);
}
function now(): string { return date('Y-m-d H:i:s'); }

// --- CSRF ------------------------------------------------------------------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    $sent = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)$sent)) {
        http_response_code(403);
        exit('Token CSRF tidak valid.');
    }
}
function post(string $k, string $def = ''): string { return trim((string)($_POST[$k] ?? $def)); }

// --- Auth ------------------------------------------------------------------
// FIX-ALL B12: sesi PHP saja tidak cukup — token sesi harus cocok dengan
// baris sessions (hash + belum kedaluwarsa). Tanpa ini, menghapus baris
// sessions (logout semua perangkat) tidak akan berpengaruh apa pun.
// Efek samping yang disengaja: sesi login yang dibuat SEBELUM patch ini
// (tanpa session_token) akan diminta login ulang satu kali.
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) { return null; }
    $st = pdo()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$_SESSION['user_id']]);
    $u = $st->fetch();
    if (!$u) { return null; }
    $raw = $_SESSION['session_token'] ?? '';
    if ($raw === '') { return null; }
    $st = pdo()->prepare('SELECT id FROM sessions WHERE user_id = ? AND token_hash = ? AND expires_at > NOW()');
    $st->execute([(int)$u['id'], hash('sha256', (string)$raw)]);
    if (!$st->fetch()) {
        // Sesi dicabut (logout semua perangkat / logout di perangkat lain
        // yang menghapus baris ini) atau kedaluwarsa: paksa logout.
        $_SESSION = [];
        return null;
    }
    return $u;
}

// FIX-ALL C4: token idempotensi sekali pakai untuk form checkout/top-up/
// bayar-ulang. Token dibuat saat form dirender (bila belum ada), disimpan
// di session, dan DIHAPUS saat pertama dipakai. Submit ulang (double-click
// lintas perangkat, refresh, back-button) dengan token yang sama ditolak
// tanpa membuat order/top-up kedua. Melengkapi (bukan mengganti) kunci
// FOR UPDATE + pengecekan status di controller.
function form_token(string $key): string {
    if (empty($_SESSION['form_token'][$key])) { $_SESSION['form_token'][$key] = bin2hex(random_bytes(32)); }
    return $_SESSION['form_token'][$key];
}
function consume_form_token(string $key): bool {
    $sent = (string)($_POST['form_token'] ?? '');
    $expected = (string)($_SESSION['form_token'][$key] ?? '');
    if ($sent === '' || $expected === '' || !hash_equals($expected, $sent)) { return false; }
    unset($_SESSION['form_token'][$key]);
    return true;
}
function form_token_field(string $key): string {
    return '<input type="hidden" name="form_token" value="' . e(form_token($key)) . '">';
}
function require_login(): array {
    $u = current_user();
    if (!$u) { flash('error', 'Silakan login dulu.'); redirect(url('login')); }
    if ($u['status'] === 'suspended') {
        session_unset(); session_destroy();
        flash('error', 'Akun Anda ditangguhkan. Hubungi admin.');
        redirect(url('login'));
    }
    return $u;
}
function require_admin(): array {
    $u = require_login();
    if ($u['role'] !== 'admin') { http_response_code(403); exit('Hanya admin.'); }
    return $u;
}
function is_admin(?array $u): bool { return $u && $u['role'] === 'admin'; }

// --- Settings (dengan fallback nilai default sesuai schema.sql) --------------
function setting(string $key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (pdo()->query('SELECT `key`, `value` FROM settings') as $r) { $cache[$r['key']] = $r['value']; }
        } catch (Throwable $e) { /* tabel belum ada: pakai fallback */ }
    }
    if (array_key_exists($key, $cache)) { return $cache[$key]; }
    $defaults = [
        'platform_fee_percent' => '10', 'cod_fee_flat' => '5000',
        'min_withdrawal' => '20000', 'auto_release_days' => '3',
        'download_expiry_hours' => '24', 'download_max_uses' => '3',
        'site_title' => 'Marketplace Bekasi Utara',
        'max_image_mb' => '5', 'max_digital_mb' => '500',
        // FIX-ALL C8: 'doku_mode' DIHAPUS dari default settings. Mode
        // sandbox/produksi HANYA dari config.php (DOKU_BASE_URL dkk);
        // kunci settings ini tidak pernah dibaca doku.php dan tidak bisa
        // diubah lewat panel admin, jadi hanya menyesatkan.
    ];
    return $defaults[$key] ?? $default;
}
function fee_percent(): float { return (float)setting('platform_fee_percent', '10'); }
// Pembeda aturan COD lama vs baru (2026-10-30): order baru cod_delivery
// punya total = subtotal + ongkos antar (pembeli membayar ongkos antar);
// order lama punya total = subtotal (penjual menanggung COD). Dipakai
// hold_escrow, settle_cash_fees, dan partial refund agar order lama
// tetap memakai ekonomi aturan lama tanpa migrasi skema.
function cod_buyer_pays(array $o): bool {
    return money((float)($o['total'] ?? 0) - (float)($o['subtotal'] ?? 0)) > 0;
}
function cod_fee_flat(): float { return (float)setting('cod_fee_flat', '5000'); }
function min_withdrawal(): float { return (float)setting('min_withdrawal', '20000'); }
function auto_release_days(): int { return (int)setting('auto_release_days', '3'); }
function download_expiry_hours(): int { return (int)setting('download_expiry_hours', '24'); }
function download_max_uses(): int { return (int)setting('download_max_uses', '3'); }
function max_image_mb(): int { return (int)setting('max_image_mb', '5'); }
function max_digital_mb(): int { return (int)setting('max_digital_mb', '500'); }

// --- Dompet: SEMUA perubahan saldo harus lewat fungsi ini, di dalam
// transaksi DB, dengan SELECT ... FOR UPDATE. Ledger wallet_transactions
// bersifat immutable (hanya INSERT). ---------------------------------------
function wallet_move(PDO $db, int $userId, float $signedAmount, string $type,
    ?string $refType, $refId, string $note): float {
    $st = $db->prepare('SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE');
    $st->execute([$userId]);
    $row = $st->fetch();
    if (!$row) { throw new RuntimeException('User tidak ditemukan.'); }
    $new = money((float)$row['wallet_balance'] + $signedAmount);
    if ($new < 0) { throw new RuntimeException('Saldo tidak mencukupi.'); }
    $db->prepare('UPDATE users SET wallet_balance = ? WHERE id = ?')->execute([$new, $userId]);
    $db->prepare('INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_type, reference_id, note) VALUES (?,?,?,?,?,?,?)')
        ->execute([$userId, $type, $signedAmount, $new, $refType, $refId, $note]);
    return $new;
}

// Kredit ke penjual dari hasil penjualan: seluruhnya masuk dompet.
// (DULU: potong utang otomatis/garnish dari seller_debts — fitur hutang
// DIHAPUS 2026-10-30: tidak ada lagi pencatatan utang, potongan dari
// kredit berikutnya, maupun blokir penarikan karena utang.)
function credit_seller(PDO $db, int $sellerId, float $gross, string $type,
    ?string $refType, $refId, string $note): float {
    return wallet_move($db, $sellerId, money($gross), $type, $refType, $refId, $note);
}

// --- Order & stok ------------------------------------------------------------
function order_code(): string {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}
function restore_stock(PDO $db, int $orderId): void {
    $items = $db->prepare('SELECT product_id, qty FROM order_items WHERE order_id = ?');
    $items->execute([$orderId]);
    foreach ($items->fetchAll() as $it) {
        // Hanya produk fisik yang stoknya pernah dikurangi (stok digital NULL).
        $db->prepare('UPDATE products SET stok = stok + ? WHERE id = ? AND stok IS NOT NULL')
            ->execute([(int)$it['qty'], $it['product_id']]);
    }
}
function slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string)$s, '-') ?: 'kategori';
}

// --- View --------------------------------------------------------------------
function render(string $view, array $vars = []): void {
    extract($vars);
    $user = current_user();
    $title = $vars['title'] ?? setting('site_title');
    $flashes = flashes();
    ob_start();
    require __DIR__ . '/views/' . $view . '.php';
    $content = ob_get_clean();
    require __DIR__ . '/views/layout.php';
}

// --- Withdrawal providers (Indonesia) ---------------------------------------
// NEO-WITHDRAWAL: daftar lengkap penyedia penarikan, tiga kelompok:
// E-Wallet (dompet digital berizin), Bank Digital (layanan bank digital),
// dan Bank (bank umum: BUMN, BPD, swasta nasional, syariah, asing/campuran).
// Dipakai form seller (app/views/withdrawals.php) DAN validasi server-side
// (withdrawal_request). Ubah daftar di sini saja bila ada penyedia baru.
// CATATAN KEAKURATAN (Okt 2026): hanya nama yang berlaku saat ini.
// Nama lama hasil merger/rebranding TIDAK dicantumkan terpisah supaya
// tidak ganda/salah: Bukopin -> Bank KB Indonesia, Bank Fama -> Superbank,
// Bank Artos -> Bank Jago, Bank Harda -> Allo Bank, Bank Kesejahteraan
// Ekonomi -> SeaBank, Bank Yudha Bhakti -> Bank Neo Commerce, Bank Mayora
// -> hibank, Commonwealth -> OCBC. BPR (jumlahnya 1.000+) tidak dirinci
// satu per satu — pakai opsi "Lainnya...". Merek digital dan nama bank
// legalnya sama-sama ada bila berbeda (mis. Jenius vs Bank BTPN, Blu vs
// Bank Digital BCA, LINE Bank vs Bank Hana Indonesia) — seller memilih
// yang sesuai rekeningnya, admin melihat persis yang dipilih seller.
function withdrawal_providers(): array
{
    return [
        'ewallet' => ['AstraPay', 'DANA', 'DOKU Wallet', 'GoPay', 'i.saku', 'LinkAja', 'OVO', 'Pospay', 'Sakuku', 'ShopeePay', 'SpeedCash'],
        'digital' => ['Allo Bank', 'Bank Aladin Syariah', 'Bank Amar', 'Bank Capital', 'Bank Jago', 'Bank Neo Commerce', 'Bank Raya', 'Bank Saqu', 'Blu by BCA Digital', 'Digibank by DBS', 'hibank', 'Jenius', 'Krom Bank', 'LINE Bank', 'MotionBank', 'Nanobank Syariah', 'SeaBank', 'Superbank', 'TMRW by UOB'],
        'bank' => ['Bank Aceh Syariah', 'Bank ANZ Indonesia', 'Bank Artha Graha Internasional', 'Bank Banten', 'Bank Bengkulu', 'Bank BJB', 'Bank BJB Syariah', 'Bank BNP Paribas Indonesia', 'Bank BPD Bali', 'Bank BPD DIY', 'Bank BTPN', 'Bank Bumi Arta', 'Bank CCB Indonesia', 'Bank CTBC Indonesia', 'Bank Danamon', 'Bank DBS Indonesia', 'Bank Digital BCA', 'Bank DKI', 'Bank Ganesha', 'Bank Hana Indonesia', 'Bank HSBC Indonesia', 'Bank IBK Indonesia', 'Bank ICBC Indonesia', 'Bank Ina', 'Bank Index', 'Bank J Trust Indonesia', 'Bank Jambi', 'Bank Jateng', 'Bank Jatim', 'Bank Kalbar', 'Bank Kalsel', 'Bank Kalteng', 'Bank Kaltimtara', 'Bank Kasikorn Indonesia', 'Bank KB Indonesia', 'Bank Lampung', 'Bank Maluku Malut', 'Bank Mandiri', 'Bank Mandiri Taspen', 'Bank Maspion', 'Bank Mayapada', 'Bank Maybank Indonesia', 'Bank Mega', 'Bank Mega Syariah', 'Bank Mestika', 'Bank Mizuho Indonesia', 'Bank MNC', 'Bank Muamalat', 'Bank Multiarta Sentosa', 'Bank Nagari', 'Bank Nobu', 'Bank NTB Syariah', 'Bank NTT', 'Bank of India Indonesia', 'Bank OK', 'Bank Panin Dubai Syariah', 'Bank Papua', 'Bank Permata', 'Bank Resona Perdania', 'Bank Riau Kepri', 'Bank SBI Indonesia', 'Bank Shinhan Indonesia', 'Bank Sinarmas', 'Bank SMBC Indonesia', 'Bank Sulselbar', 'Bank Sulteng', 'Bank Sultra', 'Bank SulutGo', 'Bank Sumsel Babel', 'Bank Sumut', 'Bank UOB Indonesia', 'Bank Victoria International', 'Bank Victoria Syariah', 'Bank Woori Saudara', 'BCA', 'BCA Syariah', 'BNI', 'BRI', 'BSI', 'BTN', 'BTPN Syariah', 'CIMB Niaga', 'Citibank', 'OCBC', 'Panin', 'Standard Chartered Bank'],
    ];
}

// Label metode penarikan untuk tampilan (nilai mentah di DB: ewallet /
// digital / bank; baris lama hanya punya ewallet / bank).
function withdrawal_method_label(string $m): string
{
    return ['ewallet' => 'E-Wallet', 'digital' => 'Bank Digital', 'bank' => 'Bank'][$m] ?? $m;
}
