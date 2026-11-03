<?php
// Produk: CRUD + moderasi admin. Hanya status=active tampil di katalog.
// Edit produk ACTIVE mengembalikannya ke pending (moderasi ulang).
// Produk digital TANPA file digital TIDAK BISA di-approve (server-side).
// File digital disimpan di server sendiri (app/storage.php); kolom
// drive_file_id menyimpan nama file internal di disk (bukan ID layanan
// luar) — nama kolom dipertahankan agar tidak perlu ALTER TABLE.
if (!defined('MARKETPLACE_APP')) { http_response_code(403); exit('Forbidden'); }

function upload_image(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return null; }
    if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) { throw new RuntimeException('Upload gambar gagal (kode ' . (int)$file['error'] . ').'); }
    $max = max_image_mb() * 1024 * 1024;
    if ($file['size'] > $max) { throw new RuntimeException('Ukuran gambar melebihi ' . max_image_mb() . ' MB.'); }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) { throw new RuntimeException('File bukan gambar yang valid.'); }
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!isset($allowed[$info[2]])) { throw new RuntimeException('Format gambar harus jpg, png, atau webp.'); }
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$info[2]];
    $dir = __DIR__ . '/../../uploads';
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) { throw new RuntimeException('Gagal menyimpan gambar.'); }
    return $name;
}

// BARANG TERLARANG — keputusan pemilik 2026-10-29: produk dewasa, alkohol,
// rokok, narkoba, judi, dan senjata OTOMATIS DITOLAK dari katalog (market
// dipakai semua umur). Berlaku di product_save (produk baru + edit, termasuk
// edit produk aktif) dan di product_approve (menutup celah produk pending
// lama yang terdaftar sebelum aturan ini). Daftar ini SATU-SATUNYA tempat
// kata kunci diatur — tambah/hapus kata cukup di sini, tanpa ubah skema.
// Pencocokan: kata/frasa utuh (bukan potongan kata), tanpa peduli huruf
// besar/kecil. Contoh yang SENGAJA tidak ikut (terlalu luas, rawan salah
// blokir): 'sexy' (fesyen), 'pedang'/'pisau' (mainan/perkakas dapur),
// 'slot'/'poker' telanjang (aksesoris HP/kartu) — yang dipakai adalah
// frasa judinya ('slot online', 'slot gacor', 'judi bola', dst.).
function prohibited_product_terms(): array
{
    return [
        // Produk dewasa
        'porno', 'pornografi', 'bokep', 'seks', 'sex', 'vibrator', 'dildo',
        'kondom', 'boneka seks', 'alat bantu seks', 'sex toy', 'sex toys',
        'sex-toy', 'open bo', 'onlyfans', 'porn',
        // Alkohol
        'alkohol', 'bir', 'wine', 'vodka', 'whisky', 'whiskey', 'soju',
        'sake', 'arak', 'tuak', 'miras',
        // Rokok / tembakau
        'rokok', 'tembakau', 'vape', 'vapor', 'cerutu', 'shisha',
        'rokok elektrik', 'cigarette', 'tobacco',
        // Narkoba
        'narkoba', 'ganja', 'sabu', 'sabu-sabu', 'kokain', 'heroin',
        'ekstasi', 'putaw', 'morfin', 'opium', 'cannabis', 'marijuana',
        'cocaine',
        // Judi
        'judi', 'togel', 'kasino', 'taruhan', 'judi online', 'judi bola',
        'slot online', 'slot gacor', 'bandar togel', 'gambling', 'casino',
        // Senjata
        'senjata', 'pistol', 'senapan', 'rifle', 'granat', 'amunisi',
        'airsoft', 'senjata api', 'senjata tajam', 'bom',
    ];
}

function prohibited_product_match(string $text): ?string
{
    $text = mb_strtolower(preg_replace('/\s+/u', ' ', $text) ?? '', 'UTF-8');
    foreach (prohibited_product_terms() as $term) {
        // Batas kata kustom: huruf/angka/_/- di kiri-kanan menggagalkan
        // (jadi 'bir' tidak kena 'biru'/'biro', 'arak' tidak kena
        // 'arak-arakan', 'sabu' tidak kena 'menyabu').
        $pattern = '/(?<![\p{L}0-9_-])' . preg_quote($term, '/') . '(?![\p{L}0-9_-])/u';
        if (preg_match($pattern, $text) === 1) { return $term; }
    }
    return null;
}

function is_prohibited_product(string $text): bool
{
    return prohibited_product_match($text) !== null;
}

function prohibited_product_reason(): string
{
    return 'Ditolak otomatis: barang terlarang (produk dewasa, alkohol, rokok, narkoba, judi, atau senjata) tidak boleh dijual di marketplace ini.';
}

function product_save(): void
{
    csrf_check();
    $u = require_login();
    $id = (int)($_POST['id'] ?? 0);
    $db = pdo();
    $existing = null;
    if ($id > 0) {
        $st = $db->prepare('SELECT * FROM products WHERE id = ?');
        $st->execute([$id]); $existing = $st->fetch();
        if (!$existing || ((int)$existing['seller_id'] !== (int)$u['id'] && $u['role'] !== 'admin')) {
            http_response_code(403); exit('Bukan produk Anda.');
        }
    }
    $tipe = post('tipe');
    $judul = post('judul'); $deskripsi = post('deskripsi');
    $harga = (float)post('harga');
    $categoryId = post('category_id') !== '' ? (int)post('category_id') : null;
    $stokRaw = post('stok');
    if (!in_array($tipe, ['digital', 'fisik'], true) || $judul === '' || $deskripsi === '' || $harga <= 0) {
        flash('error', 'Tipe, judul, deskripsi, dan harga (> 0) wajib diisi.'); redirect(url('product_form', $id ? ['id' => $id] : []));
    }
    if ($tipe === 'digital') {
        $stok = null; // NULL = tidak terbatas, hanya digital.
        $cod = 0;
    } else {
        if ($stokRaw === '' || (int)$stokRaw < 0) { flash('error', 'Stok produk fisik wajib >= 0.'); redirect(url('product_form', $id ? ['id' => $id] : [])); }
        $stok = (int)$stokRaw;
        $cod = isset($_POST['cod_available']) ? 1 : 0;
    }
    $pickup = isset($_POST['pickup_available']) ? 1 : 0;
    // KITA-UX: link demo opsional (tombol "Lihat Demo" di halaman produk).
    // Hanya skema http(s) yang diterima — javascript:/data: ditolak supaya
    // link tidak bisa dipakai menyisipkan skrip di halaman produk.
    $demoUrl = post('demo_url') ?: null;
    if ($demoUrl !== null && !preg_match('#^https?://#i', $demoUrl)) {
        flash('error', 'Link demo harus diawali http:// atau https://.'); redirect(url('product_form', $id ? ['id' => $id] : []));
    }
    $fields = [post('category_id') !== '' ? $categoryId : null, $tipe, $judul, $deskripsi,
        post('spesifikasi') ?: null, post('kelebihan') ?: null, post('kekurangan') ?: null,
        post('alasan_jual') ?: null, $harga, $stok, $cod, $pickup];
    // BARANG TERLARANG: cek judul + deskripsi + spesifikasi + kelebihan +
    // kekurangan + alasan_jual + NAMA kategori (kategori dipilih penjual
    // tapi dibuat admin — nama kategori ikut dicek supaya celah "judul
    // polos tapi kategori terlarang" tertutup). Berlaku untuk produk BARU
    // maupun EDIT; hasil cek dipakai menimpa status di bawah.
    $catName = null;
    if ($categoryId) {
        $cst = $db->prepare('SELECT nama FROM categories WHERE id = ?');
        $cst->execute([$categoryId]); $catName = $cst->fetchColumn();
    }
    $checkText = implode(' ', array_filter([$judul, $deskripsi, post('spesifikasi'), post('kelebihan'), post('kekurangan'), post('alasan_jual'), $catName]));
    $isProhibited = is_prohibited_product($checkText);
    // FIX-ALL C6: seluruh tulis produk + gambar dalam SATU transaksi DB.
    // File digital disimpan dulu ke disk (di luar transaksi — filesystem
    // tidak bisa di-rollback); bila transaksi gagal, file baru yang tidak
    // jadi dipakai dihapus best-effort (lihat catch).
    $newFileStored = false;
    $db->beginTransaction();
    try {
        // Simpan file digital ke penyimpanan lokal server bila ada file baru.
        // Validasi ukuran: batas settings.max_digital_mb di bawah + batas
        // php.ini (upload_max_filesize/post_max_size) lewat kode error upload.
        $fileId = $existing['drive_file_id'] ?? null;
        $fileName = $existing['drive_file_name'] ?? null;
        $fileSize = $existing['drive_file_size'] ?? null;
        if ($tipe === 'digital' && !empty($_FILES['digital_file']['name'])) {
            $f = $_FILES['digital_file'];
            if ($f['error'] !== UPLOAD_ERR_OK) { throw new RuntimeException('Upload file digital gagal (kode ' . (int)$f['error'] . ').'); }
            if ($f['size'] > max_digital_mb() * 1024 * 1024) { throw new RuntimeException('File digital melebihi ' . max_digital_mb() . ' MB.'); }
            $up = (new LocalStorage())->store($f['tmp_name'], basename($f['name']));
            $newFileStored = true;
            $fileId = $up['id']; $fileName = $up['name']; $fileSize = $up['size'];
        }
        if ($id > 0) {
            // Edit produk ACTIVE -> kembali pending untuk moderasi ulang.
            // FIX-ALL C10: edit produk REJECTED juga kembali ke 'pending'
            // (ajukan ulang otomatis) dan alasan penolakan LAMA DIPERTAHANKAN
            // sampai moderasi ulang (bukan dibersihkan) — penjual + admin
            // tetap bisa melihat apa yang harus diperbaiki.
            $wasRejected = $existing['status'] === 'rejected';
            $newStatus = ($existing['status'] === 'active' || $wasRejected) ? 'pending' : $existing['status'];
            $keepReason = $wasRejected ? ($existing['rejection_reason'] ?? null) : null;
            // BARANG TERLARANG menimpa status apa pun (termasuk 'pending'
            // hasil edit produk aktif) menjadi 'rejected' — tidak bisa
            // dilompati dengan mengedit, karena cek berjalan tiap simpan.
            if ($isProhibited) { $newStatus = 'rejected'; $keepReason = prohibited_product_reason(); }
            $db->prepare('UPDATE products SET category_id=?, tipe=?, judul=?, deskripsi=?, spesifikasi=?, kelebihan=?, kekurangan=?, alasan_jual=?, harga=?, stok=?, cod_available=?, pickup_available=?, drive_file_id=?, drive_file_name=?, drive_file_size=?, status=?, rejection_reason=?, demo_url=? WHERE id=?')
                ->execute([...$fields, $fileId, $fileName, $fileSize, $newStatus, $keepReason, $demoUrl, $id]);
        } else {
            $db->prepare('INSERT INTO products (seller_id, category_id, tipe, judul, deskripsi, spesifikasi, kelebihan, kekurangan, alasan_jual, harga, stok, cod_available, pickup_available, drive_file_id, drive_file_name, drive_file_size, status, rejection_reason, demo_url) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([(int)$u['id'], ...$fields, $fileId, $fileName, $fileSize, $isProhibited ? 'rejected' : 'pending', $isProhibited ? prohibited_product_reason() : null, $demoUrl]);
            $id = (int)$db->lastInsertId();
        }
        // FIX-ALL C7 (dipertahankan + diperketat untuk transaksi):
        // file lama HANYA dicatat di sini; penghapusan dilakukan SETELAH
        // commit (lihat di bawah) — bila commit gagal / rollback, baris
        // produk tetap menunjuk file lama yang masih ada.
        $oldFileId = $existing['drive_file_id'] ?? null;
        $oldFileToDelete = ($oldFileId && $oldFileId !== $fileId) ? $oldFileId : null;
        // Gambar (boleh beberapa, maks 5 per produk pada increment ini).
        if (!empty($_FILES['images']['name'][0])) {
            $count = 0;
            foreach ($_FILES['images']['name'] as $i => $nm) {
                if ($count >= 5) { break; }
                $one = ['name' => $_FILES['images']['name'][$i], 'type' => $_FILES['images']['type'][$i],
                    'tmp_name' => $_FILES['images']['tmp_name'][$i], 'error' => $_FILES['images']['error'][$i],
                    'size' => $_FILES['images']['size'][$i]];
                $path = upload_image($one);
                if ($path) {
                    $db->prepare('INSERT INTO product_images (product_id, path, sort_order) VALUES (?,?,?)')->execute([$id, $path, $count]);
                    $count++;
                }
            }
        }
        $db->commit();
        // Hapus file lama SETELAH commit berhasil (best-effort).
        if (!empty($oldFileToDelete)) { (new LocalStorage())->delete($oldFileToDelete); }
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        // File baru yang tidak jadi dipakai dibersihkan best-effort agar
        // tidak mengendap (file lama tidak pernah dihapus di sini).
        if ($newFileStored && $fileId && $fileId !== ($existing['drive_file_id'] ?? null)) {
            try { (new LocalStorage())->delete($fileId); } catch (Throwable $ignored) {}
        }
        flash('error', $e->getMessage()); redirect(url('product_form', $id ? ['id' => $id] : []));
    }
    // Pesan akhir dibedakan: produk terlarang DITOLAK (bukan "menunggu
    // moderasi") — penjual melihat alasan lengkapnya di "Produk Saya".
    if ($isProhibited) {
        flash('error', 'Produk ditolak otomatis karena termasuk barang terlarang (produk dewasa, alkohol, rokok, narkoba, judi, atau senjata) dan tidak akan tampil di katalog.');
    } else {
        flash('success', 'Produk disimpan dan menunggu moderasi admin.');
    }
    redirect(url('my_products'));
}

function product_archive(): void
{
    csrf_check(); $u = require_login();
    $id = (int)($_POST['id'] ?? 0);
    $st = pdo()->prepare('SELECT seller_id FROM products WHERE id = ?'); $st->execute([$id]);
    $p = $st->fetch();
    if (!$p || ((int)$p['seller_id'] !== (int)$u['id'] && $u['role'] !== 'admin')) { http_response_code(403); exit('Bukan produk Anda.'); }
    pdo()->prepare("UPDATE products SET status = 'archived' WHERE id = ?")->execute([$id]);
    flash('success', 'Produk diarsipkan.'); redirect(url('my_products'));
}

function product_approve(): void
{
    csrf_check(); $admin = require_admin();
    $id = (int)($_POST['id'] ?? 0);
    $db = pdo();
    $st = $db->prepare('SELECT p.*, u.status AS seller_status FROM products p JOIN users u ON u.id = p.seller_id WHERE p.id = ?'); $st->execute([$id]);
    $p = $st->fetch();
    if (!$p) { flash('error', 'Produk tidak ditemukan.'); redirect(url('admin_moderation')); }
    // FIX-ALL B10 (maker-checker): admin tidak boleh menyetujui produknya sendiri.
    if ((int)$p['seller_id'] === (int)$admin['id']) { flash('error', 'Produk sendiri harus dimoderasi admin lain.'); redirect(url('admin_moderation')); }
    // FIX-ALL B4: produk penjual yang di-suspend tidak boleh di-approve.
    if (($p['seller_status'] ?? 'active') !== 'active') { flash('error', 'Penjual produk ini sedang ditangguhkan; produk tidak bisa di-approve.'); redirect(url('admin_moderation')); }
    // Digital tanpa file TIDAK BOLEH di-approve (server-side).
    if ($p['tipe'] === 'digital' && empty($p['drive_file_id'])) {
        flash('error', 'Produk digital tanpa file tidak bisa di-approve.'); redirect(url('admin_moderation'));
    }
    // BARANG TERLARANG: menutup celah produk pending lama yang terdaftar
    // sebelum aturan ini — admin tidak bisa me-approve-nya; produk
    // langsung ditolak dengan alasan otomatis.
    $approveCatName = null;
    if (!empty($p['category_id'])) {
        $acst = $db->prepare('SELECT nama FROM categories WHERE id = ?'); $acst->execute([(int)$p['category_id']]); $approveCatName = $acst->fetchColumn();
    }
    $approveCheckText = implode(' ', array_filter([$p['judul'], $p['deskripsi'], $p['spesifikasi'], $p['kelebihan'], $p['kekurangan'], $p['alasan_jual'], $approveCatName]));
    if (is_prohibited_product($approveCheckText)) {
        $db->prepare("UPDATE products SET status = 'rejected', rejection_reason = ? WHERE id = ?")->execute([prohibited_product_reason(), $id]);
        flash('error', 'Produk ini termasuk barang terlarang dan ditolak otomatis, tidak bisa di-approve.'); redirect(url('admin_moderation'));
    }
    $db->prepare("UPDATE products SET status = 'active', rejection_reason = NULL WHERE id = ?")->execute([$id]);
    flash('success', 'Produk di-approve.'); redirect(url('admin_moderation'));
}

function product_reject(): void
{
    csrf_check(); $admin = require_admin();
    $id = (int)($_POST['id'] ?? 0);
    $reason = post('rejection_reason');
    if ($reason === '') { flash('error', 'Alasan penolakan wajib diisi.'); redirect(url('admin_moderation')); }
    // FIX-ALL B10 (maker-checker): berlaku juga untuk penolakan.
    $st = pdo()->prepare('SELECT seller_id FROM products WHERE id = ?'); $st->execute([$id]); $p = $st->fetch();
    if ($p && (int)$p['seller_id'] === (int)$admin['id']) { flash('error', 'Produk sendiri harus dimoderasi admin lain.'); redirect(url('admin_moderation')); }
    pdo()->prepare("UPDATE products SET status = 'rejected', rejection_reason = ? WHERE id = ?")->execute([$reason, $id]);
    flash('success', 'Produk ditolak.'); redirect(url('admin_moderation'));
}

// Ulasan berbasis invoice (KITA-UX): HANYA pembeli yang bisa menilai,
// dibuktikan dengan invoice (order_code) dari order miliknya sendiri yang
// berstatus completed dan memuat produk ini. Satu ulasan per pembeli per
// produk; kirim ulang MEMPERBARUI ulasan yang sama (UNIQUE buyer+product).
// Ditempatkan di controller produk karena ulasan menempel pada produk
// (bukan pada order); rute 'review_submit' didaftarkan di index.php.
function review_submit(): void
{
    csrf_check(); $u = require_login();
    $productId = (int)($_POST['product_id'] ?? 0);
    $orderId = (int)($_POST['order_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = post('comment');
    if ($productId <= 0 || $orderId <= 0 || $rating < 1 || $rating > 5 || $comment === '') {
        flash('error', 'Rating 1–5 dan komentar wajib diisi.'); redirect(url('product', ['id' => $productId]));
    }
    $db = pdo();
    $st = $db->prepare('SELECT id FROM products WHERE id = ?'); $st->execute([$productId]); $prod = $st->fetch();
    if (!$prod) { flash('error', 'Produk tidak ditemukan.'); redirect(url('catalog')); }
    // Validasi invoice di sisi server (bukan dari form saja): order harus
    // milik reviewer, berstatus completed, DAN memuat produk ini.
    $st = $db->prepare('SELECT o.id FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE o.id = ? AND o.buyer_id = ? AND o.status = ? AND oi.product_id = ? LIMIT 1');
    $st->execute([$orderId, (int)$u['id'], 'completed', $productId]); $ok = $st->fetch();
    if (!$ok) { flash('error', 'Invoice tidak valid untuk produk ini (hanya order completed milik Anda yang memuat produk ini).'); redirect(url('product', ['id' => $productId])); }
    $db->prepare('INSERT INTO product_reviews (product_id, buyer_id, order_id, rating, comment) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE order_id = VALUES(order_id), rating = VALUES(rating), comment = VALUES(comment), updated_at = ?')
        ->execute([$productId, (int)$u['id'], $orderId, $rating, $comment, now()]);
    flash('success', 'Ulasan tersimpan. Terima kasih!');
    redirect(url('product', ['id' => $productId]));
}

// FASE 1 A4 — Laporkan konten/pengguna bermasalah. Target harus ada di
// database (produk / ulasan / penjual); alasan wajib diisi. Laporan masuk
// antrean admin (admin_reports) — tidak ada aksi otomatis atas laporan
// (admin yang memutuskan reviewed/dismissed/actioned).
function report_submit(): void
{
    csrf_check(); $u = require_login();
    $type = (string)($_POST['target_type'] ?? '');
    $targetId = (int)($_POST['target_id'] ?? 0);
    $reason = post('reason');
    if (!in_array($type, ['product', 'review', 'seller'], true) || $targetId <= 0 || $reason === '') {
        flash('error', 'Jenis laporan, target, dan alasan wajib diisi.'); redirect(url('catalog'));
    }
    $db = pdo();
    $back = url('catalog');
    if ($type === 'product') {
        $st = $db->prepare('SELECT id FROM products WHERE id = ?'); $st->execute([$targetId]);
        if (!$st->fetch()) { flash('error', 'Produk yang dilaporkan tidak ditemukan.'); redirect($back); }
        $back = url('product', ['id' => $targetId]);
    } elseif ($type === 'review') {
        $st = $db->prepare('SELECT r.product_id FROM product_reviews r WHERE r.id = ?'); $st->execute([$targetId]); $r = $st->fetch();
        if (!$r) { flash('error', 'Ulasan yang dilaporkan tidak ditemukan.'); redirect($back); }
        $back = url('product', ['id' => (int)$r['product_id']]);
    } else {
        $st = $db->prepare('SELECT id FROM users WHERE id = ?'); $st->execute([$targetId]);
        if (!$st->fetch()) { flash('error', 'Pengguna yang dilaporkan tidak ditemukan.'); redirect($back); }
    }
    $db->prepare("INSERT INTO reports (reporter_id, target_type, target_id, reason, status) VALUES (?,?,?,?,'pending')")
        ->execute([(int)$u['id'], $type, $targetId, $reason]);
    flash('success', 'Laporan terkirim. Admin akan meninjaunya.');
    redirect($back);
}

// FASE 1 A4 — Blokir penjual: produknya hilang dari katalog si pemblokir
// dan tidak bisa di-checkout olehnya (penegakan di catalog.php +
// checkout()). Tidak bisa memblokir diri sendiri. Blokir bersifat
// per-pengguna (bukan pemblokiran global — itu wewenang admin suspend).
function block_seller(): void
{
    csrf_check(); $u = require_login();
    $sellerId = (int)($_POST['seller_id'] ?? 0);
    $productId = (int)($_POST['product_id'] ?? 0);
    $back = $productId > 0 ? url('product', ['id' => $productId]) : url('catalog');
    if ($sellerId <= 0) { flash('error', 'Penjual tidak valid.'); redirect($back); }
    if ($sellerId === (int)$u['id']) { flash('error', 'Anda tidak bisa memblokir diri sendiri.'); redirect($back); }
    $db = pdo();
    $st = $db->prepare('SELECT id FROM users WHERE id = ?'); $st->execute([$sellerId]);
    if (!$st->fetch()) { flash('error', 'Penjual tidak ditemukan.'); redirect($back); }
    $ex = $db->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?'); $ex->execute([(int)$u['id'], $sellerId]);
    if (!$ex->fetch()) {
        $db->prepare('INSERT INTO user_blocks (blocker_id, blocked_id) VALUES (?,?)')->execute([(int)$u['id'], $sellerId]);
    }
    flash('success', 'Penjual diblokir. Produknya tidak akan tampil di katalog Anda.');
    redirect($back);
}

function unblock_seller(): void
{
    csrf_check(); $u = require_login();
    $sellerId = (int)($_POST['seller_id'] ?? 0);
    $productId = (int)($_POST['product_id'] ?? 0);
    $back = $productId > 0 ? url('product', ['id' => $productId]) : url('catalog');
    pdo()->prepare('DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?')->execute([(int)$u['id'], $sellerId]);
    flash('success', 'Blokir dibuka. Produk penjual itu tampil lagi di katalog Anda.');
    redirect($back);
}

// FASE 1 A6 — Moderasi ulasan oleh admin: sembunyikan / tampilkan lagi.
// Ulasan tersembunyi tidak tampil di halaman produk dan tidak dihitung di
// rata-rata (product.php memfilter hidden = 0). Baris ulasan tidak dihapus.
function review_hide(): void
{
    csrf_check(); require_admin();
    $id = (int)($_POST['review_id'] ?? 0);
    pdo()->prepare('UPDATE product_reviews SET hidden = 1 WHERE id = ?')->execute([$id]);
    flash('success', 'Ulasan disembunyikan dari halaman produk.'); redirect(url('admin_reports'));
}

function review_unhide(): void
{
    csrf_check(); require_admin();
    $id = (int)($_POST['review_id'] ?? 0);
    pdo()->prepare('UPDATE product_reviews SET hidden = 0 WHERE id = ?')->execute([$id]);
    flash('success', 'Ulasan ditampilkan lagi.'); redirect(url('admin_reports'));
}

// FASE 1 A4 — Admin memutuskan laporan (antrean admin_reports).
function report_decide(): void
{
    csrf_check(); require_admin();
    $id = (int)($_POST['report_id'] ?? 0);
    $decision = (string)($_POST['decision'] ?? '');
    if (!in_array($decision, ['reviewed', 'dismissed', 'actioned'], true)) {
        flash('error', 'Keputusan laporan tidak valid.'); redirect(url('admin_reports'));
    }
    $up = pdo()->prepare("UPDATE reports SET status = ? WHERE id = ? AND status = 'pending'"); $up->execute([$decision, $id]);
    if ($up->rowCount() === 0) { flash('error', 'Laporan sudah diputuskan sebelumnya.'); redirect(url('admin_reports')); }
    flash('success', 'Laporan ditandai: ' . $decision . '.'); redirect(url('admin_reports'));
}
