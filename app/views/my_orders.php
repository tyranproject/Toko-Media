<?php
$u = require_login();
$db = pdo();
$st = $db->prepare('SELECT o.*, bu.nama AS buyer_nama, su.nama AS seller_nama FROM orders o JOIN users bu ON bu.id = o.buyer_id JOIN users su ON su.id = o.seller_id WHERE o.buyer_id = ? OR o.seller_id = ? ORDER BY o.created_at DESC LIMIT 100');
$st->execute([$u['id'], $u['id']]); $rows = $st->fetchAll();
$neoBadge = static function ($s) { $m = ['paid' => '#DFE6C6', 'completed' => '#DFE6C6', 'active' => '#DFE6C6', 'approved' => '#DFE6C6', 'pending' => '#FDE68A', 'awaiting_payment' => '#FDE68A', 'awaiting_fulfillment' => '#FDE68A', 'investigating' => '#FDE68A', 'open' => '#FDE68A', 'failed' => '#F9C5D8', 'expired' => '#F9C5D8', 'rejected' => '#F9C5D8', 'suspended' => '#F9C5D8', 'archived' => '#F9C5D8', 'held' => '#A9C4D4', 'disputed' => '#C9D3EA', 'cash_received' => '#FBD9B4', 'fee_hold' => '#FDE68A']; return $m[$s] ?? '#FFFFFF'; };
$statusLabel = ['awaiting_payment' => 'Menunggu pembayaran', 'awaiting_fulfillment' => 'Menunggu diproses penjual', 'held' => 'Dana ditahan (escrow)', 'cash_received' => 'Tunai diterima', 'fee_hold' => 'Menunggu fee penjual', 'disputed' => 'Komplain', 'completed' => 'Selesai', 'refunded' => 'Dana dikembalikan', 'cancelled' => 'Dibatalkan'];
?>
<p class="neo-eyebrow mb-1">Pembeli &amp; penjual</p>
<h1 class="text-3xl font-black mb-4">Order Saya (sebagai pembeli &amp; penjual)</h1>
<?php if (!$rows): ?><p class="neo-card p-4 font-bold">Belum ada order.</p><?php endif; ?>
<div class="space-y-3">
<?php foreach ($rows as $o): ?>
<div class="neo-card p-4 text-sm">
  <p><a class="font-black underline decoration-[3px]" href="<?= url('order', ['id' => $o['id']]) ?>"><?= e($o['order_code']) ?></a> — <span class="neo-pill" style="background:<?= $neoBadge($o['status']) ?>"><?= e($statusLabel[$o['status']] ?? $o['status']) ?></span> — <?= e($o['payment_method']) ?> / <?= e($o['fulfillment']) ?></p>
  <p class="font-medium mt-1">Pembeli: <?= e($o['buyer_nama']) ?> • Penjual: <?= e($o['seller_nama']) ?> • Total: <strong><?= rp($o['total']) ?></strong> • <?= e($o['created_at']) ?></p>
</div>
<?php endforeach; ?>
</div>
