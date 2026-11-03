<?php
// Auth: register / login / logout. Throttling via login_attempts:
// kunci setelah 5 gagal / 15 menit per email+IP.
if (!defined('MARKETPLACE_APP')) { http_response_code(403); exit('Forbidden'); }

function auth_register(): void
{
    csrf_check();
    $nama = post('nama'); $email = strtolower(post('email'));
    $pass = (string)($_POST['password'] ?? '');
    $telepon = post('telepon'); $alamat = post('alamat');
    if ($nama === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
        flash('error', 'Nama, email valid, dan password minimal 8 karakter wajib diisi.');
        redirect(url('register'));
    }
    $db = pdo();
    $st = $db->prepare('SELECT id FROM users WHERE email = ?');
    $st->execute([$email]);
    if ($st->fetch()) { flash('error', 'Email sudah terdaftar.'); redirect(url('register')); }
    $db->prepare('INSERT INTO users (email, password_hash, nama, telepon, alamat) VALUES (?,?,?,?,?)')
        ->execute([$email, password_hash($pass, PASSWORD_DEFAULT), $nama, $telepon ?: null, $alamat ?: null]);
    flash('success', 'Akun dibuat. Silakan login.');
    redirect(url('login'));
}

function auth_login(): void
{
    csrf_check();
    $email = strtolower(post('email'));
    $pass = (string)($_POST['password'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $db = pdo();
    // Hitung gagal 15 menit terakhir untuk pasangan email+IP ini.
    $st = $db->prepare("SELECT COUNT(*) c FROM login_attempts WHERE email = ? AND ip <=> ? AND success = 0 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    // Catatan: NOW() di sini memakai jam server; aplikasi memakai WIB via APP_TIMEZONE.
    $st->execute([$email, $ip]);
    if ((int)$st->fetch()['c'] >= 5) {
        flash('error', 'Terlalu banyak percobaan gagal. Coba lagi setelah 15 menit.');
        redirect(url('login'));
    }
    // FIX-ALL C16: penghitung KEDUA per email saja (20 gagal / 15 menit,
    // apa pun IP-nya) — rotasi IP tidak lagi memberi 5 percobaan baru
    // tanpa batas. Ambang lebih longgar dari 5/IP agar pengguna sah yang
    // berpindah jaringan tidak terkunci terlalu cepat.
    $st = $db->prepare("SELECT COUNT(*) c FROM login_attempts WHERE email = ? AND success = 0 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $st->execute([$email]);
    if ((int)$st->fetch()['c'] >= 20) {
        flash('error', 'Terlalu banyak percobaan gagal untuk akun ini. Coba lagi setelah 15 menit.');
        redirect(url('login'));
    }
    $st = $db->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();
    $ok = $u && password_verify($pass, $u['password_hash']);
    $db->prepare('INSERT INTO login_attempts (email, ip, success) VALUES (?,?,?)')
        ->execute([$email, $ip, $ok ? 1 : 0]);
    if (!$ok) { flash('error', 'Email atau password salah.'); redirect(url('login')); }
    if ($u['status'] === 'suspended') { flash('error', 'Akun Anda ditangguhkan. Hubungi admin.'); redirect(url('login')); }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $u['id'];
    // Jejak login (audit). Token sesi acak, disimpan dalam bentuk hash saja.
    $raw = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO sessions (user_id, token_hash, ip, user_agent, expires_at) VALUES (?,?,?,?, DATE_ADD(NOW(), INTERVAL 30 DAY))')
        ->execute([ $u['id'], hash('sha256', $raw), $ip, substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ]);
    $_SESSION['session_token'] = $raw;
    flash('success', 'Selamat datang, ' . $u['nama'] . '.');
    redirect(url('catalog'));
}

function auth_logout(): void
{
    csrf_check();
    // FIX-ALL B12: hapus baris sessions milik sesi ini agar token lama
    // tidak bisa dipakai lagi (sebelumnya logout tidak menyentuh tabel).
    try {
        if (!empty($_SESSION['session_token']) && !empty($_SESSION['user_id'])) {
            pdo()->prepare('DELETE FROM sessions WHERE user_id = ? AND token_hash = ?')
                ->execute([(int)$_SESSION['user_id'], hash('sha256', (string)$_SESSION['session_token'])]);
        }
    } catch (Throwable $e) { /* logout tetap dilanjutkan */ }
    session_unset(); session_destroy();
    redirect(url('catalog'));
}

