<?php
$u = require_login();
$db = pdo();
$st = $db->prepare('SELECT * FROM withdrawals WHERE seller_id = ? ORDER BY created_at DESC LIMIT 50');
$st->execute([$u['id']]); $rows = $st->fetchAll();
$providers = withdrawal_providers();
$neoBadge = static function ($s) { $m = ['paid' => '#DFE6C6', 'completed' => '#DFE6C6', 'active' => '#DFE6C6', 'approved' => '#DFE6C6', 'pending' => '#FDE68A', 'awaiting_payment' => '#FDE68A', 'awaiting_fulfillment' => '#FDE68A', 'investigating' => '#FDE68A', 'open' => '#FDE68A', 'failed' => '#F9C5D8', 'expired' => '#F9C5D8', 'rejected' => '#F9C5D8', 'suspended' => '#F9C5D8', 'archived' => '#F9C5D8', 'held' => '#A9C4D4', 'disputed' => '#C9D3EA', 'cash_received' => '#FBD9B4']; return $m[$s] ?? '#FFFFFF'; };
?>
<p class="neo-eyebrow mb-1">Cairkan saldo</p>
<h1 class="text-3xl font-black mb-2">Penarikan Saldo</h1>
<p class="text-sm font-semibold mb-4">Saldo: <?= rp($u['wallet_balance']) ?> • Minimal <?= rp(min_withdrawal()) ?> • Dana didebit saat request; bila ditolak admin, dana kembali.</p>
<form method="post" action="index.php" class="neo-card p-5 max-w-md space-y-3 mb-6">
<?= csrf_field() ?><input type="hidden" name="action" value="withdrawal_request">
<label class="block text-sm font-bold">Nominal (Rp)<input name="amount" type="number" step="1" min="1" required class="neo-input px-3 py-2 w-full mt-1"></label>
<label class="block text-sm font-bold">Metode<select name="method" id="wd-method" class="neo-input px-3 py-2 w-full mt-1"><option value="ewallet">E-Wallet</option><option value="digital">Bank Digital</option><option value="bank">Bank</option></select></label>
<label class="block text-sm font-bold">Penyedia<select name="provider" id="wd-provider" required class="neo-input px-3 py-2 w-full mt-1">
<optgroup label="E-Wallet" data-method="ewallet"><?php foreach ($providers['ewallet'] as $p): ?><option value="<?= e($p) ?>"><?= e($p) ?></option><?php endforeach; ?></optgroup>
<optgroup label="Bank Digital" data-method="digital"><?php foreach ($providers['digital'] as $p): ?><option value="<?= e($p) ?>"><?= e($p) ?></option><?php endforeach; ?></optgroup>
<optgroup label="Bank" data-method="bank"><?php foreach ($providers['bank'] as $p): ?><option value="<?= e($p) ?>"><?= e($p) ?></option><?php endforeach; ?></optgroup>
<option value="other">Lainnya...</option>
</select></label>
<label class="block text-sm font-bold" id="wd-other-wrap" style="display:none">Nama penyedia lainnya<input name="provider_other" id="wd-other" class="neo-input px-3 py-2 w-full mt-1" placeholder="Tulis nama e-wallet / bank"></label>
<label class="block text-sm font-bold">Nama pemilik rekening / akun<input name="account_name" required class="neo-input px-3 py-2 w-full mt-1" placeholder="Nama sesuai rekening / akun"></label>
<label class="block text-sm font-bold">Tujuan (nomor e-wallet / rekening)<input name="destination" required class="neo-input px-3 py-2 w-full mt-1"></label>
<button class="neo-btn neo-btn-pink px-5 py-2">Ajukan penarikan</button>
</form>
<script>
(function () {
  var method = document.getElementById('wd-method');
  var provider = document.getElementById('wd-provider');
  var otherWrap = document.getElementById('wd-other-wrap');
  var otherInput = document.getElementById('wd-other');
  function filterProviders() {
    var m = method.value;
    var firstEnabled = null;
    for (var i = 0; i < provider.options.length; i++) {
      var opt = provider.options[i];
      var group = opt.parentNode;
      var gm = group && group.getAttribute ? group.getAttribute('data-method') : null;
      opt.disabled = !!(gm && gm !== m);
      if (!opt.disabled && !firstEnabled) firstEnabled = opt;
    }
    if (provider.selectedOptions.length && provider.selectedOptions[0].disabled) provider.value = firstEnabled ? firstEnabled.value : 'other';
    toggleOther();
  }
  function toggleOther() {
    var show = provider.value === 'other';
    otherWrap.style.display = show ? 'block' : 'none';
    otherInput.required = show;
    if (!show) otherInput.value = '';
  }
  method.addEventListener('change', filterProviders);
  provider.addEventListener('change', toggleOther);
  filterProviders();
})();
</script>
<h2 class="font-black text-lg mb-2">Riwayat penarikan</h2>
<div class="space-y-2">
<?php foreach ($rows as $r): ?>
<div class="neo-card p-3 text-sm"><p><strong><?= rp($r['amount']) ?></strong> via <?= e($r['provider'] ?: (withdrawal_method_label($r['method']) . ' (pengajuan lama)')) ?> ke <?= e($r['destination']) ?> a.n. <?= e($r['account_name'] ?: '-') ?> — <span class="neo-pill" style="background:<?= $neoBadge($r['status']) ?>"><?= e($r['status']) ?></span> — <?= e($r['created_at']) ?><?= $r['admin_note'] ? ' — catatan: ' . e($r['admin_note']) : '' ?><?= $r['transfer_ref'] ? ' — ref: ' . e($r['transfer_ref']) : '' ?></p></div>
<?php endforeach; ?>
</div>
