<?php

declare(strict_types=1);

/**
 * Kartu statistik dasbor (docs/26): angka 20px/600 + label kecil. Nada warning dipakai untuk
 * "lewat tenggat" bila angkanya bukan nol.
 *
 * @var string $label
 * @var int $value
 * @var string|null $hint keterangan di bawah angka
 * @var string|null $href tautan ke daftar yang dihitung
 * @var bool|null $warning
 */
$tone = ($warning ?? false) && $value > 0 ? 'text-warning' : 'text-ink';
?>
<section class="flex flex-col gap-1 rounded-md border border-line bg-surface p-4 shadow-sm">
    <h2 class="text-xs font-medium text-ink-muted"><?= e($label) ?></h2>
    <p class="text-lg font-semibold <?= e($tone) ?>"><?= e($value) ?></p>
    <?php if (isset($hint) || isset($href)): ?>
    <p class="text-xs text-ink-muted">
        <?= e($hint ?? '') ?>
        <?php if (isset($href)): ?>
        <a href="<?= e($href) ?>" class="font-medium text-primary hover:underline">Lihat daftar</a>
        <?php endif ?>
    </p>
    <?php endif ?>
</section>
