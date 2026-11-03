<?php
require_admin();
$fields = ['platform_fee_percent' => 'Fee platform (%)', 'cod_fee_flat' => 'Fee COD flat (Rp)', 'min_withdrawal' => 'Minimal penarikan (Rp)', 'auto_release_days' => 'Auto-release (hari)', 'download_expiry_hours' => 'Masa berlaku token unduhan (jam)', 'download_max_uses' => 'Maks pemakaian token unduhan', 'site_title' => 'Judul situs', 'max_image_mb' => 'Maks ukuran gambar (MB)', 'max_digital_mb' => 'Maks ukuran file digital (MB)'];
?>
<p class="neo-eyebrow mb-1">Panel pengelola</p>
<h1 class="text-3xl font-black mb-4">Pengaturan &amp; Fee</h1>
<p class="text-sm font-semibold mb-4">Semua nilai fee dibaca dari tabel settings — tidak ada yang di-hardcode di kode transaksi.</p>
<form method="post" action="index.php" class="neo-card p-5 max-w-xl space-y-3">
<?= csrf_field() ?><input type="hidden" name="action" value="admin_settings_save">
<?php foreach ($fields as $k => $label): ?>
<label class="block text-sm font-bold"><?= $label ?><input name="<?= $k ?>" value="<?= e(setting($k)) ?>" class="neo-input px-3 py-2 w-full mt-1"></label>
<?php endforeach; ?>
<button class="neo-btn neo-btn-pink px-5 py-2">Simpan</button>
</form>
