<?php
$u = require_login();
$db = pdo();
$st = $db->prepare('SELECT * FROM topups WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
$st->execute([$u['id']]); $rows = $st->fetchAll();
$neoBadge = static function ($s) { $m = ['paid' => '#DFE6C6', 'completed' => '#DFE6C6', 'active' => '#DFE6C6', 'approved' => '#DFE6C6', 'pending' => '#FDE68A', 'awaiting_payment' => '#FDE68A', 'awaiting_fulfillment' => '#FDE68A', 'investigating' => '#FDE68A', 'open' => '#FDE68A', 'failed' => '#F9C5D8', 'expired' => '#F9C5D8', 'rejected' => '#F9C5D8', 'suspended' => '#F9C5D8', 'archived' => '#F9C5D8', 'held' => '#A9C4D4', 'disputed' => '#C9D3EA', 'cash_received' => '#FBD9B4']; return $m[$s] ?? '#FFFFFF'; };
?>
<p class="neo-eyebrow mb-1">Isi saldo</p>
<h1 class="text-3xl font-black mb-4">Top-up saldo (QRIS via DOKU)</h1>
<p class="text-sm font-semibold mb-4">Nominal penuh masuk ke dompet — platform menanggung biaya MDR.</p>
<form method="post" action="index.php" class="neo-card p-4 max-w-md flex flex-wrap gap-2 mb-6">
<?= csrf_field() ?><input type="hidden" name="action" value="topup_request">
<?= form_token_field('topup') ?>
<input name="amount" type="number" step="1" min="1" required placeholder="Nominal (Rp)" class="neo-input px-3 py-2 flex-1 min-w-[160px]">
<button class="neo-btn neo-btn-pink px-5 py-2">Buat QR</button>
</form>
<h2 class="font-black text-lg mb-2">Riwayat top-up</h2>
<div class="space-y-3">
<?php foreach ($rows as $r): ?>
<div class="neo-card p-4 text-sm">
  <p><strong><?= rp($r['amount']) ?></strong> — status: <span class="neo-pill" style="background:<?= $neoBadge($r['status']) ?>"><?= e($r['status']) ?></span> — <?= e($r['created_at']) ?><?= $r['doku_invoice'] ? ' — ' . e($r['doku_invoice']) : '' ?></p>
  <?php if ($r['status'] === 'pending' && !empty($r['qr_payload'])): ?>
    <?php if (str_starts_with($r['qr_payload'], 'http')): ?>
      <p class="mt-2 font-semibold">Selesaikan pembayaran di halaman DOKU (QRIS ditampilkan di sana):</p>
      <p class="mt-2"><a href="<?= e($r['qr_payload']) ?>" target="_blank" rel="noopener" class="neo-btn neo-btn-pink px-5 py-2">Buka halaman pembayaran</a></p>
      <p class="bg-gray-100 border-2 border-black rounded-xl p-2 break-all font-mono text-xs mt-2"><?= e($r['qr_payload']) ?></p>
    <?php else: ?>
      <p class="mt-2 font-semibold">Pindai QR ini dengan aplikasi pembayaran Anda:</p>
      <p class="bg-gray-100 border-2 border-black rounded-xl p-2 break-all font-mono text-xs mt-1"><?= e($r['qr_payload']) ?></p>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
