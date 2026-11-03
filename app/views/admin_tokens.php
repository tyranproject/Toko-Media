<?php
// FIX-ALL C12: daftar token unduhan aktif + revoke oleh admin.
// Dipakai saat file terbukti melanggar / penjual curang. Token yang
// dicabut tidak bisa dipakai lagi (download.php menolak status != active).
require_admin();
$db = pdo();
$rows = $db->query('SELECT t.*, p.judul AS produk, u.nama AS buyer_nama FROM download_tokens t JOIN products p ON p.id = t.product_id JOIN users u ON u.id = t.buyer_id WHERE t.status = \'active\' ORDER BY t.created_at DESC LIMIT 200')->fetchAll();
?>
<h1 class="text-2xl font-bold mb-2">Token unduhan aktif</h1>
<p class="text-sm text-gray-600 mb-4">Mencabut token menghentikan unduhan yang sudah beredar untuk token itu. Refund penuh digital otomatis mencabut token order tersebut (lihat sengketa).</p>
<?php if (!$rows): ?><p class="text-gray-500">Tidak ada token aktif.</p><?php endif; ?>
<div class="space-y-3">
<?php foreach ($rows as $r): ?>
<div class="bg-white rounded shadow p-4 text-sm">
  <p><strong><?= e($r['produk']) ?></strong> — pembeli <?= e($r['buyer_nama']) ?> — dipakai <?= (int)$r['uses'] ?>/<?= (int)$r['max_uses'] ?> — kedaluwarsa <?= e($r['expires_at']) ?></p>
  <form method="post" action="index.php" class="mt-2 inline"><?= csrf_field() ?><input type="hidden" name="action" value="token_revoke"><input type="hidden" name="token_id" value="<?= (int)$r['id'] ?>"><button class="bg-red-600 text-white px-3 py-1 rounded">Cabut token ini</button></form>
  <form method="post" action="index.php" class="mt-2 inline"><?= csrf_field() ?><input type="hidden" name="action" value="token_revoke"><input type="hidden" name="product_id" value="<?= (int)$r['product_id'] ?>"><button class="bg-red-600 text-white px-3 py-1 rounded">Cabut semua token produk ini</button></form>
</div>
<?php endforeach; ?>
</div>
