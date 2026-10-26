<?php

declare(strict_types=1);

/**
 * Penanda sifat surat (docs/26): Rahasia = badge bahaya; Sangat Segera = titik aksen (baris daftarnya
 * diberi garis kiri aksen oleh pemanggil); Segera = teks warning; Biasa = teks redup. Selalu berteks.
 *
 * @var string $sifat biasa | segera | sangat_segera | rahasia
 */
?>
<?php if ($sifat === 'rahasia'): ?>
<?= component('badge', ['label' => 'Rahasia', 'variant' => 'danger']) ?>
<?php elseif ($sifat === 'sangat_segera'): ?>
<span class="inline-flex items-center gap-1 text-xs font-medium whitespace-nowrap text-ink"><span class="size-2 rounded-full bg-accent" aria-hidden="true"></span>Sangat Segera</span>
<?php elseif ($sifat === 'segera'): ?>
<span class="text-xs font-medium text-warning">Segera</span>
<?php else: ?>
<span class="text-xs text-ink-muted">Biasa</span>
<?php endif ?>
