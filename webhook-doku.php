<?php
// Webhook DOKU — endpoint pembayaran (top-up QRIS + order QRIS).
// Keamanan: (1) verifikasi Signature Jokul atas header + raw body
// (resep resmi DOKU: Client-Id / Request-Id / Request-Timestamp /
// Request-Target = path URL notifikasi merchant / Digest, HMAC-SHA256
// dengan Secret Key); (2) idempotensi via doku_transaction_id UNIQUE di
// topups/orders + pencocokan invoice (nomor invoice merchant selalu
// dibawa notifikasi) — webhook yang sama yang datang 2x TIDAK boleh
// mengkredit 2x (baris yang sudah paid/completed langsung dibalas 200
// tanpa gerakan uang).
// Catatan: Jokul tidak mengembalikan transaction_id pada pembuatan
// pembayaran; pengenal yang andal adalah nomor invoice (doku_invoice /
// order_code). Pencocokan mencoba transaction_id dulu, lalu invoice.
define('MARKETPLACE_APP', true);
require __DIR__ . '/app/config.php';
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Jakarta');
require __DIR__ . '/app/db.php';
require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/services.php';
require __DIR__ . '/app/doku.php';

header('Content-Type: application/json');
$raw = file_get_contents('php://input');
// Header notifikasi Jokul (nama persis dari dokumen DOKU).
$targetPath = parse_url($_SERVER['REQUEST_URI'] ?? '/webhook-doku.php', PHP_URL_PATH) ?: '/webhook-doku.php';
$headers = [
    'client_id' => $_SERVER['HTTP_CLIENT_ID'] ?? null,
    'request_id' => $_SERVER['HTTP_REQUEST_ID'] ?? null,
    'timestamp' => $_SERVER['HTTP_REQUEST_TIMESTAMP'] ?? null,
    'signature' => $_SERVER['HTTP_SIGNATURE'] ?? null,
    'x_doku_signature' => $_SERVER['HTTP_X_DOKU_SIGNATURE'] ?? null,
    'x_signature' => $_SERVER['HTTP_X_SIGNATURE'] ?? null,
];
$client = new DokuClient();
if (!$client->verifyWebhook((string)$raw, $headers, $targetPath)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
    exit;
}
$payload = json_decode((string)$raw, true);
if (!is_array($payload)) { http_response_code(400); echo json_encode(['status' => 'error']); exit; }
$parsed = DokuClient::parseWebhook($payload);

// FASE 1 S2: ekstrak nominal dari payload notifikasi (format Jokul:
// order.amount; fallback ke amount di level lain). parseWebhook() tidak
// mengekstrak nominal, jadi ekstraksi dilakukan di sini tanpa mengubah
// doku.php. Bila payload TIDAK membawa amount (mis. test-notify.php yang
// hanya mengirim invoice + status), verifikasi nominal dilewati dengan
// catatan log — signature + invoice tetap diverifikasi. Bila amount ADA
// dan berbeda dari nominal baris, JANGAN kredit (lihat cabang SUCCESS).
function webhook_amount(array $payload): ?float
{
    $containers = [$payload];
    if (is_array($payload['data'] ?? null)) { $containers[] = $payload['data']; }
    if (is_array($payload['response'] ?? null)) { $containers[] = $payload['response']; }
    foreach ($containers as $c) {
        if (is_array($c['order'] ?? null) && isset($c['order']['amount']) && is_numeric($c['order']['amount'])) { return (float)$c['order']['amount']; }
        if (isset($c['amount']) && is_numeric($c['amount'])) { return (float)$c['amount']; }
        if (is_array($c['transaction'] ?? null) && isset($c['transaction']['amount']) && is_numeric($c['transaction']['amount'])) { return (float)$c['transaction']['amount']; }
    }
    return null;
}
$tx = $parsed['transaction_id'];
$inv = $parsed['invoice'];
if (!$tx && !$inv) { http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'No transaction_id or invoice']); exit; }

$db = pdo();
try {
    // 1) Top-up? (cocokkan transaction_id, lalu invoice)
    $topup = null;
    if ($tx) {
        $st = $db->prepare('SELECT * FROM topups WHERE doku_transaction_id = ?');
        $st->execute([$tx]); $topup = $st->fetch();
    }
    if (!$topup && $inv) {
        $st = $db->prepare('SELECT * FROM topups WHERE doku_invoice = ?');
        $st->execute([$inv]); $topup = $st->fetch();
    }
    if ($topup) {
        if ($topup['status'] === 'paid') { echo json_encode(['status' => 'ok', 'duplicate' => true]); exit; }
        // FASE 1 S1: SUCCESS yang diputar ulang atas top-up 'refunded'
        // TIDAK BOLEH mengkredit lagi. Balas ok tanpa gerakan uang.
        if ($topup['status'] === 'refunded' && DokuClient::isPaidStatus($parsed['status'])) {
            error_log('DOKU webhook: SUCCESS diputar ulang untuk topup refunded ' . ($topup['doku_invoice'] ?? $topup['id']) . ' — tidak dikredit (S1).');
            echo json_encode(['status' => 'ok', 'refunded_replay' => true]); exit;
        }
        if (DokuClient::isPaidStatus($parsed['status'])) {
            $db->beginTransaction();
            try {
                // Kunci ulang di dalam transaksi sebelum mengubah status.
                $st = $db->prepare("SELECT * FROM topups WHERE id = ? FOR UPDATE"); $st->execute([$topup['id']]); $t = $st->fetch();
                if ($t['status'] === 'paid') { $db->commit(); echo json_encode(['status' => 'ok', 'duplicate' => true]); exit; }
                // FASE 1 S1: kredit HANYA dari status terkunci 'pending'.
                // Status lain yang lolos ke sini (expired/failed/refunded
                // hasil balapan) dibalas ok tanpa gerakan uang.
                if ($t['status'] !== 'pending') {
                    $db->commit();
                    error_log('DOKU webhook: SUCCESS untuk topup status ' . $t['status'] . ' ' . ($t['doku_invoice'] ?? $t['id']) . ' — tidak dikredit (S1).');
                    echo json_encode(['status' => 'ok', 'ignored_status' => $t['status']]); exit;
                }
                // FASE 1 S2: bila payload membawa amount, harus sama dengan
                // nominal baris; bila tidak sama, JANGAN kredit — catat ke
                // orphaned_payments + error_log (pola yang sama dengan
                // SUCCESS terlambat untuk order cancelled).
                $wAmt = webhook_amount($payload);
                if ($wAmt !== null && money($wAmt) !== money((float)$t['amount'])) {
                    $db->rollBack();
                    $db->prepare('INSERT INTO orphaned_payments (invoice, transaction_id, amount, raw_status, note) VALUES (?,?,?,?,?)')
                        ->execute([$inv, $tx, $wAmt, $parsed['status'], 'Nominal webhook topup ' . ($t['doku_invoice'] ?? $t['id']) . ' (' . $wAmt . ') tidak sama dengan nominal baris (' . $t['amount'] . ') — tidak dikredit, tindak lanjuti manual.']);
                    error_log('DOKU webhook: nominal tidak cocok untuk topup ' . ($t['doku_invoice'] ?? $t['id']) . ' (payload ' . $wAmt . ' vs baris ' . $t['amount'] . ') — dicatat ke orphaned_payments.');
                    echo json_encode(['status' => 'ok', 'orphaned' => true, 'amount_mismatch' => true]); exit;
                }
                if ($wAmt === null) {
                    error_log('DOKU webhook: amount tidak ada di payload topup ' . ($t['doku_invoice'] ?? $t['id']) . ' — verifikasi nominal dilewati, lanjut atas dasar signature + invoice.');
                }
                wallet_move($db, (int)$t['user_id'], (float)$t['amount'], 'topup', 'topup', $t['id'], 'Top-up QRIS (DOKU).');
                $db->prepare("UPDATE topups SET status = 'paid', paid_at = ? WHERE id = ?")->execute([now(), $t['id']]);
                $db->commit();
            } catch (Throwable $e) { $db->rollBack(); throw $e; }
        } elseif (DokuClient::isFailedStatus($parsed['status'])) {
            $new = $parsed['status'] === 'EXPIRED' ? 'expired' : 'failed';
            $db->prepare('UPDATE topups SET status = ? WHERE id = ? AND status = ?')->execute([$new, $topup['id'], 'pending']);
        } elseif (DokuClient::isRefundedStatus($parsed['status'])) {
            // FIX-ALL C15 (top-up): refund dashboard DOKU merambat ke sistem.
            // Top-up paid -> 'refunded' + debit kembali dompet; bila saldo
            // tidak cukup, debit semampunya dan catat utang sisanya.
            // Top-up yang belum paid diabaikan (tidak ada yang dikredit).
            if ($topup['status'] === 'paid') {
                $db->beginTransaction();
                try {
                    $st = $db->prepare("SELECT * FROM topups WHERE id = ? FOR UPDATE"); $st->execute([$topup['id']]); $t = $st->fetch();
                    if ($t && $t['status'] === 'paid') {
                        $stB = $db->prepare('SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE'); $stB->execute([$t['user_id']]); $bal = (float)$stB->fetch()['wallet_balance'];
                        $amt = (float)$t['amount']; $payable = min($bal, $amt);
                        if ($payable > 0) { wallet_move($db, (int)$t['user_id'], -$payable, 'adjustment', 'topup', $t['id'], 'Refund top-up via DOKU (dashboard).'); }
                        if (money($amt - $payable) > 0) { record_debt($db, (int)$t['user_id'], null, money($amt - $payable), 'Kekurangan refund top-up ' . ($t['doku_invoice'] ?? $t['id'])); }
                        $db->prepare("UPDATE topups SET status = 'refunded' WHERE id = ? AND status = 'paid'")->execute([$t['id']]);
                    }
                    $db->commit();
                } catch (Throwable $e) { $db->rollBack(); throw $e; }
            }
        } else {
            error_log('DOKU webhook: status top-up tidak dikenali: ' . $parsed['status'] . ' invoice ' . ($inv ?: $tx));
        }
        echo json_encode(['status' => 'ok']); exit;
    }
    // 2) Order QRIS? (cocokkan transaction_id, lalu order_code = invoice)
    $order = null;
    if ($tx) {
        $st = $db->prepare('SELECT * FROM orders WHERE doku_transaction_id = ?');
        $st->execute([$tx]); $order = $st->fetch();
    }
    if (!$order && $inv) {
        $st = $db->prepare('SELECT * FROM orders WHERE order_code = ?');
        $st->execute([$inv]); $order = $st->fetch();
    }
    if ($order) {
        // FIX-ALL B14: SUCCESS yang terlambat untuk order yang SUDAH
        // 'cancelled' TIDAK LAGI dibalas diam-diam sebagai duplicate.
        // Uang ada di DOKU, tidak dikredit ke siapa pun di sini; kasusnya
        // dicatat permanen ke orphaned_payments agar admin menindaklanjuti
        // (refund lewat dashboard DOKU). Halaman admin: admin_orphans.
        if ($order['status'] === 'cancelled' && DokuClient::isPaidStatus($parsed['status'])) {
            $db->prepare('INSERT INTO orphaned_payments (invoice, transaction_id, amount, raw_status, note) VALUES (?,?,?,?,?)')
                ->execute([$inv, $tx, (float)$order['subtotal'], $parsed['status'], 'SUCCESS terlambat untuk order cancelled ' . $order['order_code'] . ' — refund manual via dashboard DOKU.']);
            error_log('DOKU webhook: SUCCESS terlambat untuk order cancelled ' . $order['order_code'] . ' (invoice ' . ($inv ?: $tx) . ') — dicatat ke orphaned_payments.');
            echo json_encode(['status' => 'ok', 'orphaned' => true]); exit;
        }
        if (!in_array($order['status'], ['awaiting_payment'], true)) { echo json_encode(['status' => 'ok', 'duplicate' => true]); exit; }
        if (DokuClient::isPaidStatus($parsed['status'])) {
            $db->beginTransaction();
            try {
                $st = $db->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE'); $st->execute([$order['id']]); $o = $st->fetch();
                if ($o['status'] !== 'awaiting_payment') { $db->commit(); echo json_encode(['status' => 'ok', 'duplicate' => true]); exit; }
                // FASE 1 S2: verifikasi nominal order (subtotal; total =
                // subtotal di checkout). Selisih -> orphaned, tanpa
                // mark_order_paid. Amount absen -> log + lanjut (payload
                // test-notify tidak membawa amount).
                $wAmt = webhook_amount($payload);
                if ($wAmt !== null && money($wAmt) !== money((float)$o['subtotal'])) {
                    $db->rollBack();
                    $db->prepare('INSERT INTO orphaned_payments (invoice, transaction_id, amount, raw_status, note) VALUES (?,?,?,?,?)')
                        ->execute([$inv, $tx, $wAmt, $parsed['status'], 'Nominal webhook order ' . $o['order_code'] . ' (' . $wAmt . ') tidak sama dengan subtotal baris (' . $o['subtotal'] . ') — tidak diproses, tindak lanjuti manual.']);
                    error_log('DOKU webhook: nominal tidak cocok untuk order ' . $o['order_code'] . ' (payload ' . $wAmt . ' vs subtotal ' . $o['subtotal'] . ') — dicatat ke orphaned_payments.');
                    echo json_encode(['status' => 'ok', 'orphaned' => true, 'amount_mismatch' => true]); exit;
                }
                if ($wAmt === null) {
                    error_log('DOKU webhook: amount tidak ada di payload order ' . $o['order_code'] . ' — verifikasi nominal dilewati, lanjut atas dasar signature + invoice.');
                }
                mark_order_paid($db, $o);
                $db->commit();
            } catch (Throwable $e) { $db->rollBack(); throw $e; }
        } elseif (DokuClient::isFailedStatus($parsed['status'])) {
            // FIX-ALL B1: FAILED/EXPIRED untuk order -> batalkan + stok
            // kembali, hanya dari awaiting_payment, guard WHERE status.
            $db->beginTransaction();
            try {
                $st = $db->prepare("SELECT * FROM orders WHERE id = ? AND status = 'awaiting_payment' FOR UPDATE"); $st->execute([$order['id']]); $o = $st->fetch();
                if ($o) {
                    restore_stock($db, (int)$o['id']);
                    $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = 'awaiting_payment'")->execute([$o['id']]);
                }
                $db->commit();
            } catch (Throwable $e) { $db->rollBack(); throw $e; }
        } elseif (DokuClient::isRefundedStatus($parsed['status'])) {
            // FIX-ALL C15 (order): refund dashboard untuk order yang masih
            // 'held' (escrow belum dirilis) -> batalkan escrow, kembalikan
            // dana penuh ke dompet pembeli, order 'refunded', stok kembali.
            // Order yang sudah completed/released TIDAK disentuh di sini
            // (refund atas order selesai ditangani admin lewat sengketa).
            $db->beginTransaction();
            try {
                $st = $db->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE"); $st->execute([$order['id']]); $o = $st->fetch();
                $es = null;
                if ($o) { $esSt = $db->prepare("SELECT * FROM escrow_holds WHERE order_id = ? FOR UPDATE"); $esSt->execute([$o['id']]); $es = $esSt->fetch(); }
                if ($o && $o['status'] === 'held' && $es && $es['status'] === 'held') {
                    wallet_move($db, (int)$o['buyer_id'], (float)$es['amount_held'], 'refund', 'order', $o['id'], 'Refund order via DOKU (dashboard) ' . $o['order_code'] . '.');
                    restore_stock($db, (int)$o['id']);
                    $db->prepare("UPDATE escrow_holds SET status = 'refunded', released_at = ? WHERE id = ? AND status = 'held'")->execute([now(), $es['id']]);
                    $db->prepare("UPDATE orders SET status = 'refunded' WHERE id = ? AND status = 'held'")->execute([$o['id']]);
                } else {
                    error_log('DOKU webhook: REFUNDED untuk order non-held ' . $order['order_code'] . ' (status ' . $order['status'] . ') — tidak diproses otomatis.');
                }
                $db->commit();
            } catch (Throwable $e) { $db->rollBack(); throw $e; }
        } else {
            error_log('DOKU webhook: status order tidak dikenali: ' . $parsed['status'] . ' invoice ' . ($inv ?: $tx));
        }
        echo json_encode(['status' => 'ok']); exit;
    }
    // Transaksi tidak dikenal: tetap 200 agar DOKU tidak retry tanpa henti,
    // tapi catat di error log server untuk investigasi.
    error_log('DOKU webhook: transaksi tidak dikenal: ' . ($tx ?: $inv));
    echo json_encode(['status' => 'ok', 'unknown' => true]);
} catch (Throwable $e) {
    error_log('DOKU webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error']);
}
