<?php
// Logika uang bersama: dipakai controller, webhook, dan cron agar konsisten.
// Semua fungsi di sini DIHARAPKAN dipanggil di dalam transaksi DB yang sudah
// dibuka pemanggil (kecuali disebutkan). Jangan ubah saldo di luar fungsi ini.
if (!defined('MARKETPLACE_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

// Buat token unduhan untuk satu produk digital dalam satu order.
// Hanya HASH (SHA-256) yang disimpan; token mentah dikembalikan sekali saja.
function create_download_token(PDO $db, int $orderId, int $productId, int $buyerId): string
{
    $raw = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO download_tokens (order_id, product_id, buyer_id, token_hash, expires_at, max_uses) VALUES (?,?,?,?,?,?)')
        ->execute([$orderId, $productId, $buyerId, hash('sha256', $raw),
            date('Y-m-d H:i:s', time() + download_expiry_hours() * 3600), download_max_uses()]);
    return $raw;
}

// Selesaikan order DIGITAL berbayar: kredit penjual (subtotal - fee),
// buat token unduhan per item, tandai completed.
// TIDAK ada escrow untuk produk digital.
function complete_digital_order(PDO $db, array $order): void
{
    // FIX-ALL B6: pakai fee tersimpan di order (snapshot checkout) bila
    // fees_locked=1; bila tidak terkunci (order lama pra-patch), hitung
    // dari settings saat itu seperti perilaku lama.
    $fee = !empty($order['fees_locked']) ? (float)$order['platform_fee'] : (float)round((float)$order['subtotal'] * fee_percent() / 100);
    $fee = (float)round($fee);
    $net = money((float)$order['subtotal'] - $fee);
    // Digital tidak pakai escrow; seluruh net masuk dompet penjual.
    credit_seller($db, (int)$order['seller_id'], $net, 'release', 'order', $order['id'],
        'Penjualan digital ' . $order['order_code'] . ' (fee platform ' . rp($fee) . ').');
    $items = $db->prepare('SELECT product_id FROM order_items WHERE order_id = ?');
    $items->execute([$order['id']]);
    foreach ($items->fetchAll() as $it) {
        create_download_token($db, (int)$order['id'], (int)$it['product_id'], (int)$order['buyer_id']);
    }
    $db->prepare("UPDATE orders SET status = 'completed', released_at = ? WHERE id = ?")
        ->execute([now(), $order['id']]);
}

// Bayar order FISIK (wallet/QRIS): buat escrow hold, status held.
// amount_held = TOTAL yang dibayar pembeli (subtotal + ongkos antar
// bila COD aturan baru); platform_fee dibulatkan ke rupiah terdekat;
// cod_fee = tarif flat bila cod_delivery, else 0.
// ATURAN COD BARU (2026-10-30): ongkos antar dibayar pembeli dan
// diteruskan ke penjual — jadi yang dilepas = total - fee platform.
// Order lama (total = subtotal, aturan lama) tetap: dilepas =
// subtotal - fee - COD. Pembeda: cod_buyer_pays().
function hold_escrow(PDO $db, array $order): void
{
    // FIX-ALL B6: fee dari snapshot order (checkout), bukan settings saat
    // webhook paid tiba (bisa berjam-jam kemudian). Order lama pra-patch
    // (fees_locked=0) memakai perhitungan lama sebagai fallback.
    if (!empty($order['fees_locked'])) {
        $fee = (float)$order['platform_fee'];
        $cod = (float)$order['cod_fee'];
    } else {
        $fee = (float)round((float)$order['subtotal'] * fee_percent() / 100);
        $cod = $order['fulfillment'] === 'cod_delivery' ? cod_fee_flat() : 0.0;
    }
    $amountHeld = money((float)$order['total']);
    $releasable = cod_buyer_pays($order) ? money($amountHeld - $fee) : money($amountHeld - $fee - $cod);
    if ($releasable < 0) { $releasable = 0.0; } // fee melebihi jumlah ditahan: selisih ditanggung platform (tanpa hutang)
    $db->prepare('INSERT INTO escrow_holds (order_id, buyer_id, seller_id, amount_held, platform_fee, cod_fee, releasable_amount) VALUES (?,?,?,?,?,?,?)')
        ->execute([$order['id'], $order['buyer_id'], $order['seller_id'], $amountHeld, $fee, $cod, $releasable]);
    $db->prepare('UPDATE orders SET status = ?, platform_fee = ?, cod_fee = ?, paid_at = ? WHERE id = ?')
        ->execute(['held', $fee, $cod, now(), $order['id']]);
}

// Release escrow ke penjual (konfirmasi pembeli / auto-release / sengketa release).
// FIX-ALL K1: fungsi ini SENDIRI mengunci + memeriksa ulang, sehingga SEMUA
// pemanggil (confirm, cron, dispute) terlindung — bukan hanya pemanggil
// yang kebetulan mengunci. Pertahanan kedua: UPDATE bersyarat + rowCount.
function release_escrow(PDO $db, array $order, array $escrow): void
{
    $st = $db->prepare("SELECT * FROM escrow_holds WHERE id = ? AND status = 'held' FOR UPDATE");
    $st->execute([$escrow['id']]); $locked = $st->fetch();
    if (!$locked) { throw new RuntimeException('Escrow sudah dirilis / tidak dalam status ditahan.'); }
    $st = $db->prepare("SELECT * FROM orders WHERE id = ? AND status = 'held' FOR UPDATE");
    $st->execute([$order['id']]); $oLocked = $st->fetch();
    if (!$oLocked) { throw new RuntimeException('Order tidak dalam status held.'); }
    $escrow = $locked;
    credit_seller($db, (int)$order['seller_id'], (float)$escrow['releasable_amount'], 'release', 'order', $order['id'],
        'Pelepasan escrow ' . $order['order_code'] . '.');
    // Selisih fee hanya mungkin untuk order lama (aturan lama); tanpa
    // fitur hutang, selisih itu ditanggung platform (dicatat di log).
    $shortfall = money((float)$escrow['platform_fee'] + (float)$escrow['cod_fee'] - (float)$escrow['amount_held']);
    if ($shortfall > 0) {
        error_log('COD-HUTANG: selisih fee order lama ' . $order['order_code'] . ' sebesar ' . $shortfall . ' ditanggung platform (tanpa hutang).');
    }
    $up = $db->prepare("UPDATE escrow_holds SET status = 'released', released_at = ? WHERE id = ? AND status = 'held'");
    $up->execute([now(), $escrow['id']]);
    if ($up->rowCount() === 0) { throw new RuntimeException('Escrow sudah dirilis oleh proses lain.'); }
    $up = $db->prepare("UPDATE orders SET status = 'completed', confirmed_at = ?, released_at = ? WHERE id = ? AND status = 'held'");
    $up->execute([now(), now(), $order['id']]);
    if ($up->rowCount() === 0) { throw new RuntimeException('Order sudah selesai oleh proses lain.'); }
}

// Settle order TUNAI: potong fee platform dari dompet penjual.
// ATURAN BARU (2026-10-30): (1) TANPA fitur hutang — bila saldo belum
// cukup, order DITAHAN di status 'fee_hold' (bukan completed) sampai
// penjual top up, lalu diselesaikan ulang (tombol penjual/admin di
// halaman order, atau cron per jam). Tanpa debit apa pun saat ditahan.
// (2) Biaya COD aturan baru dibayar pembeli sebagai upah antar, jadi
// yang dipotong dari dompet penjual HANYA fee platform; order lama
// (total = subtotal) tetap dipotong fee + COD seperti aturan lama.
// Mengembalikan true bila selesai, false bila ditahan.
function settle_cash_fees(PDO $db, array $order): bool
{
    $st = $db->prepare("SELECT * FROM orders WHERE id = ? AND status IN ('cash_received','fee_hold') FOR UPDATE");
    $st->execute([$order['id']]); $locked = $st->fetch();
    if (!$locked) { throw new RuntimeException('Order tidak dalam status cash_received / fee_hold.'); }
    $order = $locked;
    // Fee dari snapshot checkout bila terkunci; order lama pra-patch
    // (fees_locked=0) memakai perhitungan lama sebagai fallback,
    // lalu snapshot-nya ditulis ke order.
    if (!empty($order['fees_locked'])) {
        $fee = (float)$order['platform_fee']; $cod = (float)$order['cod_fee'];
    } else {
        $fee = (float)round((float)$order['subtotal'] * fee_percent() / 100);
        $cod = $order['fulfillment'] === 'cod_delivery' ? cod_fee_flat() : 0.0;
    }
    $feeDue = cod_buyer_pays($order) ? $fee : money($fee + $cod);
    $up = $db->prepare("UPDATE orders SET platform_fee = ?, cod_fee = ? WHERE id = ? AND status IN ('cash_received','fee_hold')");
    $up->execute([$fee, $cod, $order['id']]);
    if ($feeDue <= 0) {
        $up = $db->prepare("UPDATE orders SET status = 'completed' WHERE id = ? AND status IN ('cash_received','fee_hold')");
        $up->execute([$order['id']]);
        if ($up->rowCount() === 0) { throw new RuntimeException('Order tunai sudah diselesaikan oleh proses lain.'); }
        return true;
    }
    $st = $db->prepare('SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE');
    $st->execute([$order['seller_id']]); $bal = (float)$st->fetch()['wallet_balance'];
    if ($bal < $feeDue) {
        // DITAHAN: tanpa debit apa pun; status menjadi fee_hold agar
        // terlihat dan bisa diselesaikan ulang setelah top up.
        $up = $db->prepare("UPDATE orders SET status = 'fee_hold' WHERE id = ? AND status IN ('cash_received','fee_hold')");
        $up->execute([$order['id']]);
        return false;
    }
    wallet_move($db, (int)$order['seller_id'], -$feeDue, 'fee', 'order', $order['id'],
        'Fee platform order tunai ' . $order['order_code'] . '.');
    $up = $db->prepare("UPDATE orders SET status = 'completed' WHERE id = ? AND status IN ('cash_received','fee_hold')");
    $up->execute([$order['id']]);
    if ($up->rowCount() === 0) { throw new RuntimeException('Order tunai sudah diselesaikan oleh proses lain.'); }
    return true;
}

function has_open_dispute_locked(PDO $db, int $orderId): bool {
    $st = $db->prepare("SELECT id FROM disputes WHERE order_id = ? AND status IN ('open','investigating') LIMIT 1 FOR UPDATE");
    $st->execute([$orderId]);
    return (bool)$st->fetch();
}
function has_open_dispute(PDO $db, int $orderId): bool
{
    $st = $db->prepare("SELECT COUNT(*) c FROM disputes WHERE order_id = ? AND status IN ('open','investigating')");
    $st->execute([$orderId]);
    return ((int)$st->fetch()['c']) > 0;
}

// Tandai order QRIS/wallet sebagai lunas (dipakai webhook + simulasi internal).
// Untuk fisik -> escrow hold; untuk digital -> selesai + token.
function mark_order_paid(PDO $db, array $order): void
{
    if ($order['fulfillment'] === 'digital') {
        complete_digital_order($db, $order);
    } else {
        hold_escrow($db, $order);
    }
}
