<?php
require_admin();
$db = pdo();
// FASE 1 A7: moderasi LENGKAP — admin harus bisa melihat SEMUA yang
// diajukan penjual sebelum approve: semua foto, semua kolom teks,
// kategori, penjual, harga, stok, flag fulfillment, dan demo_url.
$rows = $db->query("SELECT p.*, u.nama AS seller_nama, c.nama AS category_nama FROM products p JOIN users u ON u.id = p.seller_id LEFT JOIN categories c ON c.id = p.category_id WHERE p.status IN ('pending','rejected') ORDER BY p.created_at ASC LIMIT 100")->fetchAll();
$neoBadge = static function ($s) { $m = ['paid' => '#DFE6C6', 'completed' => '#DFE6C6', 'active' => '#DFE6C6', 'approved' => '#DFE6C6', 'pending' => '#FDE68A', 'awaiting_payment' => '#FDE68A', 'awaiting_fulfillment' => '#FDE68A', 'investigating' => '#FDE68A', 'open' => '#FDE68A', 'failed' => '#F9C5D8', 'expired' => '#F9C5D8', 'rejected' => '#F9C5D8', 'suspended' => '#F9C5D8', 'archived' => '#F9C5D8', 'held' => '#A9C4D4', 'disputed' => '#C9D3EA', 'cash_received' => '#FBD9B4']; return $m[$s] ?? '#FFFFFF'; };
?>
<p class="neo-eyebrow mb-1">Panel pengelola</p>
<h1 class="text-3xl font-black mb-4">Moderasi Produk</h1>
<p class="text-sm font-semibold mb-4">Periksa SEMUA foto dan kolom di bawah sebelum Approve. Demo ditampilkan sebagai tautan eksternal saja — jangan dibuka sebagai pratinjau/iframe di dalam panel.</p>
<?php if (!$rows): ?><p class="neo-card p-4 font-bold">Tidak ada produk menunggu moderasi.</p><?php endif; ?>
<div class="space-y-4">
<?php foreach ($rows as $p): ?>
<?php $ph = $db->prepare('SELECT path FROM product_images WHERE product_id = ? ORDER BY sort_order'); $ph->execute([(int)$p['id']]); $photos = $ph->fetchAll(); ?>
<div class="neo-card p-5 text-sm">
  <p class="font-bold text-base"><?= e($p['judul']) ?> — <?= e($p['tipe']) ?> — <?= rp($p['harga']) ?> — penjual <?= e($p['seller_nama']) ?> — kategori <?= e($p['category_nama'] ?: 'Tanpa kategori') ?> — status <span class="neo-pill" style="background:<?= $neoBadge($p['status']) ?>"><?= e($p['status']) ?></span></p>
  <p class="font-semibold mt-1"><?= $p['tipe'] === 'fisik' ? 'Stok: ' . ((int)$p['stok']) : 'Produk digital' ?> • Pickup: <?= (int)$p['pickup_available'] ? 'ya' : 'tidak' ?> • COD: <?= (int)$p['cod_available'] ? 'ya' : 'tidak' ?><?= $p['tipe'] === 'digital' ? ($p['drive_file_id'] ? ' • file: ' . e($p['drive_file_name']) : ' • TANPA FILE (tidak bisa di-approve)') : '' ?></p>
  <?php if ($p['status'] === 'rejected' && !empty($p['rejection_reason'])): ?><p class="font-semibold mt-1">Alasan penolakan sebelumnya: <?= e($p['rejection_reason']) ?></p><?php endif; ?>
  <?php if ($photos): ?><div class="flex gap-3 flex-wrap mt-3"><?php foreach ($photos as $im): ?><img src="uploads/<?= e($im['path']) ?>" alt="" class="w-32 h-32 object-cover rounded-xl border-[3px] border-black"><?php endforeach; ?></div>
  <?php else: ?><p class="font-semibold mt-2">Tidak ada foto.</p><?php endif; ?>
  <p class="whitespace-pre-line font-medium mt-3"><strong>Deskripsi:</strong> <?= e($p['deskripsi']) ?></p>
  <?php foreach (['spesifikasi' => 'Spesifikasi', 'kelebihan' => 'Kelebihan', 'kekurangan' => 'Kekurangan', 'alasan_jual' => 'Alasan dijual'] as $k => $label): ?>
    <?php if (!empty($p[$k])): ?><p class="whitespace-pre-line font-medium mt-2"><strong><?= $label ?>:</strong> <?= e($p[$k]) ?></p><?php endif; ?>
  <?php endforeach; ?>
  <?php if (!empty($p['demo_url'] ?? '')): ?><p class="font-semibold mt-2">Demo: <a href="<?= e($p['demo_url']) ?>" target="_blank" rel="noopener" class="underline decoration-[3px]"><?= e($p['demo_url']) ?></a> — <span class="font-bold">link eksternal — periksa sebelum approve (bukan tautan marketplace, jangan tampilkan sebagai pratinjau/iframe).</span></p><?php endif; ?>
  <div class="flex flex-wrap gap-2 mt-3">
    <form method="post" action="index.php"><?= csrf_field() ?><input type="hidden" name="action" value="product_approve"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="neo-btn neo-btn-pink px-4 py-1 text-sm">Approve</button></form>
    <form method="post" action="index.php" class="flex flex-wrap gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="product_reject"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input name="rejection_reason" required placeholder="Alasan penolakan" class="neo-input px-2 py-1"><button class="neo-btn neo-btn-pastel-pink px-4 py-1 text-sm">Reject</button></form>
  </div>
</div>
<?php endforeach; ?>
</div>
