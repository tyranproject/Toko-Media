<?php
$u = require_login();
$db = pdo();
$st = $db->prepare('SELECT p.*, c.nama AS cat_nama FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.seller_id = ? ORDER BY p.created_at DESC');
$st->execute([$u['id']]); $rows = $st->fetchAll();
$neoBadge = static function ($s) { $m = ['paid' => '#DFE6C6', 'completed' => '#DFE6C6', 'active' => '#DFE6C6', 'approved' => '#DFE6C6', 'pending' => '#FDE68A', 'awaiting_payment' => '#FDE68A', 'awaiting_fulfillment' => '#FDE68A', 'investigating' => '#FDE68A', 'open' => '#FDE68A', 'failed' => '#F9C5D8', 'expired' => '#F9C5D8', 'rejected' => '#F9C5D8', 'suspended' => '#F9C5D8', 'archived' => '#F9C5D8', 'held' => '#A9C4D4', 'disputed' => '#C9D3EA', 'cash_received' => '#FBD9B4']; return $m[$s] ?? '#FFFFFF'; };
?>
<p class="neo-eyebrow mb-1">Jualanmu</p>
<h1 class="text-3xl font-black mb-4">Produk Saya</h1>
<p class="mb-4"><a class="neo-btn neo-btn-pink px-5 py-2" href="<?= url('product_form') ?>">+ Tambah produk</a></p>
<?php if (!$rows): ?><p class="neo-card p-4 font-bold">Belum ada produk.</p><?php endif; ?>
<div class="space-y-3">
<?php foreach ($rows as $p): ?>
<div class="neo-card p-4 text-sm">
  <p class="font-bold"><?= e($p['judul']) ?> — <span class="neo-pill" style="background:<?= $neoBadge($p['status']) ?>"><?= e($p['status']) ?></span> — <?= rp($p['harga']) ?> <?= $p['tipe'] === 'fisik' ? '• stok ' . (int)$p['stok'] : '• digital' . ($p['drive_file_id'] ? ' • file Drive: ' . e($p['drive_file_name']) : ' • BELUM ADA FILE DRIVE') ?></p>
  <?php if ($p['status'] === 'rejected' && $p['rejection_reason']): ?><p class="neo-flash-error p-3 mt-2">Alasan ditolak: <?= e($p['rejection_reason']) ?></p><?php endif; ?>
  <div class="flex flex-wrap gap-2 mt-3">
    <a class="neo-btn neo-btn-white px-4 py-1 text-sm" href="<?= url('product_form', ['id' => $p['id']]) ?>">Edit</a>
    <?php if ($p['status'] !== 'archived'): ?><form method="post" action="index.php"><?= csrf_field() ?><input type="hidden" name="action" value="product_archive"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="neo-btn neo-btn-pastel-pink px-4 py-1 text-sm">Arsipkan</button></form><?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>
