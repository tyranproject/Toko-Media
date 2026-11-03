<?php
// ============================================================================
// PEMBUATAN ADMIN PERTAMA — HAPUS FILE INI SETELAH DIPAKAI!
// Dijaga SETUP_KEY dari config.php (?key=...) dan hanya berjalan bila belum
// ada admin sama sekali. Setelah admin dibuat, HAPUS setup.php dari server.
// ============================================================================
define('MARKETPLACE_APP', true);
require __DIR__ . '/app/config.php';
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Jakarta');
require __DIR__ . '/app/db.php';

if (!hash_equals((string)SETUP_KEY, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden: kunci setup salah.');
}
$db = pdo();
$adminCount = (int)$db->query("SELECT COUNT(*) c FROM users WHERE role = 'admin'")->fetch()['c'];
if ($adminCount > 0) { exit('Admin sudah ada. Hapus setup.php sekarang.'); }

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim((string)($_POST['nama'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass = (string)($_POST['password'] ?? '');
    if ($nama === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
        $message = 'Nama, email valid, dan password minimal 8 karakter wajib diisi.';
    } else {
        $db->prepare("INSERT INTO users (email, password_hash, nama, role, status) VALUES (?,?,?, 'admin', 'active')")
            ->execute([$email, password_hash($pass, PASSWORD_DEFAULT), $nama]);
        exit('<h1>Admin dibuat.</h1><p style="color:red;font-weight:bold">SEKARANG HAPUS setup.php DARI SERVER sebelum melanjutkan!</p>');
    }
}
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Setup Admin Pertama</title></head><body>
<h1>Buat Admin Pertama</h1>
<p style="color:red;font-weight:bold">PERINGATAN BESAR: HAPUS setup.php dari server segera setelah admin dibuat. File ini memungkinkan pembuatan admin bila kuncinya bocor.</p>
<?php if ($message): ?><p style="color:red"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post">
<label>Nama: <input name="nama" required></label><br><br>
<label>Email: <input name="email" type="email" required></label><br><br>
<label>Password (min 8): <input name="password" type="password" required minlength="8"></label><br><br>
<button>Buat admin</button>
</form></body></html>
