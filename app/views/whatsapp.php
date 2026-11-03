<?php
$u = require_login();
$db = pdo();
$cur = $db->prepare('SELECT whatsapp FROM users WHERE id = ?');
$cur->execute([(int)$u['id']]); $curWa = $cur->fetchColumn() ?: null;
?>
<p class="neo-eyebrow mb-1">Profil</p>
<h1 class="text-3xl font-black mb-2">Nomor WhatsApp</h1>
<p class="text-sm font-semibold mb-4">Nomor ini dipakai tombol "Hubungi penjual via WhatsApp" di halaman produk Anda. Halaman ini hanya mengubah nomor WhatsApp — tidak ada field profil lain yang diubah di sini.</p>
<?php if ($curWa): ?>
<p class="neo-card p-4 text-sm font-bold">Nomor tersimpan saat ini: <?= e($curWa) ?> (tombol WA tampil di produk Anda).</p>
<?php else: ?>
<p class="neo-card p-4 text-sm font-bold">Belum ada nomor tersimpan — tombol WA tidak tampil di produk Anda.</p>
<?php endif; ?>
<form method="post" action="index.php" class="neo-card p-5 mt-4 space-y-3">
  <?= csrf_field() ?><input type="hidden" name="action" value="profile_whatsapp_save">
  <label class="block text-sm font-bold">Nomor WhatsApp
    <input name="whatsapp" value="<?= e($curWa ?? '') ?>" placeholder="cth: 0812... atau 62812..." class="neo-input px-3 py-2 w-full mt-1">
  </label>
  <p class="text-xs font-semibold">Hanya digit yang disimpan; awalan 08... otomatis diubah menjadi 62... Kosongkan lalu simpan untuk menghapus nomor (tombol WA hilang dari produk Anda).</p>
  <p class="text-xs font-semibold">WhatsApp hanya untuk tanya-jawab dengan pembeli. Transaksi tetap lewat aplikasi supaya escrow dan komplain tetap berlaku — jangan menyelesaikan pembayaran di luar aplikasi.</p>
  <button class="neo-btn neo-btn-pink px-5 py-2">Simpan nomor WhatsApp</button>
</form>
