<?php

declare(strict_types=1);

/**
 * Toast (docs/26): sukses | bahaya | info; hilang 5 detik (app.js); satu per aksi.
 *
 * @var array{type:string,message:string} $toast
 */
[$classes, $ikon] = match ($toast['type']) {
    'success' => ['bg-success', 'check-circle'],
    'danger' => ['bg-danger', 'exclamation-triangle'],
    default => ['bg-info', 'information-circle'],
};
?>
<div data-toast role="<?= $toast['type'] === 'danger' ? 'alert' : 'status' ?>" class="fixed top-4 right-4 left-4 z-50 ml-auto flex max-w-md items-start gap-3 rounded-md px-4 py-3 text-sm text-on-primary shadow-sm <?= e($classes) ?>">
    <?= icon($ikon, 'size-5 shrink-0') ?>
    <p class="flex-1"><?= e($toast['message']) ?></p>
    <button type="button" data-toast-close class="shrink-0 cursor-pointer rounded-md" aria-label="Tutup pemberitahuan"><?= icon('x-mark', 'size-5') ?></button>
</div>
