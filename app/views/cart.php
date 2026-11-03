<?php
require_login();
$db = pdo(); $items = cart(); $rows = []; $total = 0.0;
foreach ($items as $pid => $qty) {
    // FIX-ALL C13 (dipertahankan): status penjual ikut diambil untuk label.
    $st = $db->prepare('SELECT p.*, u.nama AS seller_nama, u.status AS seller_status FROM products p JOIN users u ON u.id = p.seller_id WHERE p.id = ?');
    $st->execute([$pid]); $p = $st->fetch();
    if (!$p) { continue; }
    $rows[] = ['p' => $p, 'qty' => $p['tipe'] === 'digital' ? 1 : (int)$qty];
    $total = money($total + (float)$p['harga'] * ($p['tipe'] === 'digital' ? 1 : (int)$qty));
}
?>
<p class="neo-eyebrow mb-1">Belanjaanmu</p>
<h1 class="text-3xl font-black mb-4">Keranjang</h1>
<?php
// FIX-ALL C3 (dipertahankan): peringatan DINI bila satu penjual mencampur
// digital + fisik (checkout tetap menolak sebagai backstop).
$cartGroups = [];
foreach ($rows as $r) { $cartGroups[(int)$r['p']['seller_id']]['types'][] = $r['p']['tipe']; $cartGroups[(int)$r['p']['seller_id']]['seller'] = $r['p']['seller_nama']; }
foreach ($cartGroups as $cg) {
    if (count(array_unique($cg['types'])) > 1) {
        echo '<p class="neo-card p-4 mb-4 text-sm font-bold bg-[#FDE68A]">Penjual ' . e($cg['seller']) . ' memiliki produk digital DAN fisik di keranjang Anda. Checkout menolak campuran ini — pisahkan menjadi dua checkout (atau hapus salah satu jenis) sebelum checkout.</p>';
    }
}
?>
<?php if (!$rows): ?><p class="neo-card p-4 font-bold">Keranjang kosong. <a class="underline decoration-[3px]" href="<?= url('catalog') ?>">Lihat katalog</a>.</p>
<?php else: ?>
<form method="post" action="index.php">
<?= csrf_field() ?><input type="hidden" name="action" value="cart_update">
<div class="neo-table-wrap overflow-x-auto">
<table class="neo-table">
<tr class="text-left"><th class="p-3">Produk</th><th class="p-3">Penjual</th><th class="p-3">Harga</th><th class="p-3">Qty</th><th class="p-3">Subtotal</th><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr><td class="p-3 font-bold"><?= e($r['p']['judul']) ?> (<?= e($r['p']['tipe']) ?>)<?php if ($r['p']['status'] !== 'active'): ?> <span class="font-bold">— tidak tersedia lagi (produk diarsipkan/dinonaktifkan, hapus dari keranjang)</span><?php endif; ?><?php if (($r['p']['seller_status'] ?? 'active') !== 'active'): ?> <span class="font-bold">— penjual ditangguhkan</span><?php endif; ?></td><td class="p-3"><?= e($r['p']['seller_nama']) ?></td><td class="p-3"><?= rp($r['p']['harga']) ?></td>
<td class="p-3"><?php if ($r['p']['tipe'] === 'digital'): ?>1<input type="hidden" name="qty[<?= (int)$r['p']['id'] ?>]" value="1"><?php else: ?><input type="number" name="qty[<?= (int)$r['p']['id'] ?>]" value="<?= $r['qty'] ?>" min="0" class="neo-input px-2 py-1 w-16"><?php endif; ?></td>
<td class="p-3 font-bold"><?= rp((float)$r['p']['harga'] * $r['qty']) ?></td>
<td class="p-3"><button form="rm<?= (int)$r['p']['id'] ?>" class="font-bold underline decoration-[3px]">Hapus</button></td></tr>
<?php endforeach; ?>
</table>
</div>
<div class="mt-4 flex flex-wrap gap-3 items-center"><button class="neo-btn neo-btn-white px-4 py-2">Perbarui qty</button><span class="font-black text-lg">Total: <?= rp($total) ?></span><a href="<?= url('checkout') ?>" class="neo-btn neo-btn-pink px-5 py-2">Checkout</a></div>
</form>
<?php foreach ($rows as $r): ?><form id="rm<?= (int)$r['p']['id'] ?>" method="post" action="index.php"><?= csrf_field() ?><input type="hidden" name="action" value="cart_remove"><input type="hidden" name="product_id" value="<?= (int)$r['p']['id'] ?>"></form><?php endforeach; ?>
<?php endif; ?>
