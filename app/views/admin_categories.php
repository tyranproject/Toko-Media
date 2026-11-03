<?php
require_admin();
$db = pdo();
$rows = $db->query('SELECT * FROM categories ORDER BY nama')->fetchAll();
?>
<p class="neo-eyebrow mb-1">Panel pengelola</p>
<h1 class="text-3xl font-black mb-4">Kategori</h1>
<form method="post" action="index.php" class="neo-card p-5 max-w-md space-y-3 mb-6">
<?= csrf_field() ?><input type="hidden" name="action" value="category_save">
<label class="block text-sm font-bold">Nama<input name="nama" required class="neo-input px-3 py-2 w-full mt-1"></label>
<label class="block text-sm font-bold">Deskripsi<textarea name="deskripsi" class="neo-input px-3 py-2 w-full mt-1"></textarea></label>
<button class="neo-btn neo-btn-pink px-5 py-2">Tambah kategori</button>
</form>
<ul class="space-y-2">
<?php foreach ($rows as $c): ?><li class="neo-card p-3 text-sm"><strong><?= e($c['nama']) ?></strong> — slug <?= e($c['slug']) ?><?= $c['deskripsi'] ? ' — ' . e($c['deskripsi']) : '' ?></li><?php endforeach; ?>
</ul>
