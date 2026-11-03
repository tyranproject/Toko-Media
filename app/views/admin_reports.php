<?php
require_admin();
$db = pdo();
// FASE 1 A4/A6: antrean laporan (pending dulu). Target di-resolve di PHP
// karena target_id polimorfik (produk / ulasan / penjual).
$rows = $db->query("SELECT r.*, u.nama AS reporter_nama FROM reports r JOIN users u ON u.id = r.reporter_id ORDER BY (r.status = 'pending') DESC, r.created_at DESC LIMIT 200")->fetchAll();
$neoBadge = static function ($s) { $m = ['pending' => '#FDE68A', 'reviewed' => '#A9C4D4', 'dismissed' => '#FBD9B4', 'actioned' => '#DFE6C6']; return $m[$s] ?? '#FFFFFF'; };
?>
<p class="neo-eyebrow mb-1">Panel pengelola</p>
<h1 class="text-3xl font-black mb-4">Laporan Pengguna</h1>
<p class="text-sm font-semibold mb-4">Laporan tidak memicu aksi otomatis. Admin memutuskan: reviewed (sudah ditinjau), dismissed (tidak ditindak), atau actioned (sudah ditindak, mis. produk ditolak / ulasan disembunyikan). Untuk ulasan, tombol sembunyikan/tampilkan ada di barisnya.</p>
<?php if (!$rows): ?><p class="neo-card p-4 font-bold">Belum ada laporan.</p><?php endif; ?>
<div class="space-y-3">
<?php foreach ($rows as $r): ?>
<?php
$targetHtml = '';
if ($r['target_type'] === 'product') {
    $t = $db->prepare('SELECT judul FROM products WHERE id = ?'); $t->execute([(int)$r['target_id']]); $t = $t->fetch();
    $targetHtml = $t ? '<a class="underline decoration-[3px]" href="' . e(url('product', ['id' => (int)$r['target_id']])) . '">' . e($t['judul']) . '</a>' : '(produk sudah tidak ada)';
} elseif ($r['target_type'] === 'review') {
    $t = $db->prepare('SELECT r.comment, r.hidden, r.product_id, p.judul FROM product_reviews r LEFT JOIN products p ON p.id = r.product_id WHERE r.id = ?'); $t->execute([(int)$r['target_id']]); $t = $t->fetch();
    if ($t) {
        $targetHtml = 'Ulasan pada <a class="underline decoration-[3px]" href="' . e(url('product', ['id' => (int)$t['product_id']])) . '">' . e($t['judul'] ?? ('produk #' . (int)$t['product_id'])) . '</a>: "' . e(mb_substr((string)$t['comment'], 0, 120)) . '" (' . ((int)$t['hidden'] ? 'tersembunyi' : 'tampil') . ')';
    } else { $targetHtml = '(ulasan sudah tidak ada)'; }
} else {
    $t = $db->prepare('SELECT nama FROM users WHERE id = ?'); $t->execute([(int)$r['target_id']]); $t = $t->fetch();
    $targetHtml = $t ? 'Penjual: ' . e($t['nama']) : '(pengguna sudah tidak ada)';
}
?>
<div class="neo-card p-4 text-sm">
  <p class="font-bold">Laporan #<?= (int)$r['id'] ?> — <?= e($r['target_type']) ?> — status <span class="neo-pill" style="background:<?= $neoBadge($r['status']) ?>"><?= e($r['status']) ?></span></p>
  <p class="font-semibold mt-1">Pelapor: <?= e($r['reporter_nama']) ?> • <?= e($r['created_at']) ?></p>
  <p class="font-semibold mt-1">Target: <?= $targetHtml ?></p>
  <p class="whitespace-pre-line font-medium mt-1">Alasan: <?= e($r['reason']) ?></p>
  <div class="flex flex-wrap gap-2 mt-3">
    <?php if ($r['status'] === 'pending'): ?>
    <form method="post" action="index.php" class="flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="report_decide"><input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>"><select name="decision" class="neo-input px-2 py-1"><option value="reviewed">reviewed</option><option value="dismissed">dismissed</option><option value="actioned">actioned</option></select><button class="neo-btn neo-btn-pink px-4 py-1 text-sm">Putuskan</button></form>
    <?php endif; ?>
    <?php if ($r['target_type'] === 'review'): ?>
    <form method="post" action="index.php"><?= csrf_field() ?><input type="hidden" name="action" value="review_hide"><input type="hidden" name="review_id" value="<?= (int)$r['target_id'] ?>"><button class="neo-btn neo-btn-white px-4 py-1 text-sm">Sembunyikan ulasan</button></form>
    <form method="post" action="index.php"><?= csrf_field() ?><input type="hidden" name="action" value="review_unhide"><input type="hidden" name="review_id" value="<?= (int)$r['target_id'] ?>"><button class="neo-btn neo-btn-white px-4 py-1 text-sm">Tampilkan lagi ulasan</button></form>
    <?php endif; ?>
    <?php if ($r['target_type'] === 'product'): ?>
    <form method="post" action="index.php"><?= csrf_field() ?><input type="hidden" name="action" value="product_reject"><input type="hidden" name="id" value="<?= (int)$r['target_id'] ?>"><input type="hidden" name="rejection_reason" value="Ditolak berdasarkan laporan pengguna."><button class="neo-btn neo-btn-pastel-pink px-4 py-1 text-sm">Tolak produk ini</button></form>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>
