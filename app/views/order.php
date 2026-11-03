<?php
$u = require_login();
$db = pdo();
$id = (int)($_GET['id'] ?? 0);
$st = $db->prepare('SELECT o.*, bu.nama AS buyer_nama, su.nama AS seller_nama FROM orders o JOIN users bu ON bu.id = o.buyer_id JOIN users su ON su.id = o.seller_id WHERE o.id = ?');
$st->execute([$id]); $o = $st->fetch();
if (!$o || ((int)$o['buyer_id'] !== (int)$u['id'] && (int)$o['seller_id'] !== (int)$u['id'] && $u['role'] !== 'admin')) { echo '<p class="neo-card p-4 font-bold">Order tidak ditemukan.</p>'; return; }
$items = $db->prepare('SELECT * FROM order_items WHERE order_id = ?'); $items->execute([$id]); $items = $items->fetchAll();
$esc = $db->prepare('SELECT * FROM escrow_holds WHERE order_id = ?'); $esc->execute([$id]); $esc = $esc->fetch();
$isBuyer = (int)$o['buyer_id'] === (int)$u['id'];
$isSeller = (int)$o['seller_id'] === (int)$u['id'];
$neoBadge = static function ($s) { $m = ['paid' => '#DFE6C6', 'completed' => '#DFE6C6', 'active' => '#DFE6C6', 'approved' => '#DFE6C6', 'pending' => '#FDE68A', 'awaiting_payment' => '#FDE68A', 'awaiting_fulfillment' => '#FDE68A', 'investigating' => '#FDE68A', 'open' => '#FDE68A', 'failed' => '#F9C5D8', 'expired' => '#F9C5D8', 'rejected' => '#F9C5D8', 'suspended' => '#F9C5D8', 'archived' => '#F9C5D8', 'held' => '#A9C4D4', 'disputed' => '#C9D3EA', 'cash_received' => '#FBD9B4', 'fee_hold' => '#FDE68A']; return $m[$s] ?? '#FFFFFF'; };
$statusLabel = ['awaiting_payment' => 'Menunggu pembayaran', 'awaiting_fulfillment' => 'Menunggu diproses penjual', 'held' => 'Dana ditahan (escrow)', 'cash_received' => 'Tunai diterima', 'fee_hold' => 'Menunggu fee penjual', 'disputed' => 'Komplain', 'completed' => 'Selesai', 'refunded' => 'Dana dikembalikan', 'cancelled' => 'Dibatalkan'];
?>
<p class="neo-eyebrow mb-1">Detail order</p>
<h1 class="text-3xl font-black"><?= e($o['order_code']) ?></h1>
<p class="text-sm font-semibold mt-2">Status: <span class="neo-pill" style="background:<?= $neoBadge($o['status']) ?>"><?= e($statusLabel[$o['status']] ?? $o['status']) ?></span> • <?= e($o['payment_method']) ?> / <?= e($o['fulfillment']) ?> • Dibuat <?= e($o['created_at']) ?></p>
<p class="text-sm font-semibold">Pembeli: <?= e($o['buyer_nama']) ?> • Penjual: <?= e($o['seller_nama']) ?></p>
<?php if ($o['fulfillment'] === 'pickup'): ?>
<div class="neo-card p-4 mt-4 bg-[#FDE68A] text-sm">
<p class="font-black">⚠ Keamanan bertemu langsung (pickup): bertemu di tempat umum yang ramai dan terang, jangan datang sendirian, beri tahu orang tua / wali / keluarga ke mana Anda pergi, dan jangan membawa uang tunai lebih dari yang diperlukan.</p>
</div>
<?php elseif ($o['fulfillment'] === 'cod_delivery'): ?>
<div class="neo-card p-4 mt-4 bg-[#FDE68A] text-sm">
<p class="font-black">⚠ COD (antar ke tempat pembeli): penjual mengantar barang ke alamat Anda. Periksa barang saat diterima sebelum menekan Konfirmasi barang diterima.<?php if ($o['payment_method'] === 'cash'): ?> Siapkan uang tunai sebesar total order untuk diserahkan ke penjual.<?php endif; ?></p>
</div>
<?php endif; ?>
<div class="neo-table-wrap overflow-x-auto my-4">
<table class="neo-table">
<tr class="text-left"><th class="p-2">Item</th><th class="p-2">Harga</th><th class="p-2">Qty</th><th class="p-2">Subtotal</th></tr>
<?php foreach ($items as $it): ?><tr><td class="p-2 font-bold"><?= e($it['judul_snapshot']) ?></td><td class="p-2"><?= rp($it['harga_snapshot']) ?></td><td class="p-2"><?= (int)$it['qty'] ?></td><td class="p-2"><?= rp($it['subtotal']) ?></td></tr><?php endforeach; ?>
</table>
</div>
<?php $dispFee = !empty($o['fees_locked']) ? (float)$o['platform_fee'] : (float)round((float)$o['subtotal'] * fee_percent() / 100); $dispCod = !empty($o['fees_locked']) ? (float)$o['cod_fee'] : ($o['fulfillment'] === 'cod_delivery' ? cod_fee_flat() : 0.0); ?>
<p class="font-semibold">Subtotal: <?= rp($o['subtotal']) ?> • Fee platform: <?= rp($dispFee) ?> (ditanggung penjual) • Ongkos antar (COD): <?= rp($dispCod) ?> • <strong class="font-black">Total: <?= rp($o['total']) ?></strong></p>
<?php if ($esc): ?><p class="text-sm font-semibold mt-2">Escrow: ditahan <?= rp($esc['amount_held']) ?>, bisa dilepas <?= rp($esc['releasable_amount']) ?>, status <?= e($esc['status']) ?>.</p><?php endif; ?>
<?php if ($o['payment_method'] === 'qris' && $o['status'] === 'awaiting_payment' && !empty($_SESSION['qris_qr'][$id])): ?>
  <?php $qrisLink = $_SESSION['qris_qr'][$id]; ?>
  <div class="neo-card p-4 mt-4 text-sm"><p><strong>Bayar dengan QRIS:</strong></p>
  <?php if (str_starts_with($qrisLink, 'http')): ?>
    <p class="mt-1 font-semibold">Selesaikan pembayaran di halaman DOKU (QRIS ditampilkan di sana):</p>
    <p class="mt-2"><a href="<?= e($qrisLink) ?>" target="_blank" rel="noopener" class="neo-btn neo-btn-pink px-5 py-2">Buka halaman pembayaran</a></p>
    <p class="bg-gray-100 border-2 border-black rounded-xl p-2 break-all font-mono text-xs mt-2"><?= e($qrisLink) ?></p>
  <?php else: ?>
    <p class="bg-gray-100 border-2 border-black rounded-xl p-2 break-all font-mono text-xs mt-1"><?= e($qrisLink) ?></p>
  <?php endif; ?>
  <p class="font-semibold mt-1">Link ini disimpan di sesi (lihat catatan di laporan: tidak ada kolom qr di tabel orders).</p></div>
<?php endif; ?>
<?php if ($isBuyer && $o['payment_method'] === 'qris' && $o['status'] === 'awaiting_payment'): ?>
  <div class="neo-card p-4 mt-4 text-sm"><p class="font-semibold">Pembayaran QRIS gagal / kedaluwarsa? Buat pembayaran baru untuk order ini (order lama dibatalkan otomatis bila kedaluwarsa &gt; 60 menit):</p>
  <form method="post" action="index.php" class="mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="order_repay"><input type="hidden" name="id" value="<?= $id ?>"><?= form_token_field('repay') ?><button class="neo-btn neo-btn-pink px-5 py-2">Bayar ulang (QRIS)</button></form></div>
<?php endif; ?>
<?php if ($o['status'] === 'fee_hold'): ?>
<div class="neo-card p-4 mt-4 bg-[#FDE68A] text-sm"><p class="font-black">⏳ Menunggu fee penjual</p><p class="font-semibold mt-1">Order ini sudah dikonfirmasi, tetapi fee platform belum bisa dipotong karena saldo dompet penjual belum cukup. Setelah penjual top up, order akan selesai.</p>
<?php if ($isSeller || $u['role'] === 'admin'): ?><form method="post" action="index.php" class="mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="order_settle_cash"><input type="hidden" name="id" value="<?= $id ?>"><button class="neo-btn neo-btn-pink px-4 py-2">Selesaikan fee &amp; selesaikan order</button></form><?php endif; ?></div>
<?php endif; ?>
<?php if ($o['status'] === 'held' && !empty($o['shipped_at'])): ?>
<div class="neo-card p-4 mt-4 bg-[#A9C4D4] text-sm"><p class="font-black">📦 Barang sudah dikirim pada <?= e($o['shipped_at']) ?></p><p class="font-semibold mt-1">Dana belum cair. Dana cair saat pembeli menekan "Konfirmasi barang diterima", atau otomatis <?= (int)auto_release_days() ?> hari sejak tanggal kirim bila pembeli diam tanpa komplain.</p></div>
<?php endif; ?>
<?php if ($isSeller && $o['status'] === 'held' && in_array($o['payment_method'], ['wallet', 'qris'], true) && $o['fulfillment'] !== 'digital' && empty($o['shipped_at'])): ?>
<div class="neo-card p-4 mt-4 bg-[#FDE68A] text-sm"><p class="font-black">Tombol kirim (penjual)</p><p class="font-semibold mt-1">Tekan "Barang sudah dikirim" hanya saat barang benar-benar sudah dikirim/diserahkan. Menekan tombol TIDAK langsung mencairkan dana — dana cair saat pembeli konfirmasi, atau otomatis <?= (int)auto_release_days() ?> hari sejak tombol ditekan bila pembeli diam tanpa komplain.</p></div>
<?php endif; ?>
<div class="flex flex-wrap gap-2 mt-6">
<?php if ($isBuyer && $o['status'] === 'awaiting_payment'): ?>
  <form method="post" action="index.php"><?= csrf_field() ?><input type="hidden" name="action" value="order_cancel"><input type="hidden" name="id" value="<?= $id ?>"><button class="neo-btn neo-btn-pastel-pink px-4 py-2">Batalkan order</button></form>
<?php endif; ?>
<?php if ($isSeller && $o['status'] === 'awaiting_fulfillment' && $o['payment_method'] === 'cash'): ?>
  <form method="post" action="index.php"><?= csrf_field() ?><input type="hidden" name="action" value="order_cash_received"><input type="hidden" name="id" value="<?= $id ?>"><button class="neo-btn neo-btn-pink px-4 py-2">Tandai tunai diterima</button></form>
<?php endif; ?>
<?php if ($isSeller && $o['status'] === 'held' && in_array($o['payment_method'], ['wallet', 'qris'], true) && $o['fulfillment'] !== 'digital' && empty($o['shipped_at'])): ?>
  <form method="post" action="index.php"><?= csrf_field() ?><input type="hidden" name="action" value="order_shipped"><input type="hidden" name="id" value="<?= $id ?>"><button class="neo-btn neo-btn-pink px-4 py-2">Barang sudah dikirim</button></form>
<?php endif; ?>
<?php if ($isBuyer && in_array($o['status'], ['held', 'cash_received'], true)): ?>
  <form method="post" action="index.php"><?= csrf_field() ?><input type="hidden" name="action" value="order_confirm"><input type="hidden" name="id" value="<?= $id ?>"><button class="neo-btn neo-btn-pink px-4 py-2">Konfirmasi barang diterima</button></form>
<?php endif; ?>
<?php if ($u['role'] === 'admin' && $o['status'] === 'awaiting_payment'): ?>
  <form method="post" action="index.php"><?= csrf_field() ?><input type="hidden" name="action" value="order_cancel"><input type="hidden" name="id" value="<?= $id ?>"><button class="neo-btn neo-btn-pastel-pink px-4 py-2">Batalkan (admin)</button></form>
<?php endif; ?>
</div>
<?php if ($isBuyer && (in_array($o['status'], ['held', 'cash_received', 'fee_hold', 'awaiting_fulfillment'], true) || ($o['status'] === 'completed' && $o['fulfillment'] === 'digital'))): ?>
<section class="neo-card p-5 mt-6">
  <h2 class="font-black text-lg mb-2">Ajukan komplain (sebagai ganti konfirmasi terima)</h2>
  <p class="text-sm font-semibold mb-2">Komplain adalah pengaduan pembeli karena barang tidak sesuai, rusak, atau tidak lengkap — order dibekukan sampai admin memutuskan.</p>
  <?php if ($o['status'] === 'completed' && $o['fulfillment'] === 'digital'): ?><p class="text-sm font-semibold mb-2">Order digital yang sudah selesai bisa dikomplain dalam 3 hari (refund mencabut token unduhan).</p><?php endif; ?>
  <form method="post" action="index.php" class="space-y-2">
    <?= csrf_field() ?><input type="hidden" name="action" value="dispute_open"><input type="hidden" name="order_id" value="<?= $id ?>">
    <select name="reason" class="neo-input px-3 py-2 w-full">
      <option value="not_as_described">Tidak sesuai deskripsi</option><option value="damaged">Rusak</option><option value="incomplete">Tidak lengkap</option><option value="other">Lainnya</option>
    </select>
    <textarea name="description" required placeholder="Jelaskan masalahnya..." class="neo-input px-3 py-2 w-full"></textarea>
    <button class="neo-btn neo-btn-yellow px-4 py-2">Ajukan komplain</button>
  </form>
</section>
<?php endif; ?>
