<?php
$db = pdo();
$id = (int)($_GET['id'] ?? 0);
$st = $db->prepare("SELECT p.*, u.nama AS seller_nama FROM products p JOIN users u ON u.id = p.seller_id WHERE p.id = ? AND p.status = 'active'");
$st->execute([$id]); $p = $st->fetch();
if (!$p) { echo '<p class="neo-card p-4 font-bold">Produk tidak ditemukan / belum aktif.</p>'; return; }
$imgs = $db->prepare('SELECT path FROM product_images WHERE product_id = ? ORDER BY sort_order');
$imgs->execute([$id]); $imgs = $imgs->fetchAll();
// KITA-UX: ringkasan + daftar ulasan (tabel product_reviews dari migrasi).
// FASE 1 A5/A6: nama pembeli TIDAK ditampilkan (anonim) dan ulasan yang
// disembunyikan admin (hidden = 1) tidak dihitung / tidak tampil.
$rvSt = $db->prepare('SELECT AVG(rating) AS avg_rating, COUNT(*) AS cnt FROM product_reviews WHERE product_id = ? AND hidden = 0');
$rvSt->execute([$id]); $rvAgg = $rvSt->fetch();
$rvCnt = (int)($rvAgg['cnt'] ?? 0); $rvAvg = $rvCnt > 0 ? (float)$rvAgg['avg_rating'] : null;
$rvRows = $db->prepare('SELECT r.* FROM product_reviews r WHERE r.product_id = ? AND r.hidden = 0 ORDER BY r.created_at DESC LIMIT 50');
$rvRows->execute([$id]); $rvRows = $rvRows->fetchAll();
// FASE 1 A4: apakah pembeli saat ini memblokir penjual produk ini.
$isBlocked = false;
if ($user) {
    $bl = $db->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?'); $bl->execute([(int)$user['id'], (int)$p['seller_id']]); $isBlocked = (bool)$bl->fetch();
}
// PREMKU-FITUR: nomor WA penjual (kolom users.whatsapp dari migrasi).
// Dibungkus try/catch supaya halaman produk tetap tampil bila migrasi
// belum diimport (tombol WA tinggal tidak tampil).
$sellerWa = null;
try {
    $waSt = $db->prepare('SELECT whatsapp FROM users WHERE id = ?');
    $waSt->execute([(int)$p['seller_id']]); $sellerWa = $waSt->fetchColumn() ?: null;
} catch (Throwable $e) { $sellerWa = null; }
// URL absolut untuk dibagikan (APP_URL dari config server).
$shareUrl = (defined('APP_URL') ? rtrim(APP_URL, '/') : '') . '/' . url('product', ['id' => (int)$p['id']]);
// KITA-UX: order completed milik pembeli saat ini yang memuat produk ini
// (dropdown invoice di form ulasan) + ulasan miliknya bila sudah ada.
$eligible = []; $myReview = null;
if ($user && (int)$user['id'] !== (int)$p['seller_id']) {
    $el = $db->prepare('SELECT o.id, o.order_code FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE o.buyer_id = ? AND o.status = ? AND oi.product_id = ? GROUP BY o.id, o.order_code ORDER BY o.created_at DESC');
    $el->execute([(int)$user['id'], 'completed', $id]); $eligible = $el->fetchAll();
    $mr = $db->prepare('SELECT * FROM product_reviews WHERE product_id = ? AND buyer_id = ?');
    $mr->execute([$id, (int)$user['id']]); $myReview = $mr->fetch();
}
$stars = static function ($n) { $n = max(0, min(5, (int)$n)); return str_repeat('★', $n) . str_repeat('☆', 5 - $n); };
?>
<p class="neo-eyebrow mb-1">Detail produk</p>
<h1 class="text-3xl font-black"><?= e($p['judul']) ?></h1>
<p class="text-sm font-semibold mt-2">Penjual: <?= e($p['seller_nama']) ?> • <span class="neo-pill <?= $p['tipe'] === 'digital' ? 'bg-[#C9D3EA]' : 'bg-[#FBD9B4]' ?>"><?= e($p['tipe']) ?></span> <span class="neo-pill bg-[#DFE6C6]">✓ Terverifikasi</span><?= $p['tipe'] === 'fisik' ? ' • stok: ' . (int)$p['stok'] : '' ?><?= $p['tipe'] === 'fisik' && (int)$p['cod_available'] ? ' • COD tersedia' : '' ?><?= $p['tipe'] === 'fisik' && (int)$p['pickup_available'] ? ' • Pickup tersedia' : '' ?><?= $p['tipe'] === 'fisik' && $p['stok'] !== null && (int)$p['stok'] <= 0 ? ' <span class="neo-pill bg-[#F9C5D8]">Stok habis</span>' : '' ?></p>
<?php if ($rvCnt > 0): ?><p class="text-sm font-bold mt-2"><span class="text-[#EC4899]"><?= $stars((int)round($rvAvg)) ?></span> <?= number_format($rvAvg, 1) ?> / 5 • <?= $rvCnt ?> ulasan</p><?php endif; ?>
<?php if ($imgs): ?>
<div class="my-4">
  <img src="uploads/<?= e($imgs[0]['path']) ?>" alt="" class="w-full max-w-xl h-auto object-cover rounded-2xl border-[3px] border-black shadow-[4px_4px_0_#000]">
  <?php if (count($imgs) > 1): ?><div class="flex gap-3 flex-wrap mt-3"><?php foreach (array_slice($imgs, 1) as $im): ?><img src="uploads/<?= e($im['path']) ?>" alt="" class="w-24 h-24 object-cover rounded-xl border-[3px] border-black"><?php endforeach; ?></div><?php endif; ?>
</div>
<?php endif; ?>
<div class="neo-card p-5 bg-[#FCF3D4]">
<p class="text-4xl font-black"><?= rp($p['harga']) ?></p>
<p class="text-sm font-semibold mt-1">Harga sudah termasuk fee platform <?= fee_percent() ?>%</p>
<div class="mt-4 flex flex-wrap gap-2 items-center">
<?php if ($isBlocked): ?>
<p class="text-sm font-bold">Anda memblokir penjual produk ini, jadi tombol ORDER NOW disembunyikan.</p>
<form method="post" action="index.php"><input type="hidden" name="action" value="unblock_seller"><?= csrf_field() ?><input type="hidden" name="seller_id" value="<?= (int)$p['seller_id'] ?>"><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><button class="neo-btn neo-btn-white px-5 py-2">Buka blokir penjual</button></form>
<?php elseif ($user && (int)$user['id'] !== (int)$p['seller_id']): ?>
<?php if ($p['tipe'] === 'fisik' && $p['stok'] !== null && (int)$p['stok'] <= 0): ?>
<p class="text-sm font-bold">Stok produk ini habis.</p>
<?php else: ?>
<form method="post" action="index.php" class="flex flex-wrap gap-2 items-center">
  <?= csrf_field() ?><input type="hidden" name="action" value="cart_add"><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="return_to" value="<?= e(url('product', ['id' => (int)$p['id']])) ?>">
  <?php if ($p['tipe'] === 'fisik'): ?><input type="number" name="qty" value="1" min="1" max="<?= (int)$p['stok'] ?>" class="neo-input px-3 py-2 w-20"><?php endif; ?>
  <button class="neo-btn neo-btn-pink px-6 py-2">ORDER NOW</button>
</form>
<?php endif; ?>
<?php elseif ($user): ?><p class="text-sm font-bold">Ini produk Anda sendiri.</p>
<?php else: ?><p class="font-bold"><a class="underline decoration-[3px]" href="<?= url('login') ?>">Login</a> untuk membeli.</p><?php endif; ?>
<?php if (!empty($p['demo_url'] ?? '')): ?><a href="<?= e($p['demo_url']) ?>" target="_blank" rel="noopener" class="neo-btn neo-btn-white px-5 py-2">Lihat Demo</a><?php endif; ?>
</div>
<?php if ($sellerWa && !$isBlocked && (!$user || (int)$user['id'] !== (int)$p['seller_id'])): ?>
<div class="mt-3">
  <a href="https://wa.me/<?= e($sellerWa) ?>?text=<?= rawurlencode('Halo, saya tertarik dengan produk ' . $p['judul'] . ' di Marketplace Bekasi Utara') ?>" target="_blank" rel="noopener" class="neo-btn neo-btn-white px-5 py-2">Hubungi penjual via WhatsApp</a>
  <p class="text-xs font-semibold mt-1">Chat hanya untuk tanya-jawab. Transaksi tetap lewat aplikasi supaya escrow dan komplain tetap berlaku.</p>
</div>
<?php endif; ?>
<div class="mt-3 flex flex-wrap gap-2 items-center">
  <button type="button" class="neo-btn neo-btn-white px-5 py-2" data-share-url="<?= e($shareUrl) ?>" onclick="mbuShare(this)">Bagikan</button>
  <a href="https://wa.me/?text=<?= rawurlencode($p['judul'] . ' — ' . $shareUrl) ?>" target="_blank" rel="noopener" class="neo-btn neo-btn-white px-5 py-2">Bagikan via WhatsApp</a>
</div>
<script>
function mbuShare(btn) {
  var u = btn.getAttribute('data-share-url');
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(u).then(function () { alert('Link produk disalin.'); }, function () { mbuShareFallback(u); });
  } else { mbuShareFallback(u); }
}
function mbuShareFallback(u) {
  var ta = document.createElement('textarea');
  ta.value = u; document.body.appendChild(ta); ta.select();
  try { document.execCommand('copy'); alert('Link produk disalin.'); } catch (e) { alert('Salin manual: ' + u); }
  document.body.removeChild(ta);
}
</script>
<?php if ($user && (int)$user['id'] !== (int)$p['seller_id']): ?>
<div class="mt-4 flex flex-wrap gap-2 items-center">
<form method="post" action="index.php" class="flex gap-2 items-center"><input type="hidden" name="action" value="report_submit"><?= csrf_field() ?><input type="hidden" name="target_type" value="product"><input type="hidden" name="target_id" value="<?= (int)$p['id'] ?>"><input name="reason" required placeholder="Alasan laporan produk ini..." class="neo-input px-3 py-1 text-sm w-56"><button class="neo-btn neo-btn-white px-4 py-1 text-sm">Laporkan produk</button></form>
<?php if (!$isBlocked): ?><form method="post" action="index.php"><input type="hidden" name="action" value="block_seller"><?= csrf_field() ?><input type="hidden" name="seller_id" value="<?= (int)$p['seller_id'] ?>"><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><button class="neo-btn neo-btn-white px-4 py-1 text-sm">Blokir penjual</button></form><?php endif; ?>
</div>
<?php endif; ?>
<?php if ($p['tipe'] === 'digital'): ?><p class="text-sm font-semibold mt-3">Produk digital — setelah pembayaran terkonfirmasi, file bisa diunduh lewat Pembelian Digital (token 24 jam, maks 3x unduh).</p><?php endif; ?>
</div>
<div class="neo-card p-5 mt-4">
<p class="mt-1 whitespace-pre-line font-medium"><?= e($p['deskripsi']) ?></p>
<?php foreach (['spesifikasi' => 'Spesifikasi', 'kelebihan' => 'Kelebihan', 'kekurangan' => 'Kekurangan', 'alasan_jual' => 'Alasan dijual'] as $k => $label): ?>
  <?php if (!empty($p[$k])): ?><h2 class="font-black text-lg mt-4"><?= $label ?></h2><p class="whitespace-pre-line font-medium"><?= e($p[$k]) ?></p><?php endif; ?>
<?php endforeach; ?>
</div>
<section class="mt-8">
<h2 class="font-black text-2xl mb-3">Ulasan pembeli</h2>
<?php if ($rvCnt === 0): ?><p class="neo-card p-4 font-bold">Belum ada ulasan untuk produk ini.</p>
<?php else: ?>
<div class="space-y-2">
<?php foreach ($rvRows as $r): ?>
<div class="neo-card p-4 text-sm">
  <p><strong><?= $user && (int)$user['id'] === (int)$r['buyer_id'] ? 'Anda' : 'Pembeli terverifikasi' ?></strong> • <span class="text-[#EC4899]"><?= $stars((int)$r['rating']) ?></span> • <?= e($r['created_at']) ?><?php if ($user && (int)$user['id'] === (int)$r['buyer_id']): ?> • <span class="font-semibold">Nama Anda disembunyikan dari publik</span><?php endif; ?></p>
  <p class="whitespace-pre-line font-medium mt-1"><?= e($r['comment']) ?></p>
  <?php if ($user): ?><form method="post" action="index.php" class="flex gap-2 items-center mt-2"><input type="hidden" name="action" value="report_submit"><?= csrf_field() ?><input type="hidden" name="target_type" value="review"><input type="hidden" name="target_id" value="<?= (int)$r['id'] ?>"><input name="reason" required placeholder="Alasan laporan ulasan ini..." class="neo-input px-3 py-1 text-sm w-56"><button class="neo-btn neo-btn-white px-4 py-1 text-sm">Laporkan ulasan</button></form><?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($user && (int)$user['id'] !== (int)$p['seller_id'] && $eligible): ?>
<form method="post" action="index.php" class="neo-card p-5 mt-4 space-y-3">
<?= csrf_field() ?><input type="hidden" name="action" value="review_submit"><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
<?php if ($myReview): ?><p class="text-sm font-bold">Anda sudah menilai produk ini (<?= (int)$myReview['rating'] ?>/5) — mengirim lagi akan memperbarui ulasan Anda.</p><?php endif; ?>
<label class="block text-sm font-bold">Invoice (order completed Anda untuk produk ini)
<select name="order_id" required class="neo-input px-3 py-2 w-full mt-1">
<?php foreach ($eligible as $eo): ?><option value="<?= (int)$eo['id'] ?>"><?= e($eo['order_code']) ?></option><?php endforeach; ?>
</select></label>
<label class="block text-sm font-bold">Rating
<select name="rating" required class="neo-input px-3 py-2 w-full mt-1">
<option value="5">5 — Sangat bagus</option>
<option value="4">4 — Bagus</option>
<option value="3">3 — Cukup</option>
<option value="2">2 — Kurang</option>
<option value="1">1 — Sangat buruk</option>
</select></label>
<label class="block text-sm font-bold">Komentar<textarea name="comment" required class="neo-input px-3 py-2 w-full mt-1"><?= e($myReview['comment'] ?? '') ?></textarea></label>
<button class="neo-btn neo-btn-pink px-5 py-2">Kirim ulasan</button>
</form>
<?php elseif ($user && (int)$user['id'] !== (int)$p['seller_id']): ?>
<p class="neo-card p-4 mt-4 text-sm font-bold">Hanya pembeli yang sudah menyelesaikan order produk ini yang bisa memberi ulasan.</p>
<?php elseif (!$user): ?>
<p class="neo-card p-4 mt-4 text-sm font-bold">Login dan selesaikan pembelian produk ini untuk memberi ulasan.</p>
<?php endif; ?>
</section>
