<?php
$u = require_login();
$db = pdo();
$st = $db->prepare('SELECT d.*, o.order_code FROM disputes d JOIN orders o ON o.id = d.order_id WHERE d.buyer_id = ? OR d.seller_id = ? ORDER BY d.created_at DESC LIMIT 100');
$st->execute([$u['id'], $u['id']]); $rows = $st->fetchAll();
$neoBadge = static function ($s) { $m = ['paid' => '#DFE6C6', 'completed' => '#DFE6C6', 'active' => '#DFE6C6', 'approved' => '#DFE6C6', 'pending' => '#FDE68A', 'awaiting_payment' => '#FDE68A', 'awaiting_fulfillment' => '#FDE68A', 'investigating' => '#FDE68A', 'open' => '#FDE68A', 'failed' => '#F9C5D8', 'expired' => '#F9C5D8', 'rejected' => '#F9C5D8', 'suspended' => '#F9C5D8', 'archived' => '#F9C5D8', 'held' => '#A9C4D4', 'disputed' => '#C9D3EA', 'cash_received' => '#FBD9B4']; return $m[$s] ?? '#FFFFFF'; };
?>
<p class="neo-eyebrow mb-1">Penyelesaian</p>
<h1 class="text-3xl font-black mb-4">Komplain Saya</h1>
<?php if (!$rows): ?><p class="neo-card p-4 font-bold">Belum ada komplain.</p><?php endif; ?>
<div class="space-y-3">
<?php foreach ($rows as $d): ?>
<div class="neo-card p-4 text-sm">
  <p class="font-black"><?= e($d['order_code']) ?> — <span class="neo-pill" style="background:<?= $neoBadge($d['status']) ?>"><?= e($d['status']) ?></span> — alasan: <?= e($d['reason']) ?></p>
  <p class="font-medium mt-1"><?= e($d['description']) ?></p>
  <?php if ($d['resolution_note']): ?><p class="mt-1 font-semibold">Keputusan admin: <?= e($d['resolution_note']) ?><?= $d['refund_amount'] !== null ? ' (refund ' . rp($d['refund_amount']) . ')' : '' ?></p><?php endif; ?>
  <p class="font-semibold"><?= e($d['created_at']) ?></p>
</div>
<?php endforeach; ?>
</div>
