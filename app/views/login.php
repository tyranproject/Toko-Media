<p class="neo-eyebrow mb-1">Selamat datang kembali</p>
<h1 class="text-3xl font-black mb-4">Login</h1>
<form method="post" action="index.php" class="neo-card p-5 max-w-md space-y-3">
<?= csrf_field() ?><input type="hidden" name="action" value="login">
<label class="block text-sm font-bold">Email<input name="email" type="email" required class="neo-input px-3 py-2 w-full mt-1"></label>
<label class="block text-sm font-bold">Password<input name="password" type="password" required class="neo-input px-3 py-2 w-full mt-1"></label>
<button class="neo-btn neo-btn-pink px-5 py-2">Login</button>
<p class="text-sm font-semibold">Belum punya akun? <a class="underline decoration-[3px]" href="<?= url('register') ?>">Daftar</a>.</p>
</form>
