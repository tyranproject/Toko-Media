<?php
require_admin();
$db = pdo();
$rows = $db->query('SELECT w.*, u.nama FROM withdrawals w JOIN users u ON u.id = w.seller_id ORDER BY w.created_at DESC LIMIT 200')->fetchAll();
$neoBadge = static function ($s) { $m = ['paid' => '#DFE6C6', 'completed' => '#DFE6C6', 'active' => '#DFE6C6', 'approved' => '#DFE6C6', 'pending' => '#FDE68A', 'awaiting_payment' => '#FDE68A', 'awaiting_fulfillment' => '#FDE68A', 'investigating' => '#FDE68A', 'open' => '#FDE68A', 'failed' => '#F9C5D8', 'expired' => '#F9C5D8', 'rejected' => '#F9C5D8', 'suspended' => '#F9C5D8', 'archived' => '#F9C5D8', 'held' => '#A9C4D4', 'disputed' => '#C9D3EA', 'cash_received' => '#FBD9B4']; return $m[$s] ?? '#FFFFFF'; };
?>
<p class="neo-eyebrow mb-1">Panel pengelola</p>
<h1 class="text-3xl font-black mb-4">Penarikan (admin transfer manual, lalu tandai selesai)</h1>
<div class="space-y-3">
<?php foreach ($rows as $w): ?>
<div class="neo-card p-4 text-sm">
  <p><strong><?= rp($w['amount']) ?></strong> — <?= e($w['nama']) ?> via <?= e($w['provider'] ?: (withdrawal_method_label($w['method']) . ' (pengajuan lama)')) ?> ke <?= e($w['destination']) ?> a.n. <?= e($w['account_name'] ?: '-') ?> — status <span class="neo-pill" style="background:<?= $neoBadge($w['status']) ?>"><?= e($w['status']) ?></span> — <?= e($w['created_at']) ?><?= $w['transfer_ref'] ? ' — ref ' . e($w['transfer_ref']) : '' ?></p>
  <?php if ($w['status'] === 'pending'): ?>
  <div class="flex flex-wrap gap-2 mt-3">
    <form method="post" action="index.php" class="flex flex-wrap gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="withdrawal_approve"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><input name="admin_note" placeholder="Catatan (opsional)" class="neo-input px-2 py-1"><button class="neo-btn neo-btn-pink px-4 py-1 text-sm">Approve</button></form>
    <form method="post" action="index.php" class="flex flex-wrap gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="withdrawal_reject"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><input name="admin_note" placeholder="Alasan penolakan" class="neo-input px-2 py-1"><button class="neo-btn neo-btn-pastel-pink px-4 py-1 text-sm">Reject (dana kembali)</button></form>
  </div>
  <?php elseif ($w['status'] === 'approved'): ?>
  <form method="post" action="index.php" class="flex flex-wrap gap-2 mt-3"><?= csrf_field() ?><input type="hidden" name="action" value="withdrawal_complete"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><input name="transfer_ref" required placeholder="Nomor referensi transfer" class="neo-input px-2 py-1"><button class="neo-btn neo-btn-pink px-4 py-1 text-sm">Tandai selesai (sudah transfer)</button></form>
  <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
