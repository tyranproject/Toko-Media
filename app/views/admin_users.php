<?php
require_admin();
$db = pdo();
$rows = $db->query('SELECT * FROM users ORDER BY created_at DESC LIMIT 200')->fetchAll();
$neoBadge = static function ($s) { $m = ['paid' => '#DFE6C6', 'completed' => '#DFE6C6', 'active' => '#DFE6C6', 'approved' => '#DFE6C6', 'pending' => '#FDE68A', 'awaiting_payment' => '#FDE68A', 'awaiting_fulfillment' => '#FDE68A', 'investigating' => '#FDE68A', 'open' => '#FDE68A', 'failed' => '#F9C5D8', 'expired' => '#F9C5D8', 'rejected' => '#F9C5D8', 'suspended' => '#F9C5D8', 'archived' => '#F9C5D8', 'held' => '#A9C4D4', 'disputed' => '#C9D3EA', 'cash_received' => '#FBD9B4']; return $m[$s] ?? '#FFFFFF'; };
?>
<p class="neo-eyebrow mb-1">Panel pengelola</p>
<h1 class="text-3xl font-black mb-4">Pengguna</h1>
<div class="neo-table-wrap overflow-x-auto">
<table class="neo-table">
<tr class="text-left"><th class="p-2">Nama</th><th class="p-2">Email</th><th class="p-2">Role</th><th class="p-2">Status</th><th class="p-2">Saldo</th><th class="p-2">Aksi</th></tr>
<?php foreach ($rows as $r): ?>
<tr><td class="p-2 font-bold"><?= e($r['nama']) ?></td><td class="p-2"><?= e($r['email']) ?></td><td class="p-2"><span class="neo-pill <?= $r['role'] === 'admin' ? 'bg-[#F9C5D8]' : 'bg-white' ?>"><?= e($r['role']) ?></span></td><td class="p-2"><span class="neo-pill" style="background:<?= $neoBadge($r['status']) ?>"><?= e($r['status']) ?></span></td><td class="p-2"><?= rp($r['wallet_balance']) ?></td>
<td class="p-2"><form method="post" action="index.php"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $r['status'] === 'suspended' ? 'admin_user_unsuspend' : 'admin_user_suspend' ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="neo-btn neo-btn-pastel-pink px-3 py-1 text-xs"><?= $r['status'] === 'suspended' ? 'Aktifkan' : 'Tangguhkan' ?></button></form></td></tr>
<?php endforeach; ?>
</table>
</div>
