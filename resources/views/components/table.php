<?php

declare(strict_types=1);

/**
 * Tabel data (docs/26): header primer, baris hover, kolom aksi kanan. Bergulir horizontal di
 * dalam kartunya pada layar sempit. $rows adalah HTML <tr> yang sudah di-escape pemanggil.
 * Template kerangka memuat dipakai app.js saat filter dikirim.
 *
 * @var string $id
 * @var list<string> $head
 * @var string $rows
 * @var string|null $caption
 */
$cols = count($head);
?>
<div class="overflow-x-auto rounded-md border border-line bg-surface shadow-sm">
    <table class="w-full border-collapse text-left text-sm">
        <?php if (isset($caption)): ?><caption class="sr-only"><?= e($caption) ?></caption><?php endif ?>
        <thead class="bg-primary text-on-primary">
            <tr>
                <?php foreach ($head as $i => $label): ?>
                <th scope="col" class="px-3 py-2 text-xs font-medium whitespace-nowrap<?= $i === $cols - 1 ? ' text-right' : '' ?>"><?= e($label) ?></th>
                <?php endforeach ?>
            </tr>
        </thead>
        <tbody id="<?= e($id) ?>-body" class="divide-y divide-line">
            <?= $rows ?>
        </tbody>
    </table>
    <template id="<?= e($id) ?>-skeleton"><?= component('skeleton', ['cols' => $cols, 'rows' => 5]) ?></template>
</div>
