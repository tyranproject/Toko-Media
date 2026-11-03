<?php
// Lacak order publik — TANPA login. Yang ditampilkan HANYA: kode order,
// status (label Indonesia), jenis serah terima, dan tanggal dibuat.
// TIDAK PERNAH menampilkan nama pembeli/penjual, total, metode bayar,
// atau nominal escrow. Pencarian harus kode persis (bukan LIKE), dengan
// pesan "tidak ditemukan" yang sama untuk kode yang salah maupun yang
// memang tidak ada — pola yang sama dengan penjaga halaman detail order.
$statusLabel = ['awaiting_payment' => 'Menunggu pembayaran', 'awaiting_fulfillment' => 'Menunggu diproses penjual', 'held' => 'Dana ditahan (escrow)', 'cash_received' => 'Tunai diterima', 'disputed' => 'Komplain', 'completed' => 'Selesai', 'refunded' => 'Dana dikembalikan', 'cancelled' => 'Dibatalkan'];
$fulfillmentLabel = ['pickup' => 'Pickup (ambil sendiri)', 'cod_delivery' => 'COD / temu langsung', 'digital' => 'Digital'];
$code = trim((string)($_GET['code'] ?? ''));
$found = null;
if ($code !== '') {
    $st = pdo()->prepare('SELECT order_code, status, fulfillment, created_at FROM orders WHERE order_code = ?');
    $st->execute([$code]); $found = $st->fetch();
}
?>
<p class="neo-eyebrow mb-1">Tanpa login</p>
<h1 class="text-3xl font-black mb-2">Lacak Order</h1>
<p class="text-sm font-semibold mb-4">Masukkan kode order (format ORD-YYYYMMDD-XXXXXX) untuk melihat statusnya. Halaman ini hanya menampilkan status — tanpa nama, tanpa total, tanpa metode bayar.</p>
<form method="get" action="index.php" class="neo-card p-4 flex flex-wrap gap-2 mb-4">
  <input type="hidden" name="page" value="track">
  <input name="code" value="<?= e($code) ?>" placeholder="cth: ORD-20261029-ABC123" class="neo-input px-3 py-2 flex-1 min-w-[220px]">
  <button class="neo-btn neo-btn-pink px-5 py-2">Lacak</button>
</form>
<?php if ($code !== '' && !$found): ?>
<p class="neo-card p-4 font-bold">Order tidak ditemukan. Periksa kembali kode order Anda.</p>
<?php elseif ($found): ?>
<div class="neo-card p-5">
  <p class="font-black text-lg"><?= e($found['order_code']) ?></p>
  <p class="text-sm font-semibold mt-2">Status: <span class="neo-pill bg-[#DFE6C6]"><?= e($statusLabel[$found['status']] ?? $found['status']) ?></span></p>
  <p class="text-sm font-semibold mt-1">Serah terima: <?= e($fulfillmentLabel[$found['fulfillment']] ?? $found['fulfillment']) ?></p>
  <p class="text-sm font-semibold mt-1">Dibuat: <?= e($found['created_at']) ?></p>
  <p class="text-sm font-medium mt-3">Untuk detail lengkap, konfirmasi terima, komplain, atau unduhan digital, login lalu buka Order Saya.</p>
</div>
<?php endif; ?>
