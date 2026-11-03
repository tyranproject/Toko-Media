<?php
require_admin();
$db = pdo();
$rows = $db->query('SELECT t.*, u.nama, u.email FROM topups t JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC LIMIT 200')->fetchAll();
$neoBadge = static function ($s) { $m = ['paid' => '#DFE6C6', 'completed' => '#DFE6C6', 'active' => '#DFE6C6', 'approved' => '#DFE6C6', 'pending' => '#FDE68A', 'awaiting_payment' => '#FDE68A', 'awaiting_fulfillment' => '#FDE68A', 'investigating' => '#FDE68A', 'open' => '#FDE68A', 'failed' => '#F9C5D8', 'expired' => '#F9C5D8', 'rejected' => '#F9C5D8', 'suspended' => '#F9C5D8', 'archived' => '#F9C5D8', 'held' => '#A9C4D4', 'disputed' => '#C9D3EA', 'cash_received' => '#FBD9B4']; return $m[$s] ?? '#FFFFFF'; };
?>
<p class="neo-eyebrow mb-1">Panel pengelola</p>
<h1 class="text-3xl font-black mb-4">Top-up</h1>
<div class="neo-table-wrap overflow-x-auto">
<table class="neo-table">
<tr class="text-left"><th class="p-2">User</th><th class="p-2">Nominal</th><th class="p-2">Status</th><th class="p-2">Invoice DOKU</th><th class="p-2">Waktu</th></tr>
<?php foreach ($rows as $r): ?>
<tr><td class="p-2"><?= e($r['nama']) ?> (<?= e($r['email']) ?>)</td><td class="p-2 font-bold"><?= rp($r['amount']) ?></td><td class="p-2"><span class="neo-pill" style="background:<?= $neoBadge($r['status']) ?>"><?= e($r['status']) ?></span></td><td class="p-2"><?= e($r['doku_invoice']) ?></td><td class="p-2"><?= e($r['created_at']) ?></td></tr>
<?php endforeach; ?>
</table>
</div>
