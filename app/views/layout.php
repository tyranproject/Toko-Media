<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — <?= e(setting('site_title')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@800;900&family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
  /* Tema neubrutalis (ala budgetinikah.com): border hitam tebal + hard shadow, tanpa blur. */
  body { font-family: 'Inter', system-ui, sans-serif; }
  h1, h2, h3, .font-display { font-family: 'Archivo', 'Inter', sans-serif; font-weight: 800; letter-spacing: -0.02em; }
  .neo-card { background: #fff; border: 3px solid #000; border-radius: 20px; box-shadow: 4px 4px 0 #000; }
  .neo-btn { display: inline-flex; align-items: center; justify-content: center; border: 3px solid #000; border-radius: 9999px; box-shadow: 4px 4px 0 #000; font-weight: 800; transition: transform .1s ease, box-shadow .1s ease; cursor: pointer; }
  .neo-btn:hover { transform: translate(2px, 2px); box-shadow: 2px 2px 0 #000; }
  .neo-btn:active { transform: translate(4px, 4px); box-shadow: 0 0 0 #000; }
  .neo-btn-pink { background: #EC4899; color: #000; }
  .neo-btn-white { background: #fff; color: #000; }
  .neo-btn-pastel-pink { background: #F9C5D8; color: #000; }
  .neo-btn-yellow { background: #FDE68A; color: #000; }
  .neo-input { background: #fff; border: 3px solid #000; border-radius: 12px; }
  .neo-input:focus { outline: none; box-shadow: 3px 3px 0 #EC4899; }
  .neo-pill { display: inline-block; border: 2px solid #000; border-radius: 9999px; padding: 2px 10px; font-weight: 700; font-size: 12px; line-height: 1.5; }
  .neo-eyebrow { text-transform: uppercase; letter-spacing: .15em; font-size: 12px; font-weight: 800; }
  .neo-table-wrap { border: 3px solid #000; border-radius: 20px; overflow: hidden; box-shadow: 4px 4px 0 #000; background: #fff; }
  .neo-table { width: 100%; font-size: .875rem; }
  .neo-table th { background: #000; color: #fff; font-weight: 800; text-align: left; }
  .neo-table td { border-bottom: 2px solid #f0f0f0; }
  .neo-table tr:last-child td { border-bottom: none; }
  .neo-flash-error { background: #F9C5D8; border: 3px solid #000; border-radius: 16px; box-shadow: 4px 4px 0 #000; font-weight: 700; }
  .neo-flash-ok { background: #DFE6C6; border: 3px solid #000; border-radius: 16px; box-shadow: 4px 4px 0 #000; font-weight: 700; }
  .neo-nav-active { background: #e5e5e5; border: 2px solid #000; border-radius: 9999px; }
  /* Kartu katalog: pastel berputar (murni CSS, tanpa mengubah PHP). */
  .neo-catalog > article:nth-child(6n+1) { background: #F9C5D8; }
  .neo-catalog > article:nth-child(6n+2) { background: #A9C4D4; }
  .neo-catalog > article:nth-child(6n+3) { background: #FBD9B4; }
  .neo-catalog > article:nth-child(6n+4) { background: #DFE6C6; }
  .neo-catalog > article:nth-child(6n+5) { background: #FDE68A; }
  .neo-catalog > article:nth-child(6n+6) { background: #C9D3EA; }
  /* Kartu statistik admin: pastel berputar. */
  .neo-stats > div:nth-child(6n+1) { background: #F9C5D8; }
  .neo-stats > div:nth-child(6n+2) { background: #A9C4D4; }
  .neo-stats > div:nth-child(6n+3) { background: #FBD9B4; }
  .neo-stats > div:nth-child(6n+4) { background: #DFE6C6; }
  .neo-stats > div:nth-child(6n+5) { background: #FDE68A; }
  .neo-stats > div:nth-child(6n+6) { background: #C9D3EA; }
  @media (max-width: 640px) {
    .neo-card { box-shadow: 3px 3px 0 #000; }
    .neo-btn { box-shadow: 3px 3px 0 #000; }
  }
</style>
</head>
<body class="bg-white text-black min-h-screen">
<?php $neoPage = (string)($_GET['page'] ?? 'catalog'); ?>
<?php $cartCount = !empty($_SESSION['cart']) && is_array($_SESSION['cart']) ? (int)array_sum($_SESSION['cart']) : 0; ?>
<!-- Strip utilitas ala marketplace profesional (Tokopedia/Shopee): ikut menggulir, tidak sticky -->
<div class="bg-black text-white text-xs font-bold">
  <div class="max-w-6xl mx-auto px-4 py-1.5 flex flex-wrap gap-x-4 gap-y-1 items-center justify-between">
    <span>Belanja aman &bull; Dana ditahan escrow &bull; Komplain dibantu admin</span>
    <span class="flex flex-wrap gap-x-4 gap-y-1">
      <a href="<?= url('track') ?>" class="hover:text-[#F9C5D8]">Lacak Order</a>
      <a href="<?= url('help') ?>" class="hover:text-[#F9C5D8]">Bantuan</a>
      <?php if ($user): ?><a href="<?= url('whatsapp') ?>" class="hover:text-[#F9C5D8]">WhatsApp</a><?php endif; ?>
    </span>
  </div>
</div>
<header class="sticky top-0 z-50 bg-white border-b-[3px] border-black">
  <div class="max-w-6xl mx-auto px-4 py-3 flex flex-wrap gap-3 items-center">
    <a href="<?= url('catalog') ?>" class="font-display text-2xl font-black tracking-tight shrink-0"><?= e(setting('site_title')) ?></a>
    <form method="get" action="index.php" class="flex flex-1 min-w-[220px] gap-2">
      <input type="hidden" name="page" value="catalog">
      <input name="q" value="<?= e(trim((string)($_GET['q'] ?? ''))) ?>" placeholder="Cari produk..." class="neo-input px-3 py-2 flex-1 min-w-0">
      <button class="neo-btn neo-btn-pink px-5 py-2">Cari</button>
    </form>
    <div class="flex flex-wrap gap-2 items-center">
      <?php if ($user): ?>
        <a href="<?= url('cart') ?>" class="neo-btn neo-btn-white px-4 py-2 text-sm">&#128722; Keranjang<?php if ($cartCount > 0): ?> <span class="inline-block min-w-[22px] text-center bg-[#EC4899] border-2 border-black rounded-full px-1 text-xs font-black"><?= $cartCount ?></span><?php endif; ?></a>
        <a href="<?= url('wallet') ?>" class="neo-btn neo-btn-yellow px-4 py-2 text-sm">Dompet: <?= rp($user['wallet_balance']) ?></a>
        <?php if (is_admin($user)): ?><a href="<?= url('admin') ?>" class="neo-btn neo-btn-pink px-4 py-2 text-sm">Admin</a><?php endif; ?>
        <form method="post" action="index.php" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="neo-btn neo-btn-white px-4 py-2 text-sm">Logout (<?= e($user['nama']) ?>)</button></form>
      <?php else: ?>
        <?php if ($neoPage === 'login' || $neoPage === 'catalog'): ?><a href="<?= url('catalog') ?>" class="neo-btn neo-btn-white px-4 py-2 text-sm">Katalog</a><?php endif; ?>
        <a href="<?= url('login') ?>" class="neo-btn neo-btn-white px-4 py-2 text-sm">Login</a>
        <a href="<?= url('register') ?>" class="neo-btn neo-btn-pink px-4 py-2 text-sm">Daftar</a>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($neoPage !== 'login' && !($neoPage === 'catalog' && !$user)): ?>
  <nav class="border-t-2 border-black">
    <div class="max-w-6xl mx-auto px-4 flex flex-wrap gap-1 text-sm font-bold">
      <a href="<?= url('catalog') ?>" class="px-3 py-2 <?= $neoPage === 'catalog' ? 'neo-nav-active' : '' ?>">Katalog</a>
      <?php if ($user): ?>
        <a href="<?= url('my_orders') ?>" class="px-3 py-2 <?= $neoPage === 'my_orders' ? 'neo-nav-active' : '' ?>">Order Saya</a>
        <a href="<?= url('my_products') ?>" class="px-3 py-2 <?= $neoPage === 'my_products' ? 'neo-nav-active' : '' ?>">Produk Saya</a>
        <a href="<?= url('my_purchases') ?>" class="px-3 py-2 <?= $neoPage === 'my_purchases' ? 'neo-nav-active' : '' ?>">Pembelian Digital</a>
        <a href="<?= url('withdrawals') ?>" class="px-3 py-2 <?= $neoPage === 'withdrawals' ? 'neo-nav-active' : '' ?>">Penarikan</a>
        <a href="<?= url('disputes') ?>" class="px-3 py-2 <?= $neoPage === 'disputes' ? 'neo-nav-active' : '' ?>">Komplain</a>
      <?php endif; ?>
    </div>
  </nav>
  <?php endif; ?>
</header>
<main class="max-w-6xl mx-auto px-4 py-6">
  <?php foreach ($flashes as $f): ?>
    <div class="mb-4 p-3 <?= $f['type'] === 'error' ? 'neo-flash-error' : 'neo-flash-ok' ?>"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
<footer class="border-t-[3px] border-black text-center text-xs font-bold py-6">Marketplace Bekasi Utara — Increment 1</footer>
<?php
// KITA-UX: modal keranjang setelah ORDER NOW (cart_add mengarahkan balik
// dengan ?cart_popup=1). Hanya tampil bila flag ada DAN keranjang tidak
// kosong. Halaman keranjang tetap bisa dibuka lewat navigasi; modal ini
// bukan pengganti halaman keranjang.
if (isset($_GET['cart_popup']) && $_GET['cart_popup'] === '1' && !empty($_SESSION['cart'])):
  $popDb = pdo(); $popRows = []; $popTotal = 0.0;
  foreach (cart() as $pid => $qty) {
    $pst = $popDb->prepare('SELECT p.*, u.nama AS seller_nama FROM products p JOIN users u ON u.id = p.seller_id WHERE p.id = ?');
    $pst->execute([$pid]); $pp = $pst->fetch();
    if (!$pp) { continue; }
    $pq = $pp['tipe'] === 'digital' ? 1 : (int)$qty;
    $popRows[] = ['p' => $pp, 'qty' => $pq];
    $popTotal = money($popTotal + (float)$pp['harga'] * $pq);
  }
?>
<?php if ($popRows): ?>
<div id="kita-cart-popup" class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="background:rgba(0,0,0,.5)">
  <div class="neo-card p-5 w-full max-w-md max-h-[85vh] overflow-y-auto">
    <div class="flex justify-between items-start gap-3">
      <h2 class="font-black text-xl">Keranjang belanja</h2>
      <button type="button" onclick="kitaClosePopup()" class="neo-btn neo-btn-white px-3 py-1 text-sm">✕</button>
    </div>
    <p class="text-sm font-semibold mt-1">Produk berhasil ditambahkan.</p>
    <ul class="mt-3 space-y-2 text-sm font-medium">
      <?php foreach ($popRows as $r): ?><li class="flex justify-between gap-2"><span><?= e($r['p']['judul']) ?> × <?= $r['qty'] ?></span><span class="font-bold"><?= rp((float)$r['p']['harga'] * $r['qty']) ?></span></li><?php endforeach; ?>
    </ul>
    <p class="font-black text-lg mt-3">Total: <?= rp($popTotal) ?></p>
    <div class="mt-4 flex flex-wrap gap-2">
      <button type="button" onclick="kitaClosePopup()" class="neo-btn neo-btn-white px-5 py-2">Lanjut Belanja</button>
      <a href="<?= url('checkout') ?>" class="neo-btn neo-btn-pink px-5 py-2">Checkout</a>
    </div>
  </div>
</div>
<script>
function kitaClosePopup() {
  var el = document.getElementById('kita-cart-popup');
  if (el) el.remove();
  var u = new URL(window.location.href);
  u.searchParams.delete('cart_popup');
  history.replaceState(null, '', u);
}
</script>
<?php endif; ?>
<?php endif; ?>
</body>
</html>
