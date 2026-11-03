<?php
$db = pdo();
$q = trim((string)($_GET['q'] ?? ''));
$cat = (int)($_GET['category_id'] ?? 0);
// FIX-ALL B4 (dipertahankan): produk penjual yang di-suspend tidak tampil.
$sql = "SELECT p.*, u.nama AS seller_nama, c.nama AS category_nama, (SELECT path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS img FROM products p JOIN users u ON u.id = p.seller_id LEFT JOIN categories c ON c.id = p.category_id WHERE p.status = 'active' AND u.status = 'active'";
$params = [];
if ($q !== '') { $sql .= ' AND (p.judul LIKE ? OR p.deskripsi LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($cat > 0) { $sql .= ' AND p.category_id = ?'; $params[] = $cat; }
// FASE 1 A4: pengguna yang login tidak melihat produk dari penjual yang
// ia blokir (tamu melihat semuanya — tidak ada blokir untuk tamu).
if ($user) { $sql .= ' AND p.seller_id NOT IN (SELECT blocked_id FROM user_blocks WHERE blocker_id = ?)'; $params[] = (int)$user['id']; }
$sql .= ' ORDER BY p.created_at DESC LIMIT 100';
$st = $db->prepare($sql); $st->execute($params);
$products = $st->fetchAll();
$cats = $db->query('SELECT * FROM categories ORDER BY nama')->fetchAll();
?>
<form method="get" action="index.php" class="neo-card p-4 flex flex-wrap gap-2 mb-4">
  <input type="hidden" name="page" value="catalog">
  <?php if ($cat > 0): ?><input type="hidden" name="category_id" value="<?= $cat ?>"><?php endif; ?>
  <input name="q" value="<?= e($q) ?>" placeholder="Cari produk..." class="neo-input px-3 py-2 flex-1 min-w-[200px]">
  <button class="neo-btn neo-btn-pink px-5 py-2">Cari</button>
</form>
<div class="flex flex-wrap gap-2 mb-6">
  <a href="<?= url('catalog', $q !== '' ? ['q' => $q] : []) ?>" class="neo-btn <?= $cat === 0 ? 'neo-btn-pink' : 'neo-btn-white' ?> px-4 py-1 text-sm">Semua</a>
  <?php foreach ($cats as $c): ?><a href="<?= url('catalog', array_merge(['category_id' => (int)$c['id']], $q !== '' ? ['q' => $q] : [])) ?>" class="neo-btn <?= $cat === (int)$c['id'] ? 'neo-btn-pink' : 'neo-btn-white' ?> px-4 py-1 text-sm"><?= e($c['nama']) ?></a><?php endforeach; ?>
</div>
<?php if (!$products): ?><p class="neo-card p-4 font-bold">Belum ada produk aktif.</p><?php endif; ?>
<div class="neo-catalog grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
<?php foreach ($products as $p): ?>
  <article class="neo-card p-4">
    <?php if ($p['img']): ?><a href="<?= url('product', ['id' => $p['id']]) ?>"><img src="uploads/<?= e($p['img']) ?>" alt="" class="w-full h-40 object-cover rounded-xl border-[3px] border-black mb-3"></a><?php endif; ?>
    <span class="neo-pill <?= $p['tipe'] === 'digital' ? 'bg-[#C9D3EA]' : 'bg-[#FBD9B4]' ?>"><?= e($p['tipe']) ?></span>
    <span class="neo-pill bg-[#DFE6C6]">✓ Terverifikasi</span>
    <?php if ($p['tipe'] === 'fisik' && $p['stok'] !== null && (int)$p['stok'] === 0): ?><span class="neo-pill bg-[#F9C5D8]">Stok habis</span><?php endif; ?>
    <h2 class="font-black text-lg mt-2"><a class="underline decoration-[3px] hover:text-[#EC4899]" href="<?= url('product', ['id' => $p['id']]) ?>"><?= e($p['judul']) ?></a></h2>
    <p class="text-sm font-semibold">oleh <?= e($p['seller_nama']) ?><?= $p['tipe'] === 'fisik' ? ' • stok: ' . ($p['stok'] === null ? '—' : (int)$p['stok']) : ' • digital' ?></p>
    <p class="text-sm font-semibold">Kategori: <?= e($p['category_nama'] ?: 'Tanpa kategori') ?></p>
    <p class="font-black text-xl mt-2"><?= rp($p['harga']) ?></p>
    <p class="text-xs font-semibold text-gray-600">Harga sudah termasuk fee platform <?= fee_percent() ?>%</p>
  </article>
<?php endforeach; ?>
</div>
