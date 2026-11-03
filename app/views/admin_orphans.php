<?php
// FIX-ALL B14: daftar pembayaran yatim — SUCCESS DOKU yang tiba SETELAH
// order dibatalkan. Uang ada di DOKU dan TIDAK dikredit ke siapa pun di
// sistem. Tindak lanjut: refund manual lewat dashboard DOKU kepada pembeli
// (cocokkan invoice / transaction_id di bawah), lalu catat di luar sistem.
require_admin();
$db = pdo();
$rows = $db->query('SELECT * FROM orphaned_payments ORDER BY received_at DESC LIMIT 200')->fetchAll();
?>
<h1 class="text-2xl font-bold mb-2">Pembayaran yatim (DOKU)</h1>
<p class="text-sm text-gray-600 mb-4">Kasus ini terjadi bila pembeli membayar QRIS tetapi webhook SUCCESS tiba setelah order dibatalkan (atau link dibayar setelah pembatalan). JANGAN mengkredit manual dari halaman ini — refund lewat dashboard DOKU.</p>
<?php if (!$rows): ?><p class="text-gray-500">Belum ada pembayaran yatim.</p><?php endif; ?>
<table class="w-full bg-white rounded shadow text-sm">
<tr class="text-left border-b"><th class="p-2">Diterima</th><th class="p-2">Invoice</th><th class="p-2">Transaction ID</th><th class="p-2">Nominal (order)</th><th class="p-2">Status DOKU</th><th class="p-2">Catatan</th></tr>
<?php foreach ($rows as $r): ?><tr class="border-b"><td class="p-2"><?= e($r['received_at']) ?></td><td class="p-2"><?= e($r['invoice']) ?></td><td class="p-2"><?= e($r['transaction_id']) ?></td><td class="p-2"><?= $r['amount'] !== null ? rp($r['amount']) : '—' ?></td><td class="p-2"><?= e($r['raw_status']) ?></td><td class="p-2"><?= e($r['note']) ?></td></tr><?php endforeach; ?>
</table>
