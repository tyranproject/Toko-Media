<?php
$u = require_login();
$db = pdo();
$id = (int)($_GET['id'] ?? 0);
$p = null;
if ($id > 0) {
    $st = $db->prepare('SELECT * FROM products WHERE id = ?'); $st->execute([$id]); $p = $st->fetch();
    if (!$p || ((int)$p['seller_id'] !== (int)$u['id'] && $u['role'] !== 'admin')) { echo '<p>Produk tidak ditemukan.</p>'; return; }
}
$cats = $db->query('SELECT * FROM categories ORDER BY nama')->fetchAll();
?>
<p class="neo-eyebrow mb-1">Etalase</p>
<h1 class="text-3xl font-black mb-4"><?= $p ? 'Edit produk' : 'Tambah produk' ?></h1>
<?php if ($p && $p['status'] === 'active'): ?><p class="neo-flash-error p-3 mb-4 text-sm">Perhatian: mengedit produk aktif akan mengembalikannya ke status pending untuk moderasi ulang.</p><?php endif; ?>
<form method="post" action="index.php" enctype="multipart/form-data" class="neo-card p-5 max-w-2xl space-y-3">
<?= csrf_field() ?><input type="hidden" name="action" value="product_save"><input type="hidden" name="id" value="<?= $id ?>">
<label class="block text-sm font-bold">Tipe
<select name="tipe" id="tipe" class="neo-input px-3 py-2 w-full mt-1">
<option value="digital" <?= $p && $p['tipe'] === 'digital' ? 'selected' : '' ?>>Digital</option>
<option value="fisik" <?= $p && $p['tipe'] === 'fisik' ? 'selected' : '' ?>>Fisik</option>
</select></label>
<label class="block text-sm font-bold">Judul<input name="judul" required value="<?= e($p['judul'] ?? '') ?>" class="neo-input px-3 py-2 w-full mt-1"></label>
<label class="block text-sm font-bold">Deskripsi<textarea name="deskripsi" required class="neo-input px-3 py-2 w-full mt-1"><?= e($p['deskripsi'] ?? '') ?></textarea></label>
<label class="block text-sm font-bold">Spesifikasi<textarea name="spesifikasi" class="neo-input px-3 py-2 w-full mt-1"><?= e($p['spesifikasi'] ?? '') ?></textarea></label>
<label class="block text-sm font-bold">Kelebihan<textarea name="kelebihan" class="neo-input px-3 py-2 w-full mt-1"><?= e($p['kelebihan'] ?? '') ?></textarea></label>
<label class="block text-sm font-bold">Kekurangan<textarea name="kekurangan" class="neo-input px-3 py-2 w-full mt-1"><?= e($p['kekurangan'] ?? '') ?></textarea></label>
<label class="block text-sm font-bold">Alasan dijual (opsional)<textarea name="alasan_jual" class="neo-input px-3 py-2 w-full mt-1"><?= e($p['alasan_jual'] ?? '') ?></textarea></label>
<label class="block text-sm font-bold">Harga (Rp)<input name="harga" type="number" step="1" min="1" required value="<?= e($p['harga'] ?? '') ?>" class="neo-input px-3 py-2 w-full mt-1"></label>
<label class="block text-sm font-bold" id="stokWrap">Stok (fisik saja; kosong = tidak valid untuk fisik)<input name="stok" type="number" min="0" value="<?= $p && $p['stok'] !== null ? (int)$p['stok'] : '' ?>" class="neo-input px-3 py-2 w-full mt-1"></label>
<label class="block text-sm font-bold">Kategori<select name="category_id" class="neo-input px-3 py-2 w-full mt-1"><option value="">— Tanpa kategori —</option>
<?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $p && (int)$p['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nama']) ?></option><?php endforeach; ?></select></label>
<label class="block text-sm font-bold"><input type="checkbox" name="cod_available" <?= $p && (int)$p['cod_available'] ? 'checked' : '' ?>> COD tersedia (fisik saja)</label>
<label class="block text-sm font-bold"><input type="checkbox" name="pickup_available" <?= !$p || (int)$p['pickup_available'] ? 'checked' : '' ?>> Pickup tersedia</label>
<label class="block text-sm font-bold">Link demo (opsional, diawali http:// atau https://)<input name="demo_url" value="<?= e($p['demo_url'] ?? '') ?>" placeholder="https://..." class="neo-input px-3 py-2 w-full mt-1"></label>
<label class="block text-sm font-bold">Foto produk (jpg/png/webp, maks <?= max_image_mb() ?> MB per file, maks 5)<input type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple class="block mt-1"></label>
<label class="block text-sm font-bold" id="digitalWrap">File digital (di-upload ke folder privat Drive, maks <?= max_digital_mb() ?> MB)<?php if ($p && $p['drive_file_name']): ?> — saat ini: <?= e($p['drive_file_name']) ?><?php endif; ?><input type="file" name="digital_file" class="block mt-1"></label>
<button class="neo-btn neo-btn-pink px-5 py-2">Simpan (masuk moderasi)</button>
</form>
<script>
(function () {
  var tipe = document.getElementById('tipe');
  var stok = document.getElementById('stokWrap');
  var dig = document.getElementById('digitalWrap');
  function sync() {
    var isDig = tipe.value === 'digital';
    stok.style.display = isDig ? 'none' : 'block';
    dig.style.display = isDig ? 'block' : 'none';
  }
  tipe.addEventListener('change', sync);
  sync();
})();
</script>
