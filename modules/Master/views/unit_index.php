<?php

declare(strict_types=1);

/**
 * Daftar unit kerja berindentasi hierarki, 24px per jenjang (docs/26).
 *
 * @var list<array<string,mixed>> $units urutan pohon dengan kunci depth
 */
$names = array_column($units, 'nama', 'id');

ob_start();
foreach ($units as $row):
    $id = (int) $row['id'];
    $active = (int) $row['is_active'] === 1;
    $depth = (int) $row['depth'];
?>
<tr class="hover:bg-surface-muted">
    <td class="px-3 py-2">
        <div class="flex items-start">
            <?= str_repeat('<span class="inline-block w-6 shrink-0" aria-hidden="true"></span>', $depth) ?>
            <div class="<?= $depth > 0 ? 'border-l border-line pl-3' : '' ?>">
                <p class="font-medium"><?= e($row['nama']) ?></p>
                <?php if ($row['parent_id'] !== null && isset($names[$row['parent_id']])): ?>
                <p class="text-xs text-ink-muted">Di bawah <?= e($names[$row['parent_id']]) ?></p>
                <?php endif ?>
            </div>
        </div>
    </td>
    <td class="px-3 py-2"><?= $row['jabatan_pimpinan'] !== null ? e($row['jabatan_pimpinan']) : '<span class="text-ink-muted">Tidak diisi</span>' ?></td>
    <td class="px-3 py-2"><?= e($row['urutan']) ?></td>
    <td class="px-3 py-2"><?= $active
        ? component('badge', ['label' => 'Aktif', 'variant' => 'success'])
        : component('badge', ['label' => 'Nonaktif', 'variant' => 'danger-garis']) ?></td>
    <td class="px-3 py-2">
        <div class="flex justify-end gap-1">
            <?= component('button', ['label' => 'Ubah', 'href' => '/master/unit/' . $id . '/ubah', 'variant' => 'teks']) ?>
            <?php if ($active): ?>
            <?= component('button', ['label' => 'Nonaktifkan', 'variant' => 'teks', 'type' => 'button', 'class' => 'text-danger', 'attr' => ['data-dialog-open' => 'nonaktif-' . $id]]) ?>
            <?= component('confirm-dialog', [
                'id' => 'nonaktif-' . $id,
                'title' => 'Nonaktifkan unit ' . $row['nama'] . '?',
                'uraian' => 'Unit ini tidak lagi muncul di pilihan tujuan disposisi. Disposisi lama tetap menampilkan nama unit ini.',
                'action' => '/master/unit/' . $id . '/nonaktifkan',
                'confirmLabel' => 'Nonaktifkan',
            ]) ?>
            <?php else: ?>
            <form method="post" action="/master/unit/<?= e($id) ?>/aktifkan">
                <?= csrf_field() ?>
                <?= component('button', ['label' => 'Aktifkan', 'variant' => 'teks', 'loading' => 'Memproses…']) ?>
            </form>
            <?php endif ?>
        </div>
    </td>
</tr>
<?php
endforeach;
$rows = (string) ob_get_clean();
?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <h1 class="text-lg font-semibold">Unit Kerja</h1>
    <?= component('button', ['label' => 'Tambah Unit', 'href' => '/master/unit/baru', 'icon' => 'plus']) ?>
</div>

<?php if ($units === []): ?>
<section class="rounded-md border border-line bg-surface shadow-sm">
    <?= component('empty-state', ['message' => 'Belum ada unit kerja. Tambahkan unit agar bisa dipilih sebagai tujuan disposisi.', 'icon' => 'building-office', 'actionLabel' => 'Tambah Unit', 'actionHref' => '/master/unit/baru']) ?>
</section>
<?php else: ?>
<?= component('table', [
    'id' => 'tabel-unit',
    'caption' => 'Daftar unit kerja',
    'head' => ['Nama unit', 'Jabatan pimpinan', 'Urutan', 'Status', 'Aksi'],
    'rows' => $rows,
]) ?>
<?php endif ?>
