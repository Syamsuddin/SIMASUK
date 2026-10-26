<?php

declare(strict_types=1);

/** @var array<string,mixed> $target */
?>
<div class="mb-6">
    <h1 class="text-lg font-semibold">Reset Password</h1>
    <p class="text-ink-muted"><?= e($target['nama']) ?> · <?= e($target['username']) ?></p>
</div>
<section class="max-w-2xl rounded-md border border-line bg-surface p-6 shadow-sm">
    <p class="mb-4 text-sm text-ink-muted">Password lama tidak lagi berlaku dan kunci akun akibat salah password ikut dibuka. Sampaikan password baru langsung kepada pemilik akun.</p>
    <form method="post" action="/pengguna/<?= e($target['id']) ?>/reset-password" class="flex flex-col gap-4" novalidate>
        <?= csrf_field() ?>
        <div class="grid grid-cols-1 gap-4 min-[769px]:grid-cols-2">
            <?= component('field', ['name' => 'password', 'label' => 'Password baru', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'hint' => 'Minimal 10 karakter.']) ?>
            <?= component('field', ['name' => 'password_konfirmasi', 'label' => 'Ulangi password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
        </div>
        <div class="flex gap-2">
            <?= component('button', ['label' => 'Simpan']) ?>
            <?= component('button', ['label' => 'Batal', 'href' => '/pengguna', 'variant' => 'garis']) ?>
        </div>
    </form>
</section>
