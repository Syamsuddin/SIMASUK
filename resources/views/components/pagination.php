<?php

declare(strict_types=1);

/**
 * Paginasi 25 per halaman (docs/26).
 *
 * @var string $path
 * @var array<string,string> $query filter aktif
 * @var int $page
 * @var int $pages
 * @var int $total
 */
$link = 'inline-flex h-9 items-center rounded-md border border-line bg-surface px-3 text-sm font-medium text-ink hover:bg-surface-muted';
?>
<nav class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-ink-muted" aria-label="Paginasi">
    <p>Halaman <?= e($page) ?> dari <?= e($pages) ?> · <?= e($total) ?> data</p>
    <div class="flex gap-2">
        <?php if ($page > 1): ?>
        <a class="<?= e($link) ?>" href="<?= e(url($path, [...$query, 'page' => $page - 1])) ?>">Sebelumnya</a>
        <?php endif ?>
        <?php if ($page < $pages): ?>
        <a class="<?= e($link) ?>" href="<?= e(url($path, [...$query, 'page' => $page + 1])) ?>">Berikutnya</a>
        <?php endif ?>
    </div>
</nav>
