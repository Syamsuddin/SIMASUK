<?php

declare(strict_types=1);

/**
 * Kerangka memuat (docs/26): blok garis berdenyut, dirender sebagai baris tabel.
 *
 * @var int $cols
 * @var int|null $rows
 */
?>
<?php for ($r = 0; $r < ($rows ?? 5); $r++): ?>
<tr aria-hidden="true">
    <?php for ($c = 0; $c < $cols; $c++): ?>
    <td class="px-3 py-2"><div class="h-4 animate-pulse rounded-md bg-line"></div></td>
    <?php endfor ?>
</tr>
<?php endfor ?>
