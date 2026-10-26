<?php

declare(strict_types=1);

/**
 * Dialog konfirmasi aksi destruktif (docs/26). Dibuka tombol ber-data-dialog-open="$id";
 * Esc menutup dan fokus kembali ke pemicu (app.js). Opsional field alasan wajib ($reasonName).
 *
 * @var string $id
 * @var string $title
 * @var string $uraian dampak aksi
 * @var string $action URL POST
 * @var string $confirmLabel
 * @var string|null $reasonName nama field alasan wajib
 * @var string|null $reasonLabel
 */
?>
<dialog id="<?= e($id) ?>" aria-labelledby="<?= e($id) ?>-judul" class="m-auto w-full max-w-md rounded-md border border-line bg-surface p-0 text-ink shadow-sm">
    <form method="post" action="<?= e($action) ?>" class="flex flex-col gap-4 p-6">
        <?= csrf_field() ?>
        <h2 id="<?= e($id) ?>-judul" class="text-base font-semibold"><?= e($title) ?></h2>
        <p class="text-sm text-ink-muted"><?= e($uraian) ?></p>
        <?php if (isset($reasonName)): ?>
        <div class="flex flex-col gap-1">
            <label for="<?= e($id) ?>-alasan" class="text-xs font-medium text-ink-muted"><?= e($reasonLabel ?? 'Alasan') ?> <span class="text-danger" aria-hidden="true">*</span></label>
            <textarea id="<?= e($id) ?>-alasan" name="<?= e($reasonName) ?>" rows="3" maxlength="255" required class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink"></textarea>
        </div>
        <?php endif ?>
        <div class="flex justify-end gap-2">
            <?= component('button', ['label' => 'Batal', 'variant' => 'garis', 'type' => 'button', 'attr' => ['data-dialog-close' => '']]) ?>
            <?= component('button', ['label' => $confirmLabel, 'variant' => 'bahaya', 'loading' => 'Memproses…']) ?>
        </div>
    </form>
</dialog>
