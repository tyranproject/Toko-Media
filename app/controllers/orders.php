<?php
// Keranjang (session) + checkout (pecah per seller) + aksi order.
// Total SELALU dihitung ulang dari harga produk di server — tidak pernah
// memakai angka dari client. Pembeli tidak boleh membeli produk sendiri.
if (!defined('MARKETPLACE_APP')) { http_response_code(403); exit('Forbidden'); }

function cart(): array { return $_SESSION['cart'] ?? []; }

function cart_add(): void
{
    csrf_check(); $u = require_login();
    $id = (int)($_POST['product_id'] ?? 0); $qty = max(1, (int)($_POST['qty'] ?? 1));
    $st = pdo()->prepare("SELECT p.*, u.status AS seller_status FROM products p JOIN users u ON u.id = p.seller_id WHERE p.id = ? AND p.status = 'active'"); $st->execute([$id]);
    $p = $st->fetch();
    if (!$p) { flash('error', 'Produk tidak tersedia.'); redirect(url('catalog')); }
    // FIX-ALL B4: produk penjual yang di-suspend tidak bisa dibeli.
    if (($p['seller_status'] ?? 'active') !== 'active') { flash('error', 'Penjual produk ini sedang ditangguhkan.'); redirect(url('catalog')); }
    if ((int)$p['seller_id'] === (int)$u['id']) { flash('error', 'Anda tidak bisa membeli produk sendiri.'); redirect(url('product', ['id' => $id])); }
    if ($p['tipe'] === 'fisik' && $p['stok'] !== null && $qty > (int)$p['stok']) { flash('error', 'Stok tidak mencukupi.'); redirect(url('product', ['id' => $id])); }
    // PREMKU-FITUR: stok juga memperhitungkan jumlah yang SUDAH ada di keranjang
    // (tambah berulang tidak boleh melewati stok; stok 0 sudah ditolak di atas).
    if ($p['tipe'] === 'fisik' && $p['stok'] !== null && (int)($_SESSION['cart'][$id] ?? 0) + $qty > (int)$p['stok']) { flash('error', 'Stok tidak mencukupi (sudah ada ' . (int)($_SESSION['cart'][$id] ?? 0) . ' di keranjang).'); redirect(url('product', ['id' => $id])); }
    $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + $qty;
    if ($p['tipe'] === 'digital') { $_SESSION['cart'][$id] = 1; } // digital: 1 lisensi per order
    flash('success', 'Ditambahkan ke keranjang.');
    // KITA-UX: ORDER NOW tidak langsung ke halaman keranjang / checkout.
    // Kembali ke halaman perujuk dengan flag cart_popup=1; layout.php
    // merender modal keranjang dari flag itu (tombol Lanjut Belanja /
    // Checkout ada di modal). return_to hanya diterima bila URL relatif
    // index.php (mencegah open redirect); selain itu fallback ke halaman
    // produk dengan flag yang sama.
    $back = (string)($_POST['return_to'] ?? '');
    if ($back === '' || !str_starts_with($back, 'index.php')) { $back = url('product', ['id' => $id]); }
    $sep = str_contains($back, '?') ? '&' : '?';
    redirect($back . $sep . 'cart_popup=1');
}
function cart_update(): void
{
    csrf_check(); require_login();
    $db = pdo(); $err = false;
    foreach (($_POST['qty'] ?? []) as $pid => $q) {
        $q = (int)$q;
        if ($q <= 0) { unset($_SESSION['cart'][(int)$pid]); continue; }
        // PREMKU-FITUR: ubah jumlah di keranjang tidak boleh melewati stok.
        $st = $db->prepare("SELECT tipe, stok FROM products WHERE id = ? AND status = 'active'");
        $st->execute([(int)$pid]); $pp = $st->fetch();
        if ($pp && $pp['tipe'] === 'fisik' && $pp['stok'] !== null && $q > (int)$pp['stok']) { $err = true; continue; }
        $_SESSION['cart'][(int)$pid] = $q;
    }
    if ($err) { flash('error', 'Sebagian jumlah melebihi stok sehingga tidak diubah.'); }
    redirect(url('cart'));
}
function cart_remove(): void
{
    csrf_check(); require_login();
    unset($_SESSION['cart'][(int)($_POST['product_id'] ?? 0)]);
    redirect(url('cart'));
}

// Checkout: satu order per seller; tiap grup seller harus homogen
// (semua digital ATAU semua fisik) karena fulfillment berbeda.
function checkout(): void
{
    csrf_check(); $u = require_login();
    // FIX-ALL C4: token sekali pakai; submit ulang lintas perangkat tidak
    // membuat order kedua. Token habis/dipakai -> tolak tanpa efek samping.
    if (!consume_form_token('checkout')) { flash('error', 'Form checkout sudah dipakai atau kedaluwarsa. Muat ulang halaman checkout dan coba lagi.'); redirect(url('checkout')); }
    $items = cart();
    if (!$items) { flash('error', 'Keranjang kosong.'); redirect(url('cart')); }
    $db = pdo();
    $groups = []; // seller_id => [items...]
    foreach ($items as $pid => $qty) {
        $st = $db->prepare("SELECT p.*, u.status AS seller_status FROM products p JOIN users u ON u.id = p.seller_id WHERE p.id = ? AND p.status = 'active'"); $st->execute([$pid]);
        $p = $st->fetch();
        if (!$p) { flash('error', 'Ada produk yang sudah tidak aktif.'); redirect(url('cart')); }
        // FIX-ALL B4: tolak checkout produk dari penjual yang di-suspend.
        if (($p['seller_status'] ?? 'active') !== 'active') { flash('error', 'Ada produk dari penjual yang sedang ditangguhkan: "' . $p['judul'] . '". Hapus dari keranjang.'); redirect(url('cart')); }
        if ((int)$p['seller_id'] === (int)$u['id']) { flash('error', 'Keranjang berisi produk sendiri.'); redirect(url('cart')); }
        // FASE 1 A4: tolak checkout produk dari penjual yang DIBLOKIR oleh
        // pembeli ini (penegakan server-side; katalog sudah menyembunyikan
        // produknya, tapi keranjang lama tetap harus ditolak di sini).
        $bl = $db->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?'); $bl->execute([(int)$u['id'], (int)$p['seller_id']]);
        if ($bl->fetch()) { flash('error', 'Keranjang berisi produk dari penjual yang Anda blokir: "' . $p['judul'] . '". Hapus dari keranjang atau buka blokirnya.'); redirect(url('cart')); }
        $groups[(int)$p['seller_id']][] = ['product' => $p, 'qty' => $p['tipe'] === 'digital' ? 1 : (int)$qty];
    }
    // Pilihan per seller dikirim sebagai fulfillment[seller_id], payment[seller_id].
    $fulfillments = $_POST['fulfillment'] ?? [];
    $payments = $_POST['payment'] ?? [];
    // FASE 1 A3: pertemuan langsung (pickup/COD) wajib diakui risikonya.
    // Bila ada grup fisik yang fulfillment-nya pickup/cod_delivery dan
    // checkbox safety_ack tidak dicentang, tolak SEBELUM order dibuat.
    // CATATAN JUJUR: ini peringatan + pengakuan untuk SEMUA pengguna,
    // BUKAN pembatasan usia — tanpa A1 (tidak ada data tanggal lahir),
    // pembatasan berbasis umur tidak mungkin ditegakkan server-side.
    $needsAck = false;
    foreach ($groups as $sid2 => $g2) {
        $t2 = array_unique(array_column(array_column($g2, 'product'), 'tipe'));
        if ($t2 !== ['digital']) {
            $f2 = (string)($fulfillments[$sid2] ?? 'pickup');
            if (in_array($f2, ['pickup', 'cod_delivery'], true)) { $needsAck = true; break; }
        }
    }
    if ($needsAck && empty($_POST['safety_ack'])) {
        flash('error', 'Centang dulu pernyataan bahwa Anda sudah membaca peringatan keamanan bertemu langsung (pickup/COD).'); redirect(url('checkout'));
    }
    $qrisPending = []; // order QRIS yang perlu dibuatkan pembayaran DOKU setelah commit
    $db->beginTransaction();
    try {
        foreach ($groups as $sellerId => $gItems) {
            $types = array_unique(array_column(array_column($gItems, 'product'), 'tipe'));
            if (count($types) > 1) { throw new RuntimeException('Pisahkan produk digital dan fisik dalam checkout berbeda (satu seller, satu jenis).'); }
            $isDigital = $types[0] === 'digital';
            $fulfillment = $isDigital ? 'digital' : (string)($fulfillments[$sellerId] ?? 'pickup');
            $payment = (string)($payments[$sellerId] ?? 'wallet');
            if (!in_array($payment, ['wallet', 'qris', 'cash'], true)) { throw new RuntimeException('Metode pembayaran tidak valid.'); }
            if ($isDigital && $payment === 'cash') { throw new RuntimeException('Produk digital tidak bisa dibayar tunai.'); }
            if (!$isDigital && !in_array($fulfillment, ['pickup', 'cod_delivery'], true)) { throw new RuntimeException('Fulfillment produk fisik tidak valid.'); }
            if (!$isDigital && $fulfillment === 'cod_delivery') {
                foreach ($gItems as $gi) {
                    if (!(int)$gi['product']['cod_available']) { throw new RuntimeException('Ada produk yang tidak tersedia untuk COD.'); }
                }
            }
            // FIX-ALL B3: tegakkan pickup_available persis seperti cod_available.
            if (!$isDigital && $fulfillment === 'pickup') {
                foreach ($gItems as $gi) {
                    if (!(int)$gi['product']['pickup_available']) { throw new RuntimeException('Penjual tidak melayani pickup untuk produk "' . $gi['product']['judul'] . '".'); }
                }
            }
            $subtotal = 0.0;
            foreach ($gItems as $gi) {
                $p = $gi['product'];
                // FASE 1 S3: kunci baris produk DI DALAM transaksi checkout
                // dan periksa ulang stok dari baris terkunci (bukan dari
                // snapshot pra-transaksi yang bisa kedaluwarsa).
                if ($p['tipe'] === 'fisik' && $p['stok'] !== null) {
                    $lk = $db->prepare('SELECT stok FROM products WHERE id = ? FOR UPDATE'); $lk->execute([$p['id']]); $lr = $lk->fetch();
                    if (!$lr || $gi['qty'] > (int)$lr['stok']) {
                        throw new RuntimeException('Stok produk "' . $p['judul'] . '" tidak mencukupi.');
                    }
                }
                $subtotal = money($subtotal + (float)$p['harga'] * $gi['qty']);
            }
            // ATURAN COD BARU (2026-10-30): pembeli membayar ongkos antar
            // (cod_delivery); total bayar = subtotal + ongkos antar. Fee
            // platform tetap ditanggung penjual (bukan ditambah ke total).
            // FIX-ALL B6: snapshot fee saat checkout untuk SEMUA metode bayar.
            $snapFee = (float)round($subtotal * fee_percent() / 100);
            $snapCod = (!$isDigital && $fulfillment === 'cod_delivery') ? cod_fee_flat() : 0.0;
            $total = money($subtotal + $snapCod);
            // Wallet: cek & debit sekarang (sebesar total, termasuk ongkos
            // antar); QRIS/cash: belum ada gerakan uang.
            $status = 'awaiting_fulfillment'; // cash
            $dokuTx = null;
            if ($payment === 'wallet') {
                wallet_move($db, (int)$u['id'], -$total, 'payment', 'order', null, 'Pembayaran order (dibuat di checkout).');
                $status = $isDigital ? 'completed' : 'held'; // digital selesai di bawah; fisik ditahan escrow
            } elseif ($payment === 'qris') {
                $status = 'awaiting_payment';
            }
            // FIX-ALL C5: retry hingga 5x bila order_code bertabrakan (UNIQUE).
            $orderId = 0; $code = '';
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $code = order_code();
                try {
                    $db->prepare('INSERT INTO orders (order_code, buyer_id, seller_id, payment_method, fulfillment, status, subtotal, platform_fee, cod_fee, total, fees_locked) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$code, $u['id'], $sellerId, $payment, $fulfillment, $status, $subtotal, $snapFee, $snapCod, $total, 1]);
                    $orderId = (int)$db->lastInsertId();
                    break;
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000' && strpos($e->getMessage(), 'uq_orders_code') !== false && $attempt < 4) { continue; }
                    throw $e;
                }
            }
            if ($orderId === 0) { throw new RuntimeException('Gagal membuat order (kode order bertabrakan berulang). Coba lagi.'); }
            foreach ($gItems as $gi) {
                $p = $gi['product'];
                $db->prepare('INSERT INTO order_items (order_id, product_id, judul_snapshot, harga_snapshot, qty, subtotal) VALUES (?,?,?,?,?,?)')
                    ->execute([$orderId, $p['id'], $p['judul'], $p['harga'], $gi['qty'], money((float)$p['harga'] * $gi['qty'])]);
                // FASE 1 S3: pengurangan bersyarat — hanya bila stok masih
                // cukup; rowCount 0 berarti stok berubah di tengah jalan
                // (balapan) dan checkout harus gagal total (rollback).
                if ($p['tipe'] === 'fisik' && $p['stok'] !== null) {
                    $up = $db->prepare('UPDATE products SET stok = stok - ? WHERE id = ? AND stok >= ?'); $up->execute([$gi['qty'], $p['id'], $gi['qty']]);
                    if ($up->rowCount() === 0) { throw new RuntimeException('Stok produk "' . $p['judul'] . '" tidak mencukupi (berubah saat checkout).'); }
                }
            }
            if ($payment === 'wallet') {
                $ost = $db->prepare('SELECT * FROM orders WHERE id = ?'); $ost->execute([$orderId]); $order = $ost->fetch();
                if ($isDigital) {
                    complete_digital_order($db, $order);
                } else {
                    hold_escrow($db, $order);
                }
                // Perbaiki referensi ledger payment (order_id kini diketahui).
                $db->prepare("UPDATE wallet_transactions SET reference_id = ? WHERE user_id = ? AND type = 'payment' AND reference_id IS NULL ORDER BY id DESC LIMIT 1")
                    ->execute([$orderId, $u['id']]);
            } elseif ($payment === 'qris') {
                // QRIS dibuat SETELAH commit (butuh koneksi DOKU).
                $qrisPending[] = ['id' => $orderId, 'order_code' => $code, 'total' => $total];
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        flash('error', $e->getMessage()); redirect(url('checkout'));
    }
    // QRIS: buat pembayaran DOKU per order (di luar transaksi DB).
    foreach ($qrisPending as $qo) {
        try {
            $pay = (new DokuClient())->createQris((float)$qo['total'], $qo['order_code'], $u['email']);
            $db->prepare('UPDATE orders SET doku_transaction_id = ? WHERE id = ?')->execute([$pay['transaction_id'], $qo['id']]);
            if (!empty($pay['qr_payload'])) { $_SESSION['qris_qr'][$qo['id']] = $pay['qr_payload']; }
        } catch (Throwable $e) {
            // FIX-ALL B2/C9: order tetap awaiting_payment dan BISA dibayar
            // ulang dari halaman order (order_repay); pesan generik saja,
            // detail gateway hanya ke error_log (lihat doku.php).
            error_log('checkout QRIS gagal ' . $qo['order_code'] . ': ' . $e->getMessage());
            flash('error', 'Order ' . $qo['order_code'] . ' dibuat, tapi pembayaran QRIS gagal dibuat. Buka halaman order dan tekan "Bayar ulang (QRIS)".');
        }
    }
    $_SESSION['cart'] = [];
    flash('success', 'Checkout berhasil. ' . count($groups) . ' order dibuat.');
    redirect(url('my_orders'));
}

function order_cancel(): void
{
    csrf_check(); $u = require_login();
    $id = (int)($_POST['id'] ?? 0);
    $db = pdo();
    $st = $db->prepare('SELECT * FROM orders WHERE id = ?'); $st->execute([$id]); $o = $st->fetch();
    if (!$o) { flash('error', 'Order tidak ditemukan.'); redirect(url('my_orders')); }
    // FIX-ALL B9: penjual TIDAK boleh membatalkan order sendiri — hanya
    // pembeli atau admin.
    $isOwner = (int)$o['buyer_id'] === (int)$u['id'];
    if (!$isOwner && $u['role'] !== 'admin') { http_response_code(403); exit('Bukan order Anda.'); }
    if ($o['status'] !== 'awaiting_payment') { flash('error', 'Hanya order menunggu pembayaran yang bisa dibatalkan.'); redirect(url('order', ['id' => $id])); }
    $db->beginTransaction();
    try {
        // FIX-ALL B11: kunci order + periksa ulang status DI DALAM transaksi
        // sebelum mengembalikan stok; UPDATE bersyarat sebagai pertahanan kedua.
        $st2 = $db->prepare("SELECT * FROM orders WHERE id = ? AND status = 'awaiting_payment' FOR UPDATE"); $st2->execute([$id]); $locked = $st2->fetch();
        if (!$locked) { throw new RuntimeException('Order tidak lagi dalam status menunggu pembayaran.'); }
        restore_stock($db, $id);
        $up = $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = 'awaiting_payment'"); $up->execute([$id]);
        if ($up->rowCount() === 0) { throw new RuntimeException('Order sudah dibatalkan / dibayar oleh proses lain.'); }
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); flash('error', $e->getMessage()); redirect(url('order', ['id' => $id])); }
    flash('success', 'Order dibatalkan, stok dikembalikan.'); redirect(url('my_orders'));
}

// Pembeli konfirmasi terima: fisik escrow -> release; tunai -> completed+settle.
function order_confirm(): void
{
    csrf_check(); $u = require_login();
    $id = (int)($_POST['id'] ?? 0);
    $db = pdo();
    $st = $db->prepare('SELECT * FROM orders WHERE id = ?'); $st->execute([$id]); $o = $st->fetch();
    if (!$o || (int)$o['buyer_id'] !== (int)$u['id']) { http_response_code(403); exit('Bukan order Anda.'); }
    if ($o['status'] === 'fee_hold') { flash('error', 'Order ini sudah dikonfirmasi dan sedang menunggu penjual top up untuk fee platform.'); redirect(url('order', ['id' => $id])); }
    if (!in_array($o['status'], ['held', 'cash_received'], true)) { flash('error', 'Order ini belum bisa dikonfirmasi.'); redirect(url('order', ['id' => $id])); }
    $feeHeld = false;
    $db->beginTransaction();
    try {
        // FIX-ALL K1/K3: kunci ulang order DI DALAM transaksi dan periksa
        // ulang status (snapshot pra-transaksi bisa kedaluwarsa karena cron
        // atau konfirmasi dari perangkat lain). release_escrow /
        // settle_cash_fees mengunci + memeriksa ulang sekali lagi.
        $st2 = $db->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE'); $st2->execute([$id]); $locked = $st2->fetch();
        if (!$locked || !in_array($locked['status'], ['held', 'cash_received'], true)) { throw new RuntimeException('Order ini sudah selesai / tidak bisa dikonfirmasi lagi.'); }
        $o = $locked;
        if ($o['status'] === 'held') {
            $es = $db->prepare('SELECT * FROM escrow_holds WHERE order_id = ?'); $es->execute([$id]); $esc = $es->fetch();
            if (!$esc || $esc['status'] !== 'held') { throw new RuntimeException('Escrow tidak dalam status ditahan.'); }
            release_escrow($db, $o, $esc);
        } else {
            // Tanpa fitur hutang: settle bisa DITAHAN (saldo penjual belum
            // cukup) — order menjadi fee_hold, bukan completed.
            if (!settle_cash_fees($db, $o)) { $feeHeld = true; }
        }
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); flash('error', $e->getMessage()); redirect(url('order', ['id' => $id])); }
    if ($feeHeld) { flash('error', 'Order sudah dikonfirmasi, tetapi fee platform belum bisa dipotong karena saldo dompet penjual belum cukup. Order ditahan (Menunggu fee penjual) sampai penjual top up.'); redirect(url('order', ['id' => $id])); }
    flash('success', 'Terima kasih! Order selesai.'); redirect(url('order', ['id' => $id]));
}

// Penjual/admin menyelesaikan ulang fee order tunai yang tertahan
// (status fee_hold) setelah penjual top up. Tanpa fitur hutang: bila
// saldo masih kurang, order tetap fee_hold dan bisa dicoba lagi.
// Hanya di fee_hold (bukan cash_received) agar tidak melewati
// konfirmasi pembeli.
function order_settle_cash(): void
{
    csrf_check(); $u = require_login();
    $id = (int)($_POST['id'] ?? 0);
    $db = pdo();
    $st = $db->prepare('SELECT * FROM orders WHERE id = ?'); $st->execute([$id]); $o = $st->fetch();
    if (!$o || ((int)$o['seller_id'] !== (int)$u['id'] && $u['role'] !== 'admin')) { http_response_code(403); exit('Bukan order Anda.'); }
    if ($o['status'] !== 'fee_hold') { flash('error', 'Order ini tidak sedang menunggu fee penjual.'); redirect(url('order', ['id' => $id])); }
    $db->beginTransaction();
    try {
        $settled = settle_cash_fees($db, $o);
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); flash('error', $e->getMessage()); redirect(url('order', ['id' => $id])); }
    if ($settled) { flash('success', 'Fee platform dipotong dan order selesai.'); }
    else { flash('error', 'Saldo dompet penjual masih belum cukup untuk fee platform. Top up dulu, lalu coba lagi.'); }
    redirect(url('order', ['id' => $id]));
}

// Pembeli meminta link unduhan baru untuk order digital yang sudah selesai.
// Hanya HASH token yang disimpan di DB; token mentah dibuat di sini, lalu
// pengguna langsung diarahkan ke download.php?token=... (token mentah tidak
// pernah disimpan). Token aktif lama untuk order+produk ini dicabut dulu,
// sehingga selalu hanya ada satu token aktif per pembelian.
function download_token_request(): void
{
    csrf_check(); $u = require_login();
    $orderId = (int)($_POST['order_id'] ?? 0);
    $productId = (int)($_POST['product_id'] ?? 0);
    $db = pdo();
    $st = $db->prepare("SELECT o.* FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE o.id = ? AND oi.product_id = ? AND o.buyer_id = ? AND o.status = 'completed' AND o.fulfillment = 'digital'");
    $st->execute([$orderId, $productId, $u['id']]);
    $o = $st->fetch();
    if (!$o) { flash('error', 'Order digital yang selesai tidak ditemukan.'); redirect(url('my_purchases')); }
    $db->beginTransaction();
    try {
        $db->prepare("UPDATE download_tokens SET status = 'revoked' WHERE order_id = ? AND product_id = ? AND status = 'active'")
            ->execute([$orderId, $productId]);
        $raw = create_download_token($db, $orderId, $productId, (int)$u['id']);
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); flash('error', $e->getMessage()); redirect(url('my_purchases')); }
    redirect('download.php?token=' . urlencode($raw));
}

// Penjual menandai tunai diterima (cash + pickup/COD).
function order_cash_received(): void
{
    csrf_check(); $u = require_login();
    $id = (int)($_POST['id'] ?? 0);
    $db = pdo();
    $st = $db->prepare('SELECT * FROM orders WHERE id = ?'); $st->execute([$id]); $o = $st->fetch();
    if (!$o || (int)$o['seller_id'] !== (int)$u['id']) { http_response_code(403); exit('Bukan order Anda.'); }
    if ($o['status'] !== 'awaiting_fulfillment' || $o['payment_method'] !== 'cash') {
        flash('error', 'Order ini bukan order tunai yang menunggu serah terima.'); redirect(url('order', ['id' => $id]));
    }
    // FASE 1 S4: kunci order FOR UPDATE dalam transaksi + UPDATE bersyarat
    // (hanya dari awaiting_fulfillment + cash). Tanpa ini, UPDATE bisa
    // menimpa status 'disputed' yang dipasang pembeli di antara pra-cek
    // dan UPDATE, sehingga sengketa lolos dari pembekuan.
    $db->beginTransaction();
    try {
        $lk = $db->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE'); $lk->execute([$id]); $locked = $lk->fetch();
        if (!$locked || $locked['status'] !== 'awaiting_fulfillment' || $locked['payment_method'] !== 'cash') {
            throw new RuntimeException('Order ini sudah berubah status (mis. sedang disengketakan).');
        }
        // FIX-ALL B8 dipertahankan: cash_received_at diisi SEKALI (COALESCE)
        // dan tidak pernah diubah lagi.
        $up = $db->prepare("UPDATE orders SET status = 'cash_received', cash_received_at = COALESCE(cash_received_at, ?) WHERE id = ? AND status = 'awaiting_fulfillment' AND payment_method = 'cash'");
        $up->execute([now(), $id]);
        if ($up->rowCount() === 0) { throw new RuntimeException('Order sudah diproses oleh proses lain.'); }
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); flash('error', $e->getMessage()); redirect(url('order', ['id' => $id])); }
    flash('success', 'Ditandai tunai diterima. Menunggu konfirmasi pembeli.'); redirect(url('order', ['id' => $id]));
}

// Penjual menandai barang sudah dikirim (fisik dompet/QRIS, escrow).
// Tombol ini TIDAK mencairkan dana: hanya mencatat shipped_at SEKALI
// (COALESCE + WHERE shipped_at IS NULL) sebagai jangkar jam auto-confirm
// 3 hari. Dana cair lewat konfirmasi pembeli, atau lewat cron bila
// pembeli diam 3 hari sejak shipped_at tanpa komplain. Tanpa shipped_at,
// cron TIDAK pernah auto-cair — anti-manipulasi: penjual tidak bisa
// membakar jam dari tanggal bayar (tekan tombol di hari ke-3 lalu
// langsung cair). Hanya penjual (bukan admin) yang boleh menekan,
// karena ini klaim penjual atas pengirimannya sendiri.
function order_shipped(): void
{
    csrf_check(); $u = require_login();
    $id = (int)($_POST['id'] ?? 0);
    $db = pdo();
    $st = $db->prepare('SELECT * FROM orders WHERE id = ?'); $st->execute([$id]); $o = $st->fetch();
    if (!$o || (int)$o['seller_id'] !== (int)$u['id']) { http_response_code(403); exit('Bukan order Anda.'); }
    if ($o['status'] !== 'held' || !in_array($o['payment_method'], ['wallet', 'qris'], true) || $o['fulfillment'] === 'digital') {
        flash('error', 'Tombol kirim hanya untuk order fisik dompet/QRIS yang dananya ditahan escrow.'); redirect(url('order', ['id' => $id]));
    }
    if (!empty($o['shipped_at'])) { flash('error', 'Barang sudah ditandai dikirim pada ' . $o['shipped_at'] . '.'); redirect(url('order', ['id' => $id])); }
    $db->beginTransaction();
    try {
        $lk = $db->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE'); $lk->execute([$id]); $locked = $lk->fetch();
        if (!$locked || $locked['status'] !== 'held' || !empty($locked['shipped_at'])) {
            throw new RuntimeException('Order ini sudah berubah status / sudah ditandai dikirim.');
        }
        $up = $db->prepare("UPDATE orders SET shipped_at = COALESCE(shipped_at, ?) WHERE id = ? AND status = 'held' AND shipped_at IS NULL");
        $up->execute([now(), $id]);
        if ($up->rowCount() === 0) { throw new RuntimeException('Order sudah diproses oleh proses lain.'); }
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); flash('error', $e->getMessage()); redirect(url('order', ['id' => $id])); }
    flash('success', 'Barang ditandai sudah dikirim. Dana cair saat pembeli konfirmasi, atau otomatis 3 hari sejak sekarang bila pembeli diam tanpa komplain.'); redirect(url('order', ['id' => $id]));
}

// FIX-ALL B2: bayar ulang order QRIS yang gagal dibuatkan QR-nya / link-nya
// hilang. Hanya order awaiting_payment milik pembeli sendiri, metode qris.
// Membuat pembayaran DOKU baru, menyimpan session_id baru + link baru ke
// sesi (pola yang sama dengan checkout), order tetap (stok tidak diubah,
// keranjang tidak disentuh). Token idempotensi mencegah duplikasi lintas
// perangkat; bila DOKU gagal, order tetap awaiting_payment dan bisa dicoba lagi.
function order_repay(): void
{
    csrf_check(); $u = require_login();
    if (!consume_form_token('repay')) { flash('error', 'Form bayar ulang sudah dipakai atau kedaluwarsa. Muat ulang halaman order dan coba lagi.'); redirect(url('my_orders')); }
    $id = (int)($_POST['id'] ?? 0);
    $db = pdo();
    $st = $db->prepare('SELECT * FROM orders WHERE id = ?'); $st->execute([$id]); $o = $st->fetch();
    if (!$o || (int)$o['buyer_id'] !== (int)$u['id']) { http_response_code(403); exit('Bukan order Anda.'); }
    if ($o['status'] !== 'awaiting_payment' || $o['payment_method'] !== 'qris') {
        flash('error', 'Hanya order QRIS yang menunggu pembayaran yang bisa dibayar ulang.'); redirect(url('order', ['id' => $id]));
    }
    try {
        $pay = (new DokuClient())->createQris((float)$o['total'], $o['order_code'], $u['email']);
        $db->prepare('UPDATE orders SET doku_transaction_id = ? WHERE id = ? AND status = ?')->execute([$pay['transaction_id'], $id, 'awaiting_payment']);
        if (!empty($pay['qr_payload'])) { $_SESSION['qris_qr'][$id] = $pay['qr_payload']; }
    } catch (Throwable $e) {
        error_log('order_repay gagal ' . $o['order_code'] . ': ' . $e->getMessage());
        flash('error', 'Pembayaran gagal dibuat, coba lagi.'); redirect(url('order', ['id' => $id]));
    }
    flash('success', 'Pembayaran QRIS baru dibuat. Buka halaman pembayaran dari halaman order.'); redirect(url('order', ['id' => $id]));
}

// FIX-ALL C12: admin mencabut token unduhan (per token, atau semua token
// aktif suatu produk bila product_id diisi tanpa token_id). Dipakai saat
// file terbukti melanggar / penjual curang, dan otomatis pada refund penuh
// digital (lihat disputes.php).
function token_revoke(): void
{
    csrf_check(); require_admin();
    $tokenId = (int)($_POST['token_id'] ?? 0);
    $productId = (int)($_POST['product_id'] ?? 0);
    $db = pdo();
    if ($tokenId > 0) {
        $db->prepare("UPDATE download_tokens SET status = 'revoked' WHERE id = ? AND status = 'active'")->execute([$tokenId]);
    } elseif ($productId > 0) {
        $db->prepare("UPDATE download_tokens SET status = 'revoked' WHERE product_id = ? AND status = 'active'")->execute([$productId]);
    } else {
        flash('error', 'Pilih token atau produk yang akan dicabut.'); redirect(url('admin_tokens'));
    }
    flash('success', 'Token unduhan dicabut.'); redirect(url('admin_tokens'));
}
