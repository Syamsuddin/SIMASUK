<?php

declare(strict_types=1);

/**
 * Halaman kesalahan 419 (docs/14). Tidak pernah menampilkan detail teknis.
 *
 * @var string $pesan
 * @var string|null $errorId
 */
?>
<section class="rounded-md border border-line bg-surface shadow-sm">
    <?= component('error-state', [
        'message' => $pesan,
        'errorId' => $errorId ?? null,
        'historyBack' => true,
    ]) ?>
</section>
