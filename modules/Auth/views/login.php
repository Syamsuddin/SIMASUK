<?php

declare(strict_types=1);

// Halaman login (layout auth). Kesalahan login selalu umum: "Username atau password salah." (docs/21).
$formError = error('_form');
?>
<section class="rounded-md border border-line bg-surface p-6 shadow-sm">
    <h1 class="mb-4 text-lg font-semibold">Masuk</h1>
    <?php if ($formError !== null): ?>
    <div role="alert" class="mb-4 flex items-start gap-2 rounded-md border border-danger px-3 py-2 text-sm text-danger">
        <?= icon('exclamation-triangle', 'size-5 shrink-0') ?>
        <p><?= e($formError) ?></p>
    </div>
    <?php endif ?>
    <form method="post" action="/login" class="flex flex-col gap-4" novalidate>
        <?= csrf_field() ?>
        <?= component('field', ['name' => 'username', 'label' => 'Username', 'required' => true, 'autocomplete' => 'username', 'autofocus' => true]) ?>
        <?= component('field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
        <?= component('button', ['label' => 'Masuk', 'loading' => 'Memeriksa…', 'class' => 'w-full']) ?>
    </form>
</section>
