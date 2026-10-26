<?php

declare(strict_types=1);

/** @var array<string,mixed>|null $item null = tambah baru */
$isEdit = $item !== null;
$action = $isEdit ? '/master/klasifikasi/' . (int) $item['id'] . '/ubah' : '/master/klasifikasi';
?>
<div class="mb-6">
    <h1 class="text-lg font-semibold"><?= $isEdit ? 'Ubah Klasifikasi' : 'Tambah Klasifikasi' ?></h1>
</div>
<section class="max-w-2xl rounded-md border border-line bg-surface p-6 shadow-sm">
    <?php if (errors() !== []): ?>
    <p role="alert" class="mb-4 rounded-md border border-danger px-3 py-2 text-sm text-danger">Periksa kembali isian yang ditandai.</p>
    <?php endif ?>
    <form method="post" action="<?= e($action) ?>" class="flex flex-col gap-4" novalidate>
        <?= csrf_field() ?>
        <?= component('field', ['name' => 'kode', 'label' => 'Kode', 'required' => true, 'value' => $item['kode'] ?? '', 'hint' => 'Contoh: 800.1.2']) ?>
        <?= component('field', ['name' => 'uraian', 'label' => 'Uraian', 'type' => 'textarea', 'required' => true, 'value' => $item['uraian'] ?? '']) ?>
        <div class="flex gap-2">
            <?= component('button', ['label' => 'Simpan']) ?>
            <?= component('button', ['label' => 'Batal', 'href' => '/master/klasifikasi', 'variant' => 'garis']) ?>
        </div>
    </form>
</section>
