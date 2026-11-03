<?php
// Dompet: top-up QRIS (DOKU) + penarikan (withdrawal).
// Top-up dikredit PENUH (platform menanggung MDR). Penarikan didebit saat
// request; tolak -> kredit kembali; setuju/selesai -> tidak ada gerakan uang lagi.
if (!defined('MARKETPLACE_APP')) { http_response_code(403); exit('Forbidden'); }

function topup_request(): void
{
    csrf_check(); $u = require_login();
    // FIX-ALL C4: token sekali pakai (lihat helpers.php).
    if (!consume_form_token('topup')) { flash('error', 'Form top-up sudah dipakai atau kedaluwarsa. Muat ulang halaman top-up dan coba lagi.'); redirect(url('topup')); }
    // FIX-ALL C1: tolak nominal pecahan rupiah — DOKU membulatkan ke integer
    // tetapi webhook mengkredit nominal baris, selisih hingga Rp 0,50.
    $rawAmount = post('amount');
    if (!is_numeric($rawAmount) || (float)$rawAmount != floor((float)$rawAmount)) { flash('error', 'Nominal top-up harus bilangan bulat rupiah (tanpa sen).'); redirect(url('topup')); }
    $amount = (float)$rawAmount;
    if ($amount <= 0) { flash('error', 'Nominal top-up harus > 0.'); redirect(url('topup')); }
    $db = pdo();
    // FIX-ALL C17: expires_at 60 menit, sama dengan masa berlaku link DOKU
    // (bukan 24 jam). Cron meng-expire baris setelah 60 menit; view memberi
    // label kedaluwarsa bila expires_at lewat (lihat topup.php).
    $db->prepare('INSERT INTO topups (user_id, amount, status, expires_at) VALUES (?,?,?, DATE_ADD(NOW(), INTERVAL 60 MINUTE))')
        ->execute([$u['id'], money($amount), 'pending']);
    $topupId = (int)$db->lastInsertId();
    try {
        $invoice = 'TOPUP-' . date('Ymd') . '-' . $topupId;
        $pay = (new DokuClient())->createQris(money($amount), $invoice, $u['email']);
        $db->prepare('UPDATE topups SET doku_transaction_id = ?, doku_invoice = ?, qr_payload = ? WHERE id = ?')
            ->execute([$pay['transaction_id'], $invoice, $pay['qr_payload'], $topupId]);
        flash('success', 'Top-up dibuat. Pindai QR untuk membayar.');
    } catch (Throwable $e) {
        $db->prepare("UPDATE topups SET status = 'failed' WHERE id = ?")->execute([$topupId]);
        // FIX-ALL C9: pesan generik ke pengguna; detail gateway hanya log.
        error_log('topup_request gagal: ' . $e->getMessage());
        flash('error', 'Pembayaran gagal dibuat, coba lagi.');
    }
    redirect(url('topup'));
}

function withdrawal_request(): void
{
    csrf_check(); $u = require_login();
    $amount = (float)post('amount');
    $method = post('method'); $dest = post('destination');
    // NEO-WITHDRAWAL: metode kini tiga macam (ewallet / digital / bank)
    // dan daftar penyedia lengkap dari withdrawal_providers(). 'other' =
    // penyedia di luar daftar, namanya diketik bebas lalu disimpan apa
    // adanya. Validasi: penyedia wajib cocok dengan metodenya.
    $providerRaw = post('provider'); $accountName = post('account_name');
    $provider = ($providerRaw === 'other') ? post('provider_other') : $providerRaw;
    $providers = withdrawal_providers();
    $providerOk = $provider !== '' && ($providerRaw === 'other' || in_array($provider, $providers[$method] ?? [], true));
    if (!in_array($method, ['ewallet', 'digital', 'bank'], true) || $dest === '' || $amount <= 0 || $accountName === '' || !$providerOk) {
        flash('error', 'Nominal, metode, penyedia, nama pemilik, dan tujuan wajib diisi dengan benar.'); redirect(url('withdrawals'));
    }
    $db = pdo();
    $db->beginTransaction();
    try {
        // Validasi server-side: >= min_withdrawal, <= saldo.
        // (Blokir penarikan karena utang DIHAPUS bersama fitur hutang.)
        if ($amount < min_withdrawal()) { throw new RuntimeException('Minimal penarikan ' . rp(min_withdrawal()) . '.'); }
        $db->prepare('INSERT INTO withdrawals (seller_id, amount, method, provider, account_name, destination, status) VALUES (?,?,?,?,?,?,?)')
            ->execute([$u['id'], money($amount), $method, $provider, $accountName, $dest, 'pending']);
        $wid = (int)$db->lastInsertId();
        // DEBIT SAAT REQUEST (bukan saat approve).
        wallet_move($db, (int)$u['id'], -money($amount), 'withdrawal', 'withdrawal', $wid, 'Permintaan penarikan.');
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); flash('error', $e->getMessage()); redirect(url('withdrawals')); }
    flash('success', 'Permintaan penarikan dikirim, menunggu admin.'); redirect(url('withdrawals'));
}

function withdrawal_decide(string $decision): void
{
    csrf_check(); require_admin();
    $id = (int)($_POST['id'] ?? 0);
    $note = post('admin_note');
    $db = pdo();
    // FIX-ALL K2: pra-cek di luar transaksi DIHAPUS sebagai penjaga tunggal.
    // Baris dikunci FOR UPDATE + status diperiksa ulang DI DALAM transaksi;
    // UPDATE bersyarat + rowCount sebagai pertahanan kedua. Dua admin yang
    // menolak bersamaan: hanya satu yang berhasil mengkredit kembali.
    $db->beginTransaction();
    try {
        $st = $db->prepare('SELECT * FROM withdrawals WHERE id = ? FOR UPDATE'); $st->execute([$id]); $w = $st->fetch();
        if (!$w || $w['status'] !== 'pending') { throw new RuntimeException('Penarikan tidak dalam status pending.'); }
        if ($decision === 'approve') {
            $up = $db->prepare("UPDATE withdrawals SET status = 'approved', admin_note = ?, decided_at = ? WHERE id = ? AND status = 'pending'"); $up->execute([$note ?: null, now(), $id]);
            if ($up->rowCount() === 0) { throw new RuntimeException('Penarikan sudah diproses oleh admin lain.'); }
        } else {
            // Tolak -> kredit kembali penuh (ledger adjustment).
            wallet_move($db, (int)$w['seller_id'], (float)$w['amount'], 'adjustment', 'withdrawal', $id,
                'Penarikan ditolak admin' . ($note !== '' ? ': ' . $note : '') . '. Dana dikembalikan.');
            $up = $db->prepare("UPDATE withdrawals SET status = 'rejected', admin_note = ?, decided_at = ? WHERE id = ? AND status = 'pending'"); $up->execute([$note ?: null, now(), $id]);
            if ($up->rowCount() === 0) { throw new RuntimeException('Penarikan sudah diproses oleh admin lain.'); }
        }
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); flash('error', $e->getMessage()); redirect(url('admin_withdrawals')); }
    flash('success', 'Penarikan ' . ($decision === 'approve' ? 'disetujui' : 'ditolak') . '.'); redirect(url('admin_withdrawals'));
}

function withdrawal_complete(): void
{
    csrf_check(); require_admin();
    $id = (int)($_POST['id'] ?? 0);
    $ref = post('transfer_ref');
    if ($ref === '') { flash('error', 'Nomor referensi transfer wajib diisi.'); redirect(url('admin_withdrawals')); }
    // FIX-ALL K2 (pola yang sama untuk withdrawal_complete): kunci + cek
    // ulang di dalam transaksi, UPDATE bersyarat + rowCount.
    $db = pdo(); $db->beginTransaction();
    try {
        $st = $db->prepare('SELECT status FROM withdrawals WHERE id = ? FOR UPDATE'); $st->execute([$id]); $w = $st->fetch();
        if (!$w || $w['status'] !== 'approved') { throw new RuntimeException('Hanya penarikan approved yang bisa ditandai selesai.'); }
        // TIDAK ada gerakan uang di sini: sudah didebit saat request.
        $up = $db->prepare("UPDATE withdrawals SET status = 'completed', transfer_ref = ?, completed_at = ? WHERE id = ? AND status = 'approved'"); $up->execute([$ref, now(), $id]);
        if ($up->rowCount() === 0) { throw new RuntimeException('Penarikan sudah diproses oleh admin lain.'); }
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); flash('error', $e->getMessage()); redirect(url('admin_withdrawals')); }
    flash('success', 'Penarikan ditandai selesai (transfer manual).'); redirect(url('admin_withdrawals'));
}
