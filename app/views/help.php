<?php
// Halaman indeks bantuan — daftar semua halaman bantuan. Publik, tanpa login.
$items = [
    ['help_shop', 'Cara Belanja', 'Dari cari produk sampai konfirmasi terima: keranjang, checkout per penjual, dompet, QRIS, tunai, dan escrow.'],
    ['help_sell', 'Cara Jualan', 'Dari daftar produk, moderasi, sampai dana cair: foto, stok, COD/pickup, penarikan, dan fee platform.'],
    ['help_faq', 'Tanya Jawab', 'Pertanyaan yang paling sering ditanyakan: akun, top-up, order, unduhan digital, dan komplain.'],
    ['help_warranty', 'Garansi & Komplain', 'Kapan komplain bisa dibuka, apa yang diputuskan admin, dan apa yang terjadi pada order tunai.'],
    ['help_terms', 'Syarat & Ketentuan', 'Aturan main platform: peran, fee, barang terlarang, moderasi, dan privasi order.'],
];
?>
<p class="neo-eyebrow mb-1">Pusat bantuan</p>
<h1 class="text-3xl font-black mb-2">Bantuan</h1>
<p class="text-sm font-semibold mb-4">Semua aturan Marketplace Bekasi Utara ada di sini. Kalau ada yang tidak jelas, buka komplain dari halaman order (untuk masalah order) atau laporkan lewat tombol Laporkan di produk/ulasan.</p>
<div class="space-y-3">
<?php foreach ($items as $it): ?>
<div class="neo-card p-4">
  <h2 class="font-black text-lg"><a class="underline decoration-[3px]" href="<?= url($it[0]) ?>"><?= $it[1] ?></a></h2>
  <p class="text-sm font-medium mt-1"><?= $it[2] ?></p>
</div>
<?php endforeach; ?>
</div>
