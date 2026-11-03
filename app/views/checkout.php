<?php
$u = require_login();
$db = pdo(); $items = cart(); $groups = [];
foreach ($items as $pid => $qty) {
    $st = $db->prepare('SELECT p.*, u.nama AS seller_nama FROM products p JOIN users u ON u.id = p.seller_id WHERE p.id = ?');
    $st->execute([$pid]); $p = $st->fetch();
    if (!$p) { continue; }
    $groups[(int)$p['seller_id']]['seller'] = $p['seller_nama'];
    $groups[(int)$p['seller_id']]['items'][] = ['p' => $p, 'qty' => $p['tipe'] === 'digital' ? 1 : (int)$qty];
}
// FASE 1 A3: ada item fisik? -> kotak peringatan + checkbox pengakuan
// ditampilkan (checkbox ditegakkan server-side di orders.php checkout()).
$hasFisik = false;
foreach ($groups as $g) { foreach ($g['items'] as $it) { if ($it['p']['tipe'] !== 'digital') { $hasFisik = true; break 2; } } }
?>
<p class="neo-eyebrow mb-1">Satu order per penjual</p>
<h1 class="text-3xl font-black mb-2">Checkout</h1>
<p class="text-sm font-semibold mb-4">Checkout dipecah menjadi satu order per penjual. Total dihitung ulang dari harga produk di server.</p>
<?php if (!$groups): ?><p class="neo-card p-4 font-bold">Keranjang kosong.</p>
<?php else: ?>
<form method="post" action="index.php" class="space-y-6">
<?= csrf_field() ?><input type="hidden" name="action" value="checkout">
<?= form_token_field('checkout') ?>
<?php foreach ($groups as $sid => $g): $types = array_unique(array_column(array_column($g['items'], 'p'), 'tipe')); $isDigital = $types === ['digital']; $sub = 0.0; foreach ($g['items'] as $it) { $sub = money($sub + (float)$it['p']['harga'] * $it['qty']); } ?>
<section class="neo-card p-5">
  <h2 class="font-black text-lg">Penjual: <?= e($g['seller']) ?> — Subtotal <?= rp($sub) ?></h2>
  <ul class="text-sm font-medium list-disc ml-5 my-2"><?php foreach ($g['items'] as $it): ?><li><?= e($it['p']['judul']) ?> × <?= $it['qty'] ?> — <?= rp((float)$it['p']['harga'] * $it['qty']) ?> (<?= e($it['p']['tipe']) ?>)</li><?php endforeach; ?></ul>
  <?php if (count($types) > 1): ?><p class="neo-flash-error p-3 text-sm">Grup ini campuran digital + fisik — pisahkan dulu di keranjang.</p>
  <?php elseif ($isDigital): ?>
    <p class="text-sm font-semibold">Fulfillment: digital • Pembayaran: tunai tidak tersedia untuk digital.</p>
    <label class="text-sm font-bold mr-3"><input type="radio" name="payment[<?= $sid ?>]" value="wallet" checked> Dompet (saldo <?= rp($u['wallet_balance']) ?>)</label>
    <label class="text-sm font-bold"><input type="radio" name="payment[<?= $sid ?>]" value="qris"> QRIS (DOKU)</label>
  <?php else: ?>
    <?php $pickupOk = true; $codOk = true; foreach ($g['items'] as $it) { if (!(int)$it['p']['pickup_available']) { $pickupOk = false; } if (!(int)$it['p']['cod_available']) { $codOk = false; } } ?>
    <div class="text-sm font-medium space-y-2">
      <div>Fulfillment:
        <?php if ($pickupOk): ?><label class="font-bold mr-3"><input type="radio" name="fulfillment[<?= $sid ?>]" value="pickup" checked> Pickup (ambil sendiri)</label><?php else: ?><span class="font-semibold mr-3">Pickup tidak tersedia untuk penjual ini.</span><?php endif; ?>
        <?php if ($codOk): ?><label class="font-bold"><input type="radio" name="fulfillment[<?= $sid ?>]" value="cod_delivery"<?= !$pickupOk ? ' checked' : '' ?>> COD (antar ke tempat Anda)</label><?php else: ?><span class="font-semibold">COD tidak tersedia untuk penjual ini.</span><?php endif; ?>
      </div>
      <div>Pembayaran:
        <label class="font-bold mr-3"><input type="radio" name="payment[<?= $sid ?>]" value="wallet" checked> Dompet</label>
        <label class="font-bold mr-3"><input type="radio" name="payment[<?= $sid ?>]" value="qris"> QRIS (DOKU)</label>
        <label class="font-bold"><input type="radio" name="payment[<?= $sid ?>]" value="cash"> Tunai (bayar saat serah terima)</label>
      </div>
      <p class="font-semibold">Wallet/QRIS: dana ditahan escrow sampai konfirmasi terima / auto-release <?= auto_release_days() ?> hari. Bila memilih COD, ongkos antar <?= rp(cod_fee_flat()) ?> ditambahkan ke total (dibayar pembeli, diterima penjual sebagai upah antar). Tunai: bayar harga + ongkos antar bila COD saat serah terima; fee platform <?= fee_percent() ?>% dipotong dari dompet penjual saat order selesai.</p>
    </div>
  <?php endif; ?>
</section>
<?php endforeach; ?>
<?php if ($hasFisik): ?>
<div class="neo-card p-5 bg-[#FDE68A]">
<p class="font-black text-lg">⚠ Peringatan keamanan serah terima (pickup / COD)</p>
<p class="text-sm font-semibold mt-1">Serah terima dengan orang asing berisiko. Lindungi diri Anda:</p>
<ul class="text-sm font-semibold list-disc ml-5 mt-1">
<li>Pickup: bertemu di tempat umum yang ramai dan terang, sebaiknya di siang hari; jangan datang sendirian.</li>
<li>COD: penjual mengantar ke alamat Anda — pastikan alamat jelas dan terima di tempat yang aman.</li>
<li>Beri tahu orang tua / wali / keluarga ke mana Anda pergi dan dengan siapa.</li>
<li>Jangan membawa uang tunai lebih dari yang diperlukan.</li>
<li>Periksa barang sebelum membayar / mengonfirmasi penerimaan.</li>
</ul>
<label class="block text-sm font-bold mt-3"><input type="checkbox" name="safety_ack" value="1" required> Saya sudah membaca peringatan keamanan serah terima</label>
</div>
<?php endif; ?>
<button class="neo-btn neo-btn-pink px-6 py-2">Buat order</button>
</form>
<?php endif; ?>
