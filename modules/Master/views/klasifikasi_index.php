<?php

declare(strict_types=1);

/**
 * Daftar kode klasifikasi arsip + filter (docs/26).
 *
 * @var array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,filter:array<string,string>} $result
 */
$filter = $result['filter'];
$filterActive = implode('', $filter) !== '';

$fields = component('field', ['name' => 'q', 'label' => 'Cari', 'value' => $filter['q'], 'placeholder' => 'Kode atau uraian'])
    . component('field', ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'value' => $filter['status'], 'options' => ['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'], 'placeholder' => 'Semua status']);

ob_start();
foreach ($result['rows'] as $row):
    $id = (int) $row['id'];
    $active = (int) $row['is_active'] === 1;
?>
<tr class="hover:bg-surface-muted">
    <td class="px-3 py-2 font-medium whitespace-nowrap"><?= e($row['kode']) ?></td>
    <td class="px-3 py-2"><?= e($row['uraian']) ?></td>
    <td class="px-3 py-2"><?= $active
        ? component('badge', ['label' => 'Aktif', 'variant' => 'success'])
        : component('badge', ['label' => 'Nonaktif', 'variant' => 'danger-garis']) ?></td>
    <td class="px-3 py-2">
        <div class="flex justify-end gap-1">
            <?= component('button', ['label' => 'Ubah', 'href' => '/master/klasifikasi/' . $id . '/ubah', 'variant' => 'teks']) ?>
            <?php if ($active): ?>
            <?= component('button', ['label' => 'Nonaktifkan', 'variant' => 'teks', 'type' => 'button', 'class' => 'text-danger', 'attr' => ['data-dialog-open' => 'nonaktif-' . $id]]) ?>
            <?= component('confirm-dialog', [
                'id' => 'nonaktif-' . $id,
                'title' => 'Nonaktifkan klasifikasi ' . $row['kode'] . '?',
                'uraian' => 'Kode ini tidak lagi muncul di pilihan formulir surat. Surat yang sudah memakai kode ini tidak berubah.',
                'action' => '/master/klasifikasi/' . $id . '/nonaktifkan',
                'confirmLabel' => 'Nonaktifkan',
            ]) ?>
            <?php else: ?>
            <form method="post" action="/master/klasifikasi/<?= e($id) ?>/aktifkan">
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
    <h1 class="text-lg font-semibold">Klasifikasi Arsip</h1>
    <?= component('button', ['label' => 'Tambah Klasifikasi', 'href' => '/master/klasifikasi/baru', 'icon' => 'plus']) ?>
</div>

<?= component('filter-bar', ['action' => '/master/klasifikasi', 'target' => 'tabel-klasifikasi', 'fields' => $fields, 'active' => $filterActive]) ?>

<?php if ($result['rows'] === []): ?>
<section class="rounded-md border border-line bg-surface shadow-sm">
    <?= $filterActive
        ? component('empty-state', ['message' => 'Tidak ada kode klasifikasi yang cocok dengan filter ini.', 'icon' => 'magnifying-glass', 'actionLabel' => 'Tampilkan semua', 'actionHref' => '/master/klasifikasi'])
        : component('empty-state', ['message' => 'Belum ada kode klasifikasi arsip.', 'icon' => 'folder', 'actionLabel' => 'Tambah Klasifikasi', 'actionHref' => '/master/klasifikasi/baru']) ?>
</section>
<?php else: ?>
<?= component('table', [
    'id' => 'tabel-klasifikasi',
    'caption' => 'Daftar kode klasifikasi arsip',
    'head' => ['Kode', 'Uraian', 'Status', 'Aksi'],
    'rows' => $rows,
]) ?>
<?= component('pagination', ['path' => '/master/klasifikasi', 'query' => $filter, 'page' => $result['page'], 'pages' => $result['pages'], 'total' => $result['total']]) ?>
<?php endif ?>
