<?php

declare(strict_types=1);

/**
 * Bilah filter GET untuk halaman daftar. Saat dikirim, tabel $target menampilkan kerangka memuat.
 *
 * @var string $action
 * @var string $target id tabel
 * @var string $fields HTML field yang sudah di-escape pemanggil
 * @var bool $active ada filter terpasang
 */
?>
<form method="get" action="<?= e($action) ?>" data-skeleton="<?= e($target) ?>" class="mb-4 flex flex-col gap-3 rounded-md border border-line bg-surface p-4 shadow-sm min-[769px]:flex-row min-[769px]:items-end">
    <div class="grid flex-1 grid-cols-1 gap-3 min-[769px]:grid-cols-3"><?= $fields ?></div>
    <div class="flex gap-2">
        <?= component('button', ['label' => 'Cari', 'icon' => 'magnifying-glass', 'loading' => 'Mencari…']) ?>
        <?php if ($active): ?>
        <?= component('button', ['label' => 'Tampilkan semua', 'href' => $action, 'variant' => 'teks']) ?>
        <?php endif ?>
    </div>
</form>
