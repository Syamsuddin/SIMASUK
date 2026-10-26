<?php

declare(strict_types=1);

/** @var array<string,string> $peran */
?>
<div class="mb-6">
    <h1 class="text-lg font-semibold">Tambah Pengguna</h1>
</div>
<section class="max-w-2xl rounded-md border border-line bg-surface p-6 shadow-sm">
    <?php if (errors() !== []): ?>
    <p role="alert" class="mb-4 rounded-md border border-danger px-3 py-2 text-sm text-danger">Periksa kembali isian yang ditandai.</p>
    <?php endif ?>
    <form method="post" action="/pengguna" class="flex flex-col gap-4" novalidate>
        <?= csrf_field() ?>
        <?= component('field', ['name' => 'nama', 'label' => 'Nama lengkap', 'required' => true]) ?>
        <?= component('field', ['name' => 'username', 'label' => 'Username', 'required' => true, 'autocomplete' => 'off', 'hint' => 'Huruf kecil, angka, titik, atau garis bawah. Contoh: siti.tu']) ?>
        <?= component('field', ['name' => 'role', 'label' => 'Peran', 'type' => 'select', 'options' => $peran, 'placeholder' => 'Pilih peran', 'required' => true]) ?>
        <div class="grid grid-cols-1 gap-4 min-[769px]:grid-cols-2">
            <?= component('field', ['name' => 'password', 'label' => 'Password awal', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'hint' => 'Minimal 10 karakter.']) ?>
            <?= component('field', ['name' => 'password_konfirmasi', 'label' => 'Ulangi password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
        </div>
        <div class="flex gap-2">
            <?= component('button', ['label' => 'Simpan']) ?>
            <?= component('button', ['label' => 'Batal', 'href' => '/pengguna', 'variant' => 'garis']) ?>
        </div>
    </form>
</section>
