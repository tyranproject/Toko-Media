<p class="neo-eyebrow mb-1">Gabung marketplace</p>
<h1 class="text-3xl font-black mb-4">Daftar akun</h1>
<form method="post" action="index.php" class="neo-card p-5 max-w-md space-y-3">
<?= csrf_field() ?><input type="hidden" name="action" value="register">
<label class="block text-sm font-bold">Nama<input name="nama" required class="neo-input px-3 py-2 w-full mt-1"></label>
<label class="block text-sm font-bold">Email<input name="email" type="email" required class="neo-input px-3 py-2 w-full mt-1"></label>
<label class="block text-sm font-bold">Password (min. 8 karakter)<input name="password" type="password" required minlength="8" class="neo-input px-3 py-2 w-full mt-1"></label>
<label class="block text-sm font-bold">Telepon<input name="telepon" class="neo-input px-3 py-2 w-full mt-1"></label>
<label class="block text-sm font-bold">Alamat<textarea name="alamat" class="neo-input px-3 py-2 w-full mt-1"></textarea></label>
<button class="neo-btn neo-btn-pink px-5 py-2">Daftar</button>
</form>
