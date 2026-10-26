<?php

declare(strict_types=1);

/**
 * State kosong (docs/26): ikon outline 24px + kalimat spesifik + aksi bila peran boleh.
 *
 * @var string $message
 * @var string|null $icon
 * @var string|null $actionLabel
 * @var string|null $actionHref
 */
?>
<div class="flex flex-col items-center gap-3 px-4 py-8 text-center text-ink-muted">
    <?= icon($icon ?? 'inbox', 'size-6') ?>
    <p class="text-sm"><?= e($message) ?></p>
    <?php if (isset($actionLabel, $actionHref)): ?>
    <?= component('button', ['label' => $actionLabel, 'href' => $actionHref, 'variant' => 'garis']) ?>
    <?php endif ?>
</div>
