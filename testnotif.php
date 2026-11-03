<?php
// ============================================================================
// TEST SEMENTARA — HAPUS FILE INI DARI SERVER SEGERA SETELAH TES SELESAI!
// ----------------------------------------------------------------------------
// File ini mengirim notifikasi SUCCESS/FAILED/EXPIRED *tiruan* yang
// bertanda tangan sah (resep Jokul resmi DOKU) ke webhook marketplace.
// Tujuannya: menguji verifikasi signature, pencocokan invoice,
// pengkreditan saldo, dan idempotensi — karena simulator DOKU tidak
// menyediakan simulasi QRIS dan QR sandbox tidak bisa dibayar dompet asli.
//
// BAHAYA: siapa pun yang bisa membuka file ini dengan key-nya bisa
// memalsukan notifikasi lunas dan mengkredit saldo seenaknya. Karena itu:
//  1. Isi $TEST_KEY di bawah dengan kata sandi sementara apa pun.
//  2. Upload, jalankan SATU kali (dan sekali lagi untuk tes duplikat).
//  3. HAPUS file ini dari server, lalu verifikasi URL-nya sudah 404.
// Jangan pernah membiarkan file ini tertinggal di server produksi.
// ============================================================================
define('MARKETPLACE_APP', true);
require __DIR__ . '/app/config.php';

$TEST_KEY = 'qwertyu123'; // <-- EDIT BARIS INI DULU

header('Content-Type: text/plain; charset=utf-8');
if (($_GET['key'] ?? '') !== $TEST_KEY) {
    http_response_code(403);
    echo "Akses ditolak. Isi \$TEST_KEY di file ini, lalu panggil dengan ?key=<nilai itu>.\n";
    exit;
}
$invoice = (string)($_GET['invoice'] ?? '');
$status = strtoupper((string)($_GET['status'] ?? 'SUCCESS'));
if ($invoice === '' || !in_array($status, ['SUCCESS', 'FAILED', 'EXPIRED'], true)) {
    http_response_code(400);
    echo "Pakai: test-notify.php?key=<kunci>&invoice=TOPUP-20261029-6&status=SUCCESS\n";
    echo "status boleh: SUCCESS, FAILED, EXPIRED.\n";
    exit;
}

// Badan notifikasi gaya Jokul (yang dibaca webhook: order.invoice_number
// + transaction.status). Nilai amount hanya informatif; webhook memakai
// amount dari baris topup/order di database.
$body = [
    'order' => ['invoice_number' => $invoice],
    'transaction' => ['status' => $status],
];
$json = json_encode($body);

// Signature Jokul resmi (sama seperti yang dipakai createQris):
// Digest = base64(SHA-256(body)); komponen per baris; Signature =
// 'HMACSHA256=' + base64(HMAC-SHA256(SecretKey, komponen)).
$requestId = bin2hex(random_bytes(16));
$timestamp = gmdate('Y-m-d\TH:i:s\Z');
$target = '/webhook-doku.php';
$digest = base64_encode(hash('sha256', $json, true));
$component = 'Client-Id:' . DOKU_CLIENT_ID . "\n"
    . 'Request-Id:' . $requestId . "\n"
    . 'Request-Timestamp:' . $timestamp . "\n"
    . 'Request-Target:' . $target . "\n"
    . 'Digest:' . $digest;
$signature = 'HMACSHA256=' . base64_encode(hash_hmac('sha256', $component, DOKU_SECRET_KEY, true));

$url = rtrim(APP_URL, '/') . $target;
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true, CURLOPT_POSTFIELDS => $json, CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Client-Id: ' . DOKU_CLIENT_ID,
        'Request-Id: ' . $requestId,
        'Request-Timestamp: ' . $timestamp,
        'Signature: ' . $signature,
    ],
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

echo "POST $url\n";
echo "invoice: $invoice | status kirim: $status\n";
echo "HTTP: $code\n";
echo "Respons webhook: $resp\n";
echo "\nSEGERA HAPUS FILE INI DARI SERVER SETELAH TES SELESAI.\n";
