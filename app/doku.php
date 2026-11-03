<?php
// Klien DOKU (Jokul Checkout) tanpa SDK: raw cURL + HMAC.
// ============================================================================
// Detail integrasi di file ini DICCOCOKKAN dengan dokumentasi resmi DOKU
// (developers.doku.com, halaman "Backend Integration to initiate payment"
// dan "Signature Component from Request Header") pada 2026-10-29:
//  - Endpoint: POST {base}/checkout/v1/payment (sandbox
//    https://api-sandbox.doku.com, produksi https://api.doku.com).
//  - Header request: Client-Id, Request-Id, Request-Timestamp (UTC ISO8601
//    ...Z), Signature — TANPA awalan X-.
//  - Signature: 'HMACSHA256=' + base64(HMAC-SHA256(SecretKey, komponen))
//    dengan komponen per baris (tanpa baris kosong di akhir):
//      Client-Id:{id}\nRequest-Id:{rid}\nRequest-Timestamp:{ts}
//      \nRequest-Target:/checkout/v1/payment\nDigest:{digest}
//    dan Digest = base64(SHA-256(body JSON persis yang dikirim)).
//  - Respons sukses TIDAK memuat transaction_id: yang dikembalikan adalah
//    response.payment.url (halaman checkout DOKU, wajib ditampilkan ke
//    pembeli) dan response.order.session_id (pengenal sesi DOKU).
//  - additional_info.override_notification_url: mengesampingkan Notification
//    URL dashboard untuk transaksi ini — notifikasi dikirim ke URL tersebut.
// Konfigurasi (termasuk base URL) berasal dari config.php; default-nya
// KOSONG sehingga tanpa konfigurasi, pembayaran gagal secara eksplisit.
// ============================================================================
if (!defined('MARKETPLACE_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

class DokuException extends RuntimeException {}

class DokuClient
{
    public function configured(): bool
    {
        return DOKU_BASE_URL !== '' && DOKU_CLIENT_ID !== '' && DOKU_SECRET_KEY !== '';
    }

    // Buat pembayaran QRIS via Jokul Checkout. Mengembalikan
    // ['transaction_id', 'qr_payload', 'payment_url'].
    //  - transaction_id: session_id DOKU, atau token checkout, atau nomor
    //    invoice sebagai fallback unik (Jokul tidak mengembalikan
    //    transaction_id; pencocokan webhook mengandalkan invoice).
    //  - qr_payload / payment_url: URL halaman checkout DOKU — pembeli
    //    membukanya dan membayar QRIS di sana (QR ditampilkan DOKU).
    // CATATAN 2026-10-30: payment_due_date TIDAK dikirim ke DOKU.
    // Field itu ditambahkan fix-all C17 tanpa bukti di skema Jokul dan
    // menjadi satu-satunya perubahan body sejak top-up terakhir terbukti
    // berhasil (29 Okt). Masa berlaku 60 menit tetap ditegakkan di sisi
    // kita (topups.expires_at + cron + label di view).
    public function createQris(float $amount, string $invoice, string $customerEmail): array
    {
        if (!$this->configured()) {
            throw new DokuException('Pembayaran DOKU belum dikonfigurasi (periksa config.php).');
        }
        $body = [
            'order' => ['invoice_number' => $invoice, 'amount' => (int)round($amount)],
            'payment' => ['payment_method_types' => ['QRIS']],
            'customer' => ['email' => $customerEmail],
        ];
        // Beri tahu DOKU ke mana notifikasi transaksi ini harus dikirim.
        // Ini mengesampingkan Notification URL di dashboard (bila ada).
        if (defined('APP_URL') && APP_URL !== '') {
            $body['additional_info'] = ['override_notification_url' => rtrim(APP_URL, '/') . '/webhook-doku.php'];
        }
        $json = json_encode($body);
        $requestId = bin2hex(random_bytes(16)); // string unik, maks 128 karakter
        $timestamp = gmdate('Y-m-d\TH:i:s\Z'); // UTC ISO8601 (WIB dikurangi 7 jam)
        $target = '/checkout/v1/payment';
        $digest = base64_encode(hash('sha256', $json, true));
        $component = 'Client-Id:' . DOKU_CLIENT_ID . "\n"
            . 'Request-Id:' . $requestId . "\n"
            . 'Request-Timestamp:' . $timestamp . "\n"
            . 'Request-Target:' . $target . "\n"
            . 'Digest:' . $digest;
        $signature = 'HMACSHA256=' . base64_encode(hash_hmac('sha256', $component, DOKU_SECRET_KEY, true));
        $ch = curl_init(rtrim(DOKU_BASE_URL, '/') . $target);
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
        if ($resp === false || $code < 200 || $code >= 300) {
            // FIX-ALL C9: badan respons DOKU HANYA ke error_log server,
            // tidak pernah ke pesan pengguna (pesan exception ini sampai
            // ke flash). Resep signature / parsing respons TIDAK diubah.
            $detail = $resp !== false ? substr((string)$resp, 0, 300) : 'tidak ada respons';
            error_log('DOKU createQris gagal (HTTP ' . (int)$code . ') invoice ' . $invoice . ': ' . $detail);
            throw new DokuException('Pembayaran gagal dibuat, coba lagi.');
        }
        $data = json_decode((string)$resp, true);
        $r = is_array($data['response'] ?? null) ? $data['response'] : $data;
        $url = $r['payment']['url'] ?? $data['payment']['url'] ?? null;
        $tx = $r['order']['session_id'] ?? $r['payment']['token_id'] ?? $data['transaction_id'] ?? $r['transaction_id'] ?? null;
        if (!$url && !$tx) { throw new DokuException('Respons DOKU tidak memuat payment.url.'); }
        // Fallback unik bila DOKU tak memberi session/token: nomor invoice
        // (unik per transaksi; kolom doku_transaction_id ber-UNIQUE).
        if (!$tx) { $tx = $invoice; }
        return ['transaction_id' => (string)$tx, 'qr_payload' => $url ? (string)$url : null, 'payment_url' => $url ? (string)$url : null];
    }

    // Verifikasi signature notifikasi Jokul.
    // Skema utama (dokumen DOKU): header Client-Id, Request-Id,
    // Request-Timestamp, Signature; komponen = Client-Id / Request-Id /
    // Request-Timestamp / Request-Target (path URL notifikasi merchant) /
    // Digest (base64 SHA-256 raw body), HMAC-SHA256 dengan Secret Key,
    // diawali 'HMACSHA256='.
    // Fallback: skema lama (header X-Doku-Signature/X-Signature berisi
    // HMAC-SHA256 atas raw body) bila header Jokul tidak ada.
    public function verifyWebhook(string $rawBody, array $headers, string $targetPath = '/webhook-doku.php'): bool
    {
        $sig = $headers['signature'] ?? null;
        if ($sig !== null && $sig !== '') {
            $clientId = $headers['client_id'] ?? '';
            $requestId = $headers['request_id'] ?? '';
            $timestamp = $headers['timestamp'] ?? '';
            if ($clientId !== '' && $requestId !== '' && $timestamp !== '') {
                $digest = base64_encode(hash('sha256', $rawBody, true));
                $component = 'Client-Id:' . $clientId . "\n"
                    . 'Request-Id:' . $requestId . "\n"
                    . 'Request-Timestamp:' . $timestamp . "\n"
                    . 'Request-Target:' . $targetPath . "\n"
                    . 'Digest:' . $digest;
                $expected = 'HMACSHA256=' . base64_encode(hash_hmac('sha256', $component, DOKU_SECRET_KEY, true));
                if (hash_equals($expected, trim((string)$sig))) { return true; }
            }
        }
        $legacy = $headers['x_doku_signature'] ?? $headers['x_signature'] ?? null;
        if ($legacy !== null && $legacy !== '') {
            $expected = hash_hmac('sha256', $rawBody, DOKU_SECRET_KEY);
            $given = (string)$legacy;
            if (stripos($given, 'HMAC ') === 0) { $given = substr($given, 5); }
            if (hash_equals(strtolower($expected), strtolower(trim($given)))) { return true; }
        }
        return false;
    }

    // Normalisasi payload notifikasi dari berbagai kemungkinan bentuk field.
    // Jokul membawa invoice merchant (order.invoice_number) — pengenal
    // paling andal — dan status di transaction.status / status.
    // Mengembalikan ['transaction_id', 'invoice', 'status'] (status mentah).
    public static function parseWebhook(array $payload): array
    {
        $tx = null; $inv = null; $status = null;
        $containers = [$payload];
        if (is_array($payload['data'] ?? null)) { $containers[] = $payload['data']; }
        if (is_array($payload['response'] ?? null)) { $containers[] = $payload['response']; }
        foreach ($containers as $c) {
            if ($tx === null && is_array($c['transaction'] ?? null)) {
                $tx = $c['transaction']['transaction_id'] ?? $c['transaction']['id'] ?? null;
            }
            if ($status === null && is_array($c['transaction'] ?? null)) {
                $status = $c['transaction']['status'] ?? null;
            }
            if ($inv === null && is_array($c['order'] ?? null)) {
                $inv = $c['order']['invoice_number'] ?? $c['order']['invoiceNumber'] ?? null;
            }
            if ($tx === null) { $tx = $c['transaction_id'] ?? $c['transactionId'] ?? $c['session_id'] ?? $c['token_id'] ?? null; }
            if ($inv === null) { $inv = $c['invoice_number'] ?? $c['invoiceNumber'] ?? $c['invoice'] ?? null; }
            if ($status === null) { $status = $c['status'] ?? $c['transaction_status'] ?? null; }
        }
        return [
            'transaction_id' => $tx !== null ? (string)$tx : null,
            'invoice' => $inv !== null ? (string)$inv : null,
            'status' => strtoupper((string)($status ?? '')),
        ];
    }

    public static function isPaidStatus(string $status): bool
    {
        return in_array($status, ['SUCCESS', 'PAID', 'SETTLED', 'COMPLETED'], true);
    }
    public static function isFailedStatus(string $status): bool
    {
        return in_array($status, ['FAILED', 'FAILURE', 'EXPIRED', 'CANCELLED', 'VOID'], true);
    }
    // FIX-ALL C15: varian status refund DOKU yang didokumentasikan.
    // Status yang tidak dikenali tetap dicatat ke error_log oleh webhook,
    // bukan dibalas diam-diam sebagai ok tanpa aksi.
    public static function isRefundedStatus(string $status): bool
    {
        return in_array($status, ['REFUNDED', 'REFUND', 'PARTIAL_REFUNDED', 'REVERSED'], true);
    }
}
