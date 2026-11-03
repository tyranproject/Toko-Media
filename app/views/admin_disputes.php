<?php
require_admin();
$db = pdo();
$rows = $db->query('SELECT d.*, o.order_code, o.payment_method FROM disputes d JOIN orders o ON o.id = d.order_id ORDER BY d.created_at DESC LIMIT 200')->fetchAll();
$neoBadge = static function ($s) { $m = ['paid' => '#DFE6C6', 'completed' => '#DFE6C6', 'active' => '#DFE6C6', 'approved' => '#DFE6C6', 'pending' => '#FDE68A', 'awaiting_payment' => '#FDE68A', 'awaiting_fulfillment' => '#FDE68A', 'investigating' => '#FDE68A', 'open' => '#FDE68A', 'failed' => '#F9C5D8', 'expired' => '#F9C5D8', 'rejected' => '#F9C5D8', 'suspended' => '#F9C5D8', 'archived' => '#F9C5D8', 'held' => '#A9C4D4', 'disputed' => '#C9D3EA', 'cash_received' => '#FBD9B4']; return $m[$s] ?? '#FFFFFF'; };
?>
<p class="neo-eyebrow mb-1">Panel pengelola</p>
<h1 class="text-3xl font-black mb-4">Komplain</h1>
<p class="text-sm font-semibold mb-4">Refund/release/partial hanya menggerakkan uang bila ada escrow. Untuk order tunai, resolusi hanya mencatat keputusan — penyelesaian uang dilakukan manual (lihat komentar di controller disputes).</p>
<?php if (!$rows): ?><p class="neo-card p-4 font-bold">Belum ada komplain.</p><?php endif; ?>
<div class="space-y-3">
<?php foreach ($rows as $d): ?>
<div class="neo-card p-4 text-sm">
  <p class="font-black"><?= e($d['order_code']) ?> (<?= e($d['payment_method']) ?>) — <span class="neo-pill" style="background:<?= $neoBadge($d['status']) ?>"><?= e($d['status']) ?></span> — <?= e($d['reason']) ?></p>
  <p class="font-medium mt-1"><?= e($d['description']) ?></p>
  <?php if (in_array($d['status'], ['open', 'investigating'], true)): ?>
  <form method="post" action="index.php" class="mt-3 space-y-2">
    <?= csrf_field() ?><input type="hidden" name="action" value="dispute_resolve"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
    <select name="decision" class="neo-input px-2 py-1 w-full">
      <option value="refund">Refund penuh (dari escrow ke pembeli)</option>
      <option value="partial">Partial refund</option>
      <option value="release">Release ke penjual (normal)</option>
      <option value="rejected">Tolak komplain (order kembali)</option>
    </select>
    <input name="refund_amount" type="number" step="1" min="0" placeholder="Nominal partial refund (Rp, hanya untuk partial)" class="neo-input px-2 py-1 w-full">
    <textarea name="resolution_note" placeholder="Catatan resolusi" class="neo-input px-2 py-1 w-full"></textarea>
    <button class="neo-btn neo-btn-pink px-4 py-1 text-sm">Putuskan</button>
  </form>
  <?php elseif ($d['resolution_note']): ?><p class="mt-2 font-semibold">Keputusan: <?= e($d['resolution_note']) ?><?= $d['refund_amount'] !== null ? ' (refund ' . rp($d['refund_amount']) . ')' : '' ?></p><?php endif; ?>
</div>
<?php endforeach; ?>
</div>
