<?php
// ============================================================================
// Cron: auto-release escrow + auto-complete tunai + expire top-up.
// Dijaga kunci: ?key=... dibandingkan dengan CRON_KEY memakai hash_equals.
// Jadwal yang disarankan: tiap jam. Auto-release memakai shipped_at
// (tanggal penjual menekan tombol kirim) + auto_release_days dari
// settings (default 3 hari). Tanpa shipped_at, order held TIDAK pernah
// auto-cair — tombol kirim adalah syarat jam auto-confirm berjalan.
// Order dengan sengketa terbuka DILEWATI (dibekukan sampai diputus admin).
// ============================================================================
define('MARKETPLACE_APP', true);
require __DIR__ . '/app/config.php';
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Jakarta');
require __DIR__ . '/app/db.php';
require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/services.php';

header('Content-Type: application/json');
if (!hash_equals((string)CRON_KEY, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid key']);
    exit;
}
$db = pdo();
$released = 0; $cashCompleted = 0; $topupsExpired = 0; $ordersCancelledExpired = 0;
$cutoff = date('Y-m-d H:i:s', time() - auto_release_days() * 86400);

// 1) Auto-release escrow: order held yang SUDAH ditandai dikirim
// (shipped_at terisi) dan shipped_at melewati batas, tanpa sengketa
// terbuka. Order held yang belum ditekan tombol kirimnya SENGAJA
// dilewati — jam auto-confirm belum berjalan.
// FIX-ALL C14: pengecekan sengketa DIPINDAH KE DALAM transaksi per-order,
// SETELAH baris order dikunci FOR UPDATE (has_open_dispute_locked) —
// sengketa yang dibuka setelah pra-cek kini terlihat sebelum rilis.
$st = $db->prepare("SELECT * FROM orders WHERE status = 'held' AND shipped_at IS NOT NULL AND shipped_at <= ?");
$st->execute([$cutoff]);
foreach ($st->fetchAll() as $o) {
    $db->beginTransaction();
    try {
        $stL = $db->prepare("SELECT * FROM orders WHERE id = ? AND status = 'held' FOR UPDATE");
        $stL->execute([$o['id']]); $oLocked = $stL->fetch();
        if (!$oLocked) { $db->commit(); continue; }
        if (has_open_dispute_locked($db, (int)$o['id'])) { $db->commit(); continue; }
        $es = $db->prepare("SELECT * FROM escrow_holds WHERE order_id = ? AND status = 'held' FOR UPDATE");
        $es->execute([$o['id']]); $esc = $es->fetch();
        if ($esc) { release_escrow($db, $oLocked, $esc); $released++; }
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); error_log('cron release error order ' . $o['id'] . ': ' . $e->getMessage()); }
}

// 2) Auto-complete tunai: cash_received melewati batas + order fee_hold
// (percobaan ulang tanpa batas waktu tambahan, karena sudah pernah
// lolos gerbang konfirmasi / 3 hari), tanpa sengketa terbuka.
// FIX-ALL B8: diukur dari cash_received_at (diisi sekali saat
// order_cash_received), dengan fallback updated_at untuk order lama
// pra-patch yang belum punya kolom terisi. FIX-ALL C14: cek sengketa
// di dalam transaksi setelah kunci order. Tanpa fitur hutang: bila
// fee belum bisa dipotong, order tetap fee_hold dan dicoba lagi
// pada cron berikutnya (tidak dihitung completed, tanpa error).
$st = $db->prepare("SELECT * FROM orders WHERE (status = 'cash_received' AND COALESCE(cash_received_at, updated_at) <= ?) OR status = 'fee_hold'");
$st->execute([$cutoff]);
foreach ($st->fetchAll() as $o) {
    $db->beginTransaction();
    try {
        $st2 = $db->prepare("SELECT * FROM orders WHERE id = ? AND status IN ('cash_received','fee_hold') FOR UPDATE");
        $st2->execute([$o['id']]); $o2 = $st2->fetch();
        if (!$o2) { $db->commit(); continue; }
        if (has_open_dispute_locked($db, (int)$o['id'])) { $db->commit(); continue; }
        if (settle_cash_fees($db, $o2)) { $cashCompleted++; }
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); error_log('cron cash error order ' . $o['id'] . ': ' . $e->getMessage()); }
}

// FIX-ALL B1 (langkah baru, 1b): auto-cancel order QRIS awaiting_payment
// yang sudah lewat 60 menit sejak dibuat (masa berlaku link DOKU) +
// kembalikan stok dalam transaksi yang sama. Guard status bersyarat agar
// order yang sempat dibayar di tengah jalan tidak ikut dibatalkan.
$expiryCutoff = date('Y-m-d H:i:s', time() - 3600);
$st = $db->prepare("SELECT * FROM orders WHERE status = 'awaiting_payment' AND payment_method = 'qris' AND created_at <= ?");
$st->execute([$expiryCutoff]);
foreach ($st->fetchAll() as $o) {
    $db->beginTransaction();
    try {
        $st2 = $db->prepare("SELECT * FROM orders WHERE id = ? AND status = 'awaiting_payment' FOR UPDATE");
        $st2->execute([$o['id']]); $o2 = $st2->fetch();
        if (!$o2) { $db->commit(); continue; }
        restore_stock($db, (int)$o['id']);
        $up = $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = 'awaiting_payment'"); $up->execute([$o['id']]);
        if ($up->rowCount() > 0) { $ordersCancelledExpired++; }
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); error_log('cron expire-order error order ' . $o['id'] . ': ' . $e->getMessage()); }
}

// 3) Expire top-up pending yang melewati expires_at.
$st = $db->prepare("UPDATE topups SET status = 'expired' WHERE status = 'pending' AND expires_at IS NOT NULL AND expires_at <= ?");
$st->execute([now()]);
$topupsExpired = $st->rowCount();

echo json_encode(['status' => 'ok', 'released' => $released, 'cash_completed' => $cashCompleted, 'topups_expired' => $topupsExpired, 'orders_cancelled_expired' => $ordersCancelledExpired]);
