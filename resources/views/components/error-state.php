<?php

declare(strict_types=1);

/**
 * State gagal (docs/26, docs/14): ikon + kalimat + aksi (Coba lagi / Kembali).
 *
 * @var string $message
 * @var string|null $errorId
 * @var string|null $actionLabel
 * @var string|null $actionHref
 * @var bool|null $historyBack tautan aksi kembali ke halaman sebelumnya bila riwayat ada
 */
?>
<div role="alert" class="flex flex-col items-center gap-3 px-4 py-8 text-center">
    <span class="text-danger"><?= icon('exclamation-triangle', 'size-6') ?></span>
    <p class="text-sm text-ink"><?= e($message) ?><?php if (isset($errorId)): ?> Kode: <span class="font-semibold"><?= e($errorId) ?></span><?php endif ?></p>
    <div class="flex flex-wrap justify-center gap-2">
        <?php if ($historyBack ?? false): ?>
        <?= component('button', ['label' => 'Coba lagi', 'href' => '/', 'variant' => 'garis', 'attr' => ['data-history-back' => '']]) ?>
        <?php endif ?>
        <?= component('button', ['label' => $actionLabel ?? 'Kembali ke Dasbor', 'href' => $actionHref ?? '/', 'variant' => 'primer']) ?>
    </div>
</div>
