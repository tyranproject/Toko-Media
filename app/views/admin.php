<?php
require_admin();
$db = pdo();
$stats = [];
$stats['users'] = (int)$db->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
$stats['products_active'] = (int)$db->query("SELECT COUNT(*) c FROM products WHERE status = 'active'")->fetch()['c'];
$stats['products_pending'] = (int)$db->query("SELECT COUNT(*) c FROM products WHERE status = 'pending'")->fetch()['c'];
$stats['orders'] = (int)$db->query('SELECT COUNT(*) c FROM orders')->fetch()['c'];
$stats['orders_completed'] = (int)$db->query("SELECT COUNT(*) c FROM orders WHERE status = 'completed'")->fetch()['c'];
$stats['escrow_held'] = (float)($db->query("SELECT COALESCE(SUM(amount_held),0) s FROM escrow_holds WHERE status = 'held'")->fetch()['s']);
$stats['disputes_open'] = (int)$db->query("SELECT COUNT(*) c FROM disputes WHERE status IN ('open','investigating')")->fetch()['c'];
$stats['withdrawals_pending'] = (int)$db->query("SELECT COUNT(*) c FROM withdrawals WHERE status = 'pending'")->fetch()['c'];
$stats['topups_pending'] = (int)$db->query("SELECT COUNT(*) c FROM topups WHERE status = 'pending'")->fetch()['c'];
$stats['reports_pending'] = (int)$db->query("SELECT COUNT(*) c FROM reports WHERE status = 'pending'")->fetch()['c'];
?>
<p class="neo-eyebrow mb-1">Panel pengelola</p>
<h1 class="text-3xl font-black mb-4">Dashboard Admin</h1>
<nav class="flex flex-wrap gap-2 text-sm font-bold mb-6">
<a class="neo-btn neo-btn-white px-4 py-1" href="<?= url('admin_settings') ?>">Pengaturan &amp; fee</a>
<a class="neo-btn neo-btn-white px-4 py-1" href="<?= url('admin_users') ?>">Pengguna</a>
<a class="neo-btn neo-btn-white px-4 py-1" href="<?= url('admin_moderation') ?>">Moderasi produk</a>
<a class="neo-btn neo-btn-white px-4 py-1" href="<?= url('admin_orders') ?>">Order &amp; transaksi</a>
<a class="neo-btn neo-btn-white px-4 py-1" href="<?= url('admin_topups') ?>">Top-up</a>
<a class="neo-btn neo-btn-white px-4 py-1" href="<?= url('admin_withdrawals') ?>">Penarikan</a>
<a class="neo-btn neo-btn-white px-4 py-1" href="<?= url('admin_disputes') ?>">Sengketa</a>
<a class="neo-btn neo-btn-white px-4 py-1" href="<?= url('admin_categories') ?>">Kategori</a>
<a class="neo-btn neo-btn-white px-4 py-1" href="<?= url('admin_reports') ?>">Laporan pengguna</a>
<a class="neo-btn neo-btn-white px-4 py-1" href="<?= url('admin_scan') ?>">Pindai barang terlarang</a>
</nav>
<div class="neo-stats grid sm:grid-cols-3 gap-5">
<?php foreach (['users' => 'Total user', 'products_active' => 'Produk aktif', 'products_pending' => 'Produk menunggu moderasi', 'orders' => 'Total order', 'orders_completed' => 'Order selesai', 'escrow_held' => 'Escrow ditahan (Rp)', 'disputes_open' => 'Sengketa terbuka', 'withdrawals_pending' => 'Penarikan pending', 'topups_pending' => 'Top-up pending', 'reports_pending' => 'Laporan menunggu'] as $k => $label): ?>
<div class="neo-card p-4"><p class="text-sm font-bold"><?= $label ?></p><p class="text-2xl font-black"><?= $k === 'escrow_held' ? rp($stats[$k]) : (int)$stats[$k] ?></p></div>
<?php endforeach; ?>
</div>
