<?php
// ============================================================================
// Penyimpanan file digital LOKAL (server sendiri — bukan Google Drive).
// File produk digital disimpan di disk server, di folder yang dikonfigurasi
// lewat DIGITAL_FILES_DIR (di config.php). Folder tersebut WAJIB berada di
// LUAR docroot (mis. /home/akun/mbu-digital/) agar tidak ada URL yang bisa
// mengaksesnya. Bila DIGITAL_FILES_DIR kosong, dipakai folder fallback di
// dalam docroot (digital-files/) yang diproteksi .htaccess "Require all
// denied" (file .htaccess dibuat otomatis bila belum ada).
//
// Keamanan:
// - Nama file di disk SELALU nama internal acak (32 heksadesimal + ".bin"),
//   tidak pernah memakai nama dari pengguna. Nama asli hanya disimpan di
//   database (kolom drive_file_name) dan dipakai untuk header
//   Content-Disposition saat unduhan.
// - Tidak ada input pengguna yang pernah menjadi bagian path: resolve()
//   hanya menerima pola ketat ^[a-f0-9]{32}\.bin$ dan memverifikasi dengan
//   realpath bahwa file benar-benar berada di dalam folder storage.
// - File tidak pernah dieksekusi lewat web: folder utama di luar docroot,
//   folder fallback diblokir .htaccess, dan ekstensi .bin bukan .php.
// - Unduhan HANYA lewat download.php, yang memvalidasi token SEBELUM file
//   dibaca (hash cocok + milik pembeli + belum kedaluwarsa + uses <
//   max_uses + status active). File ini tidak pernah melayani HTTP langsung.
// ============================================================================
if (!defined('MARKETPLACE_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

class StorageException extends RuntimeException {}

class LocalStorage
{
    // Folder penyimpanan aktif. Bila DIGITAL_FILES_DIR diisi, folder itu yang
    // dipakai (dibuat bila belum ada; gagal dengan pesan jelas bila tidak
    // bisa dibuat). Bila kosong, dipakai folder fallback di dalam docroot.
    public static function dir(): string
    {
        if (defined('DIGITAL_FILES_DIR') && DIGITAL_FILES_DIR !== '') {
            $dir = rtrim(DIGITAL_FILES_DIR, '/');
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new StorageException('Folder penyimpanan file digital tidak bisa dibuat: ' . $dir);
                }
            }
            if (!is_writable($dir)) {
                throw new StorageException('Folder penyimpanan file digital tidak bisa ditulis: ' . $dir);
            }
            return $dir;
        }
        $fallback = __DIR__ . '/../digital-files';
        if (!is_dir($fallback)) {
            if (!mkdir($fallback, 0755, true)) {
                throw new StorageException('Folder fallback file digital tidak bisa dibuat.');
            }
        }
        $ht = $fallback . '/.htaccess';
        if (!is_file($ht)) {
            file_put_contents($ht, "Require all denied\n");
        }
        return $fallback;
    }

    // Ubah nama tersimpan menjadi path absolut yang terverifikasi.
    // Hanya nama internal (32 heksadesimal + .bin) yang diterima; apa pun
    // yang lain (termasuk "../", path absolut, atau ID lama dari backend
    // lain) ditolak — gagal tertutup, tidak pernah membocorkan file lain.
    private function resolve(string $storedName): string
    {
        $base = basename($storedName);
        if ($base !== $storedName || !preg_match('/^[a-f0-9]{32}\.bin$/', $base)) {
            throw new StorageException('Nama file digital tidak valid.');
        }
        $dir = realpath(self::dir());
        if ($dir === false) {
            throw new StorageException('Folder penyimpanan file digital tidak ditemukan.');
        }
        $path = $dir . '/' . $base;
        $real = realpath($path);
        if ($real === false || strpos($real, $dir . '/') !== 0) {
            throw new StorageException('File digital tidak ditemukan.');
        }
        return $real;
    }

    // Simpan file hasil upload. Mengembalikan ['id','name','size'] dengan
    // bentuk yang sama seperti klien lama: 'id' = nama internal di disk,
    // 'name' = nama asli (untuk header unduhan), 'size' = ukuran byte.
    // $tmpPath HARUS berasal dari $_FILES (move_uploaded_file menolak
    // sumber lain). Validasi ukuran sudah dilakukan pemanggil (batas
    // settings.max_digital_mb + batas php.ini lewat kode error upload).
    public function store(string $tmpPath, string $originalName): array
    {
        $stored = bin2hex(random_bytes(16)) . '.bin';
        $dest = self::dir() . '/' . $stored;
        if (!move_uploaded_file($tmpPath, $dest)) {
            throw new StorageException('Gagal menyimpan file digital.');
        }
        chmod($dest, 0644);
        return ['id' => $stored, 'name' => $originalName, 'size' => (int)filesize($dest)];
    }

    // FIX-ALL C11: cek keberadaan + keterbacaan file SEBELUM pemakaian
    // token di-commit (download.php memanggil ini di dalam transaksi
    // validasi). Gagal tertutup bila nama tidak valid / file hilang.
    public function exists(string $storedName): bool
    {
        try {
            $path = $this->resolve($storedName);
        } catch (Throwable $e) {
            return false;
        }
        return is_readable($path);
    }

    // Stream file tersimpan ke output (chunked) — dipakai download.php
    // SETELAH validasi token. Gagal tertutup bila file tidak ada.
    public function stream(string $storedName): void
    {
        $path = $this->resolve($storedName);
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new StorageException('File digital tidak ditemukan.');
        }
        while (!feof($fh)) {
            echo fread($fh, 8192);
            flush();
        }
        fclose($fh);
    }

    // Hapus file tersimpan (pembersihan best-effort saat penjual mengganti
    // file produk; kegagalan diabaikan — tidak pernah menggagalkan simpan).
    // Aman untuk token lama: token selalu mengacu ke file produk SAAT INI
    // lewat baris products, bukan ke file lama.
    public function delete(string $storedName): void
    {
        try {
            $path = $this->resolve($storedName);
        } catch (Throwable $e) {
            return;
        }
        @unlink($path);
    }
}
