<?php
require_admin();
$db = pdo();
// FASE 1 A2 (sisa): pindai listing AKTIF + PENDING terhadap daftar barang
// terlarang. Pemindaian ini HANYA MENAMPILKAN — tidak mengubah apa pun.
// Admin menolak per baris dengan tombol yang memakai aksi product_reject
// yang sudah ada (CSRF + maker-checker tetap berlaku: admin tidak bisa
// menolak produknya sendiri — minta admin lain).
$rows = $db->query("SELECT p.*, u.nama AS seller_nama, c.nama AS category_nama FROM products p JOIN users u ON u.id = p.seller_id LEFT JOIN categories c ON c.id = p.category_id WHERE p.status IN ('active','pending') ORDER BY p.created_at DESC")->fetchAll();
$matches = [];
foreach ($rows as $p) {
    $text = implode(' ', [(string)$p['judul'], (string)$p['deskripsi'], (string)$p['spesifikasi'], (string)$p['kelebihan'], (string)$p['kekurangan'], (string)$p['alasan_jual'], (string)$p['category_nama']]);
    $term = prohibited_product_match($text);
    if ($term !== null) { $matches[] = ['p' => $p, 'term' => $term]; }
}
?>
<p class="neo-eyebrow mb-1">Panel pengelola</p>
<h1 class="text-3xl font-black mb-4">Pindai Barang Terlarang</h1>
<p class="text-sm font-semibold mb-4">Memindai <?= count($rows) ?> produk aktif + pending. Ditemukan <strong><?= count($matches) ?> produk yang mengandung kata terlarang</strong>. Cocokkan kata itu dengan konteks produk sebelum menolak — pencocokan kata bisa false-positive (mis. istilah yang sama dalam konteks berbeda). Menolak bersifat manual per baris.</p>
<?php if (!$matches): ?><p class="neo-card p-4 font-bold">Tidak ada produk aktif/pending yang cocok dengan daftar barang terlarang.</p><?php endif; ?>
<div class="space-y-3">
<?php foreach ($matches as $m): $p = $m['p']; ?>
<div class="neo-card p-4 text-sm">
  <p class="font-bold"><?= e($p['judul']) ?> — <?= e($p['tipe']) ?> — <?= rp($p['harga']) ?> — penjual <?= e($p['seller_nama']) ?> — status <?= e($p['status']) ?></p>
  <p class="font-semibold mt-1">Kata terlarang yang cocok: <span class="neo-pill bg-[#F9C5D8]"><?= e($m['term']) ?></span> • Kategori: <?= e($p['category_nama'] ?: 'Tanpa kategori') ?></p>
  <p class="whitespace-pre-line font-medium mt-1"><?= e(mb_substr((string)$p['deskripsi'], 0, 300)) ?></p>
  <div class="flex flex-wrap gap-2 mt-3">
    <a class="neo-btn neo-btn-white px-4 py-1 text-sm" href="<?= url('product', ['id' => (int)$p['id']]) ?>">Lihat produk</a>
    <form method="post" action="index.php" class="flex flex-wrap gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="product_reject"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="rejection_reason" value="<?= e(prohibited_product_reason()) ?>"><button class="neo-btn neo-btn-pastel-pink px-4 py-1 text-sm">Tolak (barang terlarang)</button></form>
  </div>
</div>
<?php endforeach; ?>
</div>
