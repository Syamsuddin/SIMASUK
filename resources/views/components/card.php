<?php

declare(strict_types=1);

/**
 * Kartu (docs/26), dengan atau tanpa judul H2. $body adalah HTML yang sudah di-escape pemanggil.
 *
 * @var string $body
 * @var string|null $title
 * @var string|null $class
 */
?>
<section class="rounded-md border border-line bg-surface shadow-sm <?= e($class ?? '') ?>">
    <?php if (isset($title)): ?>
    <header class="border-b border-line px-4 py-3">
        <h2 class="text-base font-semibold"><?= e($title) ?></h2>
    </header>
    <?php endif ?>
    <div class="p-4"><?= $body ?></div>
</section>
