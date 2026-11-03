<?php
// ============================================================================
// Marketplace Bekasi Utara — Front controller (Increment 1)
// Semua request web lewat file ini. Router memakai WHITELIST:
// halaman GET dan aksi POST yang tidak terdaftar ditolak (404/403).
// Jalankan dengan config.php di server (disalin dari app/config.example.php).
// ============================================================================
define('MARKETPLACE_APP', true);
require __DIR__ . '/app/config.php';
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Jakarta');

session_name(defined('SESSION_NAME') ? SESSION_NAME : 'mbu_session');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax',
]);
session_start();

require __DIR__ . '/app/db.php';
require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/services.php';
require __DIR__ . '/app/doku.php';
require __DIR__ . '/app/storage.php';
require __DIR__ . '/app/controllers/auth.php';
require __DIR__ . '/app/controllers/products.php';
require __DIR__ . '/app/controllers/orders.php';
require __DIR__ . '/app/controllers/wallet.php';
require __DIR__ . '/app/controllers/disputes.php';
require __DIR__ . '/app/controllers/admin.php';
require __DIR__ . '/app/controllers/profile.php';

// --- Aksi POST (whitelist) ---------------------------------------------------
$actions = [
    'register' => 'auth_register',
    'login' => 'auth_login',
    'logout' => 'auth_logout',
    'profile_whatsapp_save' => 'profile_whatsapp_save',
    'product_save' => 'product_save',
    'product_archive' => 'product_archive',
    'product_approve' => 'product_approve',
    'product_reject' => 'product_reject',
    'cart_add' => 'cart_add',
    'cart_update' => 'cart_update',
    'cart_remove' => 'cart_remove',
    'review_submit' => 'review_submit',
    'report_submit' => 'report_submit',
    'block_seller' => 'block_seller',
    'unblock_seller' => 'unblock_seller',
    'review_hide' => 'review_hide',
    'review_unhide' => 'review_unhide',
    'report_decide' => 'report_decide',
    'checkout' => 'checkout',
    'order_repay' => 'order_repay',
    'token_revoke' => 'token_revoke',
    'order_cancel' => 'order_cancel',
    'order_confirm' => 'order_confirm',
    'order_cash_received' => 'order_cash_received',
    'order_shipped' => 'order_shipped',
    'order_settle_cash' => 'order_settle_cash',
    'download_token_request' => 'download_token_request',
    'withdrawal_request' => 'withdrawal_request',
    'withdrawal_approve' => function () { withdrawal_decide('approve'); },
    'withdrawal_reject' => function () { withdrawal_decide('reject'); },
    'withdrawal_complete' => 'withdrawal_complete',
    'dispute_open' => 'dispute_open',
    'dispute_resolve' => 'dispute_resolve',
    'topup_request' => 'topup_request',
    'admin_settings_save' => 'admin_settings_save',
    'admin_user_suspend' => function () { admin_user_status('suspended'); },
    'admin_user_unsuspend' => function () { admin_user_status('active'); },
    'category_save' => 'category_save',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if (!isset($actions[$action])) { http_response_code(404); exit('Aksi tidak dikenal.'); }
    $fn = $actions[$action];
    $fn();
    exit; // controller selalu redirect / exit; baris ini pengaman saja.
}

// --- Halaman GET (whitelist) -------------------------------------------------
$pages = [
    'catalog' => ['view' => 'catalog', 'title' => 'Katalog'],
    'product' => ['view' => 'product', 'title' => 'Detail produk'],
    'cart' => ['view' => 'cart', 'title' => 'Keranjang'],
    'checkout' => ['view' => 'checkout', 'title' => 'Checkout'],
    'login' => ['view' => 'login', 'title' => 'Login'],
    'register' => ['view' => 'register', 'title' => 'Daftar'],
    'wallet' => ['view' => 'wallet', 'title' => 'Dompet'],
    'topup' => ['view' => 'topup', 'title' => 'Top-up'],
    'my_orders' => ['view' => 'my_orders', 'title' => 'Order saya'],
    'order' => ['view' => 'order', 'title' => 'Detail order'],
    'my_products' => ['view' => 'my_products', 'title' => 'Produk saya'],
    'product_form' => ['view' => 'product_form', 'title' => 'Form produk'],
    'my_purchases' => ['view' => 'my_purchases', 'title' => 'Pembelian digital'],
    'withdrawals' => ['view' => 'withdrawals', 'title' => 'Penarikan'],
    'disputes' => ['view' => 'disputes', 'title' => 'Sengketa'],
    'admin' => ['view' => 'admin', 'title' => 'Dashboard admin'],
    'admin_settings' => ['view' => 'admin_settings', 'title' => 'Pengaturan'],
    'admin_users' => ['view' => 'admin_users', 'title' => 'Pengguna'],
    'admin_moderation' => ['view' => 'admin_moderation', 'title' => 'Moderasi'],
    'admin_orders' => ['view' => 'admin_orders', 'title' => 'Order'],
    'admin_topups' => ['view' => 'admin_topups', 'title' => 'Top-up'],
    'admin_withdrawals' => ['view' => 'admin_withdrawals', 'title' => 'Penarikan'],
    'admin_disputes' => ['view' => 'admin_disputes', 'title' => 'Sengketa'],
    'admin_tokens' => ['view' => 'admin_tokens', 'title' => 'Token unduhan'],
    'admin_orphans' => ['view' => 'admin_orphans', 'title' => 'Pembayaran yatim (DOKU)'],
    'admin_reports' => ['view' => 'admin_reports', 'title' => 'Laporan pengguna'],
    'admin_scan' => ['view' => 'admin_scan', 'title' => 'Pindai barang terlarang'],
    'admin_categories' => ['view' => 'admin_categories', 'title' => 'Kategori'],
    'help' => ['view' => 'help', 'title' => 'Bantuan'],
    'help_shop' => ['view' => 'help_shop', 'title' => 'Cara Belanja'],
    'help_sell' => ['view' => 'help_sell', 'title' => 'Cara Jualan'],
    'help_faq' => ['view' => 'help_faq', 'title' => 'Tanya Jawab'],
    'help_warranty' => ['view' => 'help_warranty', 'title' => 'Garansi & Komplain'],
    'help_terms' => ['view' => 'help_terms', 'title' => 'Syarat & Ketentuan'],
    'whatsapp' => ['view' => 'whatsapp', 'title' => 'Nomor WhatsApp'],
    'track' => ['view' => 'track', 'title' => 'Lacak Order'],
];
$page = (string)($_GET['page'] ?? 'catalog');
if (!isset($pages[$page])) { http_response_code(404); exit('Halaman tidak ditemukan.'); }
render($pages[$page]['view'], ['title' => $pages[$page]['title']]);
