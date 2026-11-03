<?php
require_admin();
$db = pdo();
$rows = $db->query('SELECT o.*, bu.nama AS buyer_nama, su.nama AS seller_nama FROM orders o JOIN users bu ON bu.id = o.buyer_id JOIN users su ON su.id = o.seller_id ORDER BY o.created_at DESC LIMIT 200')->fetchAll();
$neoBadge = static function ($s) { $m = ['paid' => '#DFE6C6', 'completed' => '#DFE6C6', 'active' => '#DFE6C6', 'approved' => '#DFE6C6', 'pending' => '#FDE68A', 'awaiting_payment' => '#FDE68A', 'awaiting_fulfillment' => '#FDE68A', 'investigating' => '#FDE68A', 'open' => '#FDE68A', 'failed' => '#F9C5D8', 'expired' => '#F9C5D8', 'rejected' => '#F9C5D8', 'suspended' => '#F9C5D8', 'archived' => '#F9C5D8', 'held' => '#A9C4D4', 'disputed' => '#C9D3EA', 'cash_received' => '#FBD9B4', 'fee_hold' => '#FDE68A']; return $m[$s] ?? '#FFFFFF'; };
$statusLabel = ['awaiting_payment' => 'Menunggu pembayaran', 'awaiting_fulfillment' => 'Menunggu diproses penjual', 'held' => 'Dana ditahan (escrow)', 'cash_received' => 'Tunai diterima', 'fee_hold' => 'Menunggu fee penjual', 'disputed' => 'Komplain', 'completed' => 'Selesai', 'refunded' => 'Dana dikembalikan', 'cancelled' => 'Dibatalkan'];
?>
<p class="neo-eyebrow mb-1">Panel pengelola</p>
<h1 class="text-3xl font-black mb-4">Order &amp; Transaksi</h1>
<div class="neo-table-wrap overflow-x-auto">
<table class="neo-table">
<tr class="text-left"><th class="p-2">Kode</th><th class="p-2">Pembeli → Penjual</th><th class="p-2">Metode</th><th class="p-2">Status</th><th class="p-2">Total</th><th class="p-2">Fee + Antar</th></tr>
<?php foreach ($rows as $o): ?>
<?php $dispFee = !empty($o['fees_locked']) ? (float)$o['platform_fee'] : (float)round((float)$o['subtotal'] * fee_percent() / 100); $dispCod = !empty($o['fees_locked']) ? (float)$o['cod_fee'] : ($o['fulfillment'] === 'cod_delivery' ? cod_fee_flat() : 0.0); ?>
<tr><td class="p-2"><a class="underline decoration-[3px] font-bold" href="<?= url('order', ['id' => $o['id']]) ?>"><?= e($o['order_code']) ?></a></td><td class="p-2"><?= e($o['buyer_nama']) ?> → <?= e($o['seller_nama']) ?></td><td class="p-2"><?= e($o['payment_method']) ?> / <?= e($o['fulfillment']) ?></td><td class="p-2"><span class="neo-pill" style="background:<?= $neoBadge($o['status']) ?>"><?= e($statusLabel[$o['status']] ?? $o['status']) ?></span></td><td class="p-2 font-bold"><?= rp($o['total']) ?></td><td class="p-2"><?= rp($dispFee) ?> + <?= rp($dispCod) ?></td></tr>
<?php endforeach; ?>
</table>
</div>
