<?php
// Sengketa: dibuka pembeli sebagai ganti konfirmasi terima, dalam jendela
// auto-release; diputus admin dalam satu transaksi DB.
// CATATAN PENTING (skema): order TUNAI tidak punya escrow, jadi resolusi
// sengketa tunai TIDAK menggerakkan uang secara otomatis — admin hanya
// mencatat keputusan + note, dan penyelesaian uang dilakukan manual di
// luar sistem (mis. transfer / serah terima ulang). Komentar ini juga ada
// di resolver di bawah.
if (!defined('MARKETPLACE_APP')) { http_response_code(403); exit('Forbidden'); }

function dispute_open(): void
{
    csrf_check(); $u = require_login();
    $orderId = (int)($_POST['order_id'] ?? 0);
    $reason = post('reason'); $desc = post('description');
    if (!in_array($reason, ['not_as_described', 'damaged', 'incomplete', 'other'], true) || $desc === '') {
        flash('error', 'Alasan dan deskripsi komplain wajib diisi.'); redirect(url('order', ['id' => $orderId]));
    }
    $db = pdo();
    $st = $db->prepare('SELECT * FROM orders WHERE id = ?'); $st->execute([$orderId]); $o = $st->fetch();
    if (!$o || (int)$o['buyer_id'] !== (int)$u['id']) { http_response_code(403); exit('Bukan order Anda.'); }
    // FIX-ALL B13: order digital yang sudah 'completed' BISA disengketakan
    // dalam 3 hari sejak penyelesaian (anchor released_at). Tanpa ini,
    // pembeli digital tidak punya jalur refund sama sekali.
    $isDigitalCompleted = $o['status'] === 'completed' && $o['fulfillment'] === 'digital';
    if (!$isDigitalCompleted && !in_array($o['status'], ['held', 'cash_received', 'fee_hold', 'awaiting_fulfillment'], true)) {
        flash('error', 'Komplain hanya bisa diajukan dari status held / cash_received / fee_hold / awaiting_fulfillment, atau completed untuk order digital.'); redirect(url('order', ['id' => $orderId]));
    }
    // Jendela: held -> paid_at; cash_received -> cash_received_at (FIX-ALL
    // B8, bukan updated_at yang me-reset tiap update); digital completed ->
    // released_at, jendela tetap 3 hari; lainnya -> created_at.
    if ($isDigitalCompleted) {
        $anchor = $o['released_at'] ?? $o['paid_at'] ?? $o['updated_at'] ?? $o['created_at'];
    } elseif ($o['status'] === 'cash_received' || $o['status'] === 'fee_hold') {
        $anchor = ($o['cash_received_at'] ?? null) ?: ($o['updated_at'] ?? $o['created_at']);
    } elseif ($o['status'] === 'held') {
        $anchor = $o['paid_at'] ?? $o['created_at'];
    } else {
        $anchor = $o['created_at'];
    }
    // fee_hold tidak kena batas jendela: ordernya belum selesai, jadi
    // pembeli harus tetap bisa komplain kapan pun selama tertahan.
    if ($o['status'] !== 'fee_hold' && strtotime($anchor) + auto_release_days() * 86400 < time()) {
        flash('error', 'Jendela komplain sudah lewat (auto-release ' . auto_release_days() . ' hari).'); redirect(url('order', ['id' => $orderId]));
    }
    $db->beginTransaction();
    try {
        // FASE 1 S5: kunci order FOR UPDATE + periksa ulang status DI DALAM
        // transaksi (pra-cek di luar transaksi bisa kedaluwarsa bila cron
        // auto-release / konfirmasi menyelesaikan order di antaranya).
        $lk = $db->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE'); $lk->execute([$orderId]); $locked = $lk->fetch();
        if (!$locked || (int)$locked['buyer_id'] !== (int)$u['id']) { throw new RuntimeException('Order tidak ditemukan / bukan order Anda.'); }
        $lockedDigitalCompleted = $locked['status'] === 'completed' && $locked['fulfillment'] === 'digital';
        if (!$lockedDigitalCompleted && !in_array($locked['status'], ['held', 'cash_received', 'awaiting_fulfillment'], true)) {
            throw new RuntimeException('Status order sudah berubah; komplain tidak bisa diajukan.');
        }
        $db->prepare('INSERT INTO disputes (order_id, buyer_id, seller_id, reason, description) VALUES (?,?,?,?,?)')
            ->execute([$orderId, $u['id'], $o['seller_id'], $reason, $desc]);
        $up = $db->prepare("UPDATE orders SET status = 'disputed' WHERE id = ? AND status = ?"); $up->execute([$orderId, $locked['status']]);
        if ($up->rowCount() === 0) { throw new RuntimeException('Status order berubah saat komplain diajukan.'); }
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); flash('error', $e->getMessage()); redirect(url('order', ['id' => $orderId])); }
    flash('success', 'Komplain diajukan. Order dibekukan sampai admin memutuskan.'); redirect(url('disputes'));
}

function dispute_resolve(): void
{
    csrf_check(); require_admin();
    $id = (int)($_POST['id'] ?? 0);
    $decision = post('decision'); // refund | partial | release | rejected
    $note = post('resolution_note');
    $refundAmount = (float)post('refund_amount');
    if (!in_array($decision, ['refund', 'partial', 'release', 'rejected'], true)) {
        flash('error', 'Keputusan tidak valid.'); redirect(url('admin_disputes'));
    }
    $db = pdo();
    $db->beginTransaction();
    try {
        $st = $db->prepare("SELECT * FROM disputes WHERE id = ? AND status IN ('open','investigating') FOR UPDATE");
        $st->execute([$id]); $d = $st->fetch();
        if (!$d) { throw new RuntimeException('Komplain tidak terbuka.'); }
        $st = $db->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE'); $st->execute([$d['order_id']]); $o = $st->fetch();
        if (!$o || $o['status'] !== 'disputed') { throw new RuntimeException('Order tidak sedang dikomplain.'); }
        $es = $db->prepare('SELECT * FROM escrow_holds WHERE order_id = ? FOR UPDATE'); $es->execute([$o['id']]); $esc = $es->fetch();

        $isDigitalOrder = $o['fulfillment'] === 'digital';
        if ($decision === 'rejected') {
            // Kembali ke status sebelum sengketa. KETERBATASAN SKEMA: tidak ada
            // kolom previous_status di disputes, jadi: ada escrow -> held,
            // order digital completed -> completed, order tunai ->
            // awaiting_fulfillment (penjual menandai ulang cash_received bila
            // tunai sudah diterima). Jam auto-release berjalan dari penanda
            // waktu semula (tidak di-reset; tunai memakai cash_received_at).
            // FASE 1 S5: escrow yang SUDAH dirilis/partial berarti order
            // sudah selesai sebelum sengketa — kembali ke 'completed',
            // JANGAN ke awaiting_fulfillment (di sana order selesai bisa
            // "hidup lagi"). Escrow yang sudah refunded -> 'refunded'.
            if ($esc && $esc['status'] === 'held') { $back = 'held'; }
            elseif ($isDigitalOrder) { $back = 'completed'; }
            elseif ($esc && in_array($esc['status'], ['released', 'partial'], true)) { $back = 'completed'; }
            elseif ($esc && $esc['status'] === 'refunded') { $back = 'refunded'; }
            else { $back = 'awaiting_fulfillment'; }
            $db->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$back, $o['id']]);
            $db->prepare("UPDATE disputes SET status = 'rejected', resolution_note = ?, resolved_at = ? WHERE id = ?")->execute([$note ?: null, now(), $id]);
        } elseif ($decision === 'release') {
            // Digital: penjual SUDAH dibayar saat completion, jadi 'release'
            // berarti sengketa tidak beralasan dan order kembali completed
            // tanpa gerakan uang. Escrow: release seperti semula.
            if ($isDigitalOrder) {
                $db->prepare("UPDATE orders SET status = 'completed' WHERE id = ?")->execute([$o['id']]);
                $db->prepare("UPDATE disputes SET status = 'resolved_release', resolution_note = ?, resolved_at = ? WHERE id = ?")->execute([$note ?: null, now(), $id]);
            } else {
                if (!$esc || $esc['status'] !== 'held') { throw new RuntimeException('Tidak ada escrow yang bisa dilepas untuk order ini.'); }
                release_escrow($db, $o, $esc);
                $db->prepare("UPDATE disputes SET status = 'resolved_release', resolution_note = ?, resolved_at = ? WHERE id = ?")->execute([$note ?: null, now(), $id]);
            }
        } elseif ($decision === 'refund' || $decision === 'partial') {
            if ($isDigitalOrder) {
                // FIX-ALL B13: refund digital — cabut SEMUA token aktif hanya
                // pada refund PENUH (partial: pembeli tetap memegang akses
                // atas pembayaran parsialnya). Dana: debit dompet penjual
                // bila cukup; bila kurang, kekurangannya ditanggung
                // platform (tanpa fitur hutang). Pembeli selalu dikredit penuh.
                $amt = $decision === 'refund' ? (float)$o['subtotal'] : $refundAmount;
                if ($amt <= 0 || $amt > (float)$o['subtotal']) { throw new RuntimeException('Nominal refund digital harus > 0 dan <= subtotal order.'); }
                if ($decision === 'refund') {
                    $db->prepare("UPDATE download_tokens SET status = 'revoked' WHERE order_id = ? AND status = 'active'")->execute([$o['id']]);
                }
                $stB = $db->prepare('SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE'); $stB->execute([$o['seller_id']]); $sBal = (float)$stB->fetch()['wallet_balance'];
                $payable = min($sBal, $amt);
                if ($payable > 0) {
                    wallet_move($db, (int)$o['seller_id'], -$payable, 'refund', 'order', $o['id'], 'Refund digital komplain ' . $o['order_code'] . '.');
                }
                $short = money($amt - $payable);
                if ($short > 0) {
                    error_log('COD-HUTANG: kekurangan refund digital ' . $o['order_code'] . ' sebesar ' . $short . ' ditanggung platform (tanpa hutang).');
                }
                wallet_move($db, (int)$o['buyer_id'], $amt, 'refund', 'order', $o['id'], 'Refund digital komplain ' . $o['order_code'] . '.');
                $db->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$decision === 'refund' ? 'refunded' : 'completed', $o['id']]);
                $db->prepare("UPDATE disputes SET status = ?, resolution_note = ?, refund_amount = ?, resolved_at = ? WHERE id = ?")
                    ->execute([$decision === 'refund' ? 'resolved_refund' : 'resolved_partial', ($note ?: '') . ' [Refund digital: token dicabut bila refund penuh.]', $amt, now(), $id]);
            } elseif ($esc && $esc['status'] !== 'held') {
                // FASE 1 S5: escrow SUDAH dirilis/partial/refunded — tidak
                // ada dana ditahan untuk dikembalikan. Memutus refund/partial
                // di sini akan menghasilkan status 'refunded' TANPA gerakan
                // uang (bug audit lama). Tolak mentah-mentah; admin harus
                // menyelesaikan lewat jalur lain (bukan refund sengketa).
                throw new RuntimeException('Escrow order ini sudah ' . $esc['status'] . '; refund/partial tidak bisa diputus karena dana sudah tidak ditahan.');
            } elseif (!$esc) {
                // ORDER TUNAI: tidak ada escrow, jadi TIDAK ADA gerakan uang
                // otomatis. Keputusan + note dicatat; penyelesaian dilakukan
                // manual di luar sistem. Ini disengaja dan didokumentasikan.
                // FIX-ALL K4: order tunai yang diputus refund/partial TIDAK
                // BOLEH kembali ke awaiting_fulfillment (di sana ia bisa
                // ditandai cash_received lagi dan fee ditagih atas penjualan
                // yang sudah di-refund). Full refund -> 'refunded' (terminal);
                // partial tunai -> 'completed' dengan jumlah yang disepakati
                // dicatat di resolution_note. Aturan "tanpa gerakan uang
                // otomatis untuk tunai" TETAP berlaku — hanya status yang
                // berubah. Cabang 'rejected' di atas tetap kembali ke status
                // pra-sengketa sesuai spec.
                $cashNote = ($note ?: '') . ' [Order tunai: tanpa gerakan uang otomatis; selesaikan manual.]';
                if ($decision === 'partial') {
                    if ($refundAmount <= 0 || $refundAmount > (float)$o['subtotal']) { throw new RuntimeException('Nominal partial refund tunai harus > 0 dan <= subtotal order.'); }
                    $cashNote .= ' [Jumlah yang disepakati: ' . rp($refundAmount) . '.]';
                }
                $db->prepare('UPDATE disputes SET status = ?, resolution_note = ?, refund_amount = ?, resolved_at = ? WHERE id = ?')
                    ->execute([$decision === 'refund' ? 'resolved_refund' : 'resolved_partial', $cashNote, $decision === 'partial' ? $refundAmount : null, now(), $id]);
                $back = $decision === 'refund' ? 'refunded' : 'completed';
                $db->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$back, $o['id']]);
            } else {
                $held = (float)$esc['amount_held'];
                if ($decision === 'refund') {
                    wallet_move($db, (int)$o['buyer_id'], $held, 'refund', 'order', $o['id'], 'Refund komplain ' . $o['order_code'] . '.');
                    restore_stock($db, (int)$o['id']);
                    $db->prepare("UPDATE escrow_holds SET status = 'refunded', released_at = ? WHERE id = ?")->execute([now(), $esc['id']]);
                    $db->prepare("UPDATE orders SET status = 'refunded' WHERE id = ?")->execute([$o['id']]);
                    $db->prepare("UPDATE disputes SET status = 'resolved_refund', resolution_note = ?, refund_amount = ?, resolved_at = ? WHERE id = ?")->execute([$note ?: null, $held, now(), $id]);
                } else {
                    if ($refundAmount <= 0 || $refundAmount > $held) { throw new RuntimeException('Nominal partial refund harus > 0 dan <= dana ditahan.'); }
                    wallet_move($db, (int)$o['buyer_id'], $refundAmount, 'refund', 'order', $o['id'], 'Partial refund komplain ' . $o['order_code'] . '.');
                    // Aturan COD baru: ongkos antar diteruskan ke penjual,
                    // jadi tidak dikurangkan dari sisa penjual; order lama
                    // (total = subtotal) tetap dikurangi COD seperti dulu.
                    // Tanpa fitur hutang: sisa negatif DITOLAK (admin harus
                    // mengecilkan nominal refund), bukan dicatat utang.
                    $sellerNet = cod_buyer_pays($o)
                        ? money($held - $refundAmount - (float)$esc['platform_fee'])
                        : money($held - $refundAmount - (float)$esc['platform_fee'] - (float)$esc['cod_fee']);
                    if ($sellerNet < 0) {
                        throw new RuntimeException('Nominal partial refund terlalu besar: sisa untuk penjual setelah fee platform menjadi negatif. Kurangi nominal refund.');
                    }
                    if ($sellerNet > 0) {
                        credit_seller($db, (int)$o['seller_id'], $sellerNet, 'release', 'order', $o['id'], 'Sisa partial refund komplain ' . $o['order_code'] . '.');
                    }
                    $db->prepare("UPDATE escrow_holds SET status = 'partial', released_at = ? WHERE id = ?")->execute([now(), $esc['id']]);
                    $db->prepare("UPDATE orders SET status = 'completed' WHERE id = ?")->execute([$o['id']]);
                    $db->prepare("UPDATE disputes SET status = 'resolved_partial', resolution_note = ?, refund_amount = ?, resolved_at = ? WHERE id = ?")->execute([$note ?: null, $refundAmount, now(), $id]);
                }
            }
        }
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); flash('error', $e->getMessage()); redirect(url('admin_disputes')); }
    flash('success', 'Komplain diputus: ' . $decision . '.'); redirect(url('admin_disputes'));
}
