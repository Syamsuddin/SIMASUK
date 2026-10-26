<?php

declare(strict_types=1);

// Ubah password sendiri (docs/05: semua peran).
?>
<div class="mb-6">
    <h1 class="text-lg font-semibold">Ubah Password</h1>
</div>
<section class="max-w-2xl rounded-md border border-line bg-surface p-6 shadow-sm">
    <?php if (errors() !== []): ?>
    <p role="alert" class="mb-4 rounded-md border border-danger px-3 py-2 text-sm text-danger">Periksa kembali isian yang ditandai.</p>
    <?php endif ?>
    <form method="post" action="/ubah-password" class="flex flex-col gap-4" novalidate>
        <?= csrf_field() ?>
        <?= component('field', ['name' => 'password_lama', 'label' => 'Password lama', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
        <?= component('field', ['name' => 'password_baru', 'label' => 'Password baru', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'hint' => 'Minimal 10 karakter.']) ?>
        <?= component('field', ['name' => 'password_konfirmasi', 'label' => 'Ulangi password baru', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
        <div class="flex gap-2">
            <?= component('button', ['label' => 'Simpan']) ?>
            <?= component('button', ['label' => 'Batal', 'href' => '/', 'variant' => 'garis']) ?>
        </div>
    </form>
</section>
