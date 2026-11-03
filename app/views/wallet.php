<?php
$u = require_login();
$db = pdo();
$st = $db->prepare('SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 100');
$st->execute([$u['id']]); $rows = $st->fetchAll();
?>
<p class="neo-eyebrow mb-1">Uangmu</p>
<h1 class="text-3xl font-black mb-4">Dompet Saya</h1>
<div class="bg-black text-white border-[3px] border-black rounded-[20px] shadow-[4px_4px_0_#EC4899] p-5 mb-4 flex flex-wrap gap-3 items-center justify-between">
<p class="text-xl">Saldo: <strong class="font-black"><?= rp($u['wallet_balance']) ?></strong></p>
<a class="neo-btn neo-btn-pink px-5 py-2" href="<?= url('topup') ?>">Top-up</a>
</div>
<h2 class="font-black text-lg mb-2">Riwayat (ledger, tidak bisa diubah)</h2>
<div class="neo-table-wrap overflow-x-auto">
<table class="neo-table">
<tr class="text-left"><th class="p-2">Waktu</th><th class="p-2">Jenis</th><th class="p-2">Jumlah</th><th class="p-2">Saldo sesudah</th><th class="p-2">Catatan</th></tr>
<?php foreach ($rows as $r): ?><tr><td class="p-2"><?= e($r['created_at']) ?></td><td class="p-2"><span class="neo-pill bg-white"><?= e($r['type']) ?></span></td><td class="p-2 font-bold"><?= ($r['amount'] >= 0 ? '+' : '') . rp($r['amount']) ?></td><td class="p-2"><?= rp($r['balance_after']) ?></td><td class="p-2"><?= e($r['note']) ?> (<?= e($r['reference_type']) ?> #<?= e($r['reference_id']) ?>)</td></tr><?php endforeach; ?>
</table>
</div>
