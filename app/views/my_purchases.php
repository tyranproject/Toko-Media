<?php
$u = require_login();
$db = pdo();
// Daftar pembelian digital yang sudah selesai (sumber tombol unduh).
$st = $db->prepare("SELECT o.id AS order_id, o.order_code, oi.product_id, oi.judul_snapshot FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE o.buyer_id = ? AND o.status = 'completed' AND o.fulfillment = 'digital' ORDER BY o.created_at DESC");
$st->execute([$u['id']]); $orders = $st->fetchAll();
// Riwayat token (audit: hanya hash yang tersimpan, token mentah tidak tampil).
$st = $db->prepare('SELECT t.*, p.judul FROM download_tokens t JOIN products p ON p.id = t.product_id WHERE t.buyer_id = ? ORDER BY t.created_at DESC LIMIT 50');
$st->execute([$u['id']]); $rows = $st->fetchAll();
$neoBadge = static function ($s) { $m = ['paid' => '#DFE6C6', 'completed' => '#DFE6C6', 'active' => '#DFE6C6', 'approved' => '#DFE6C6', 'pending' => '#FDE68A', 'awaiting_payment' => '#FDE68A', 'awaiting_fulfillment' => '#FDE68A', 'investigating' => '#FDE68A', 'open' => '#FDE68A', 'failed' => '#F9C5D8', 'expired' => '#F9C5D8', 'rejected' => '#F9C5D8', 'suspended' => '#F9C5D8', 'archived' => '#F9C5D8', 'held' => '#A9C4D4', 'disputed' => '#C9D3EA', 'cash_received' => '#FBD9B4']; return $m[$s] ?? '#FFFFFF'; };
?>
<p class="neo-eyebrow mb-1">Koleksi digitalmu</p>
<h1 class="text-3xl font-black mb-4">Pembelian Digital Saya</h1>
<p class="text-sm font-semibold mb-4">Klik Unduh untuk membuat token unduhan baru (berlaku <?= download_expiry_hours() ?> jam, maks <?= download_max_uses() ?>× pakai). File di-stream dari Drive lewat server — tidak ada link publik Drive.</p>
<?php if (!$orders): ?><p class="neo-card p-4 font-bold">Belum ada pembelian digital yang selesai.</p><?php endif; ?>
<div class="space-y-3 mb-8">
<?php foreach ($orders as $ord): ?>
<div class="neo-card p-4 text-sm flex flex-wrap gap-3 justify-between items-center">
  <p><strong><?= e($ord['judul_snapshot']) ?></strong> (<?= e($ord['order_code']) ?>)</p>
  <form method="post" action="index.php"><?= csrf_field() ?><input type="hidden" name="action" value="download_token_request"><input type="hidden" name="order_id" value="<?= (int)$ord['order_id'] ?>"><input type="hidden" name="product_id" value="<?= (int)$ord['product_id'] ?>"><button class="neo-btn neo-btn-pink px-4 py-2">Unduh</button></form>
</div>
<?php endforeach; ?>
</div>
<h2 class="font-black text-lg mb-2">Riwayat token</h2>
<div class="space-y-2">
<?php foreach ($rows as $r): ?>
<div class="neo-card p-3 text-sm"><p><?= e($r['judul']) ?> — kedaluwarsa <?= e($r['expires_at']) ?> • pakai <?= (int)$r['uses'] ?>/<?= (int)$r['max_uses'] ?> • <span class="neo-pill" style="background:<?= $neoBadge($r['status']) ?>"><?= e($r['status']) ?></span></p></div>
<?php endforeach; ?>
</div>
