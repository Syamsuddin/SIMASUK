<?php

declare(strict_types=1);

use Modules\Agenda\Service\SuratMasukService;

/**
 * Daftar surat masuk + filter (docs/26): cari, tahun, status, sifat, rentang tanggal terima.
 *
 * @var array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,filter:array<string,string>,
 *   errors:array<string,string>,years:list<int>} $result
 */
$filter = $result['filter'];
$errs = $result['errors'];
$filterActive = implode('', $filter) !== '';
$statusBadge = ['diterima' => 'info', 'didisposisi' => 'warning', 'selesai' => 'success', 'dibatalkan' => 'danger-garis'];

$fields = component('field', ['name' => 'q', 'label' => 'Cari', 'value' => $filter['q'], 'placeholder' => 'No. agenda, no. surat, asal, perihal'])
    . component('field', ['name' => 'tahun', 'label' => 'Tahun', 'type' => 'select', 'value' => $filter['tahun'], 'options' => array_combine($result['years'], $result['years']) ?: [], 'placeholder' => 'Semua tahun'])
    . component('field', ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'value' => $filter['status'], 'options' => SuratMasukService::STATUS, 'placeholder' => 'Semua status'])
    . component('field', ['name' => 'sifat', 'label' => 'Sifat', 'type' => 'select', 'value' => $filter['sifat'], 'options' => SuratMasukService::SIFAT, 'placeholder' => 'Semua sifat'])
    . component('field', ['name' => 'dari', 'label' => 'Diterima dari', 'type' => 'date', 'value' => $filter['dari'], 'error' => $errs['dari'] ?? null])
    . component('field', ['name' => 'sampai', 'label' => 'Diterima sampai', 'type' => 'date', 'value' => $filter['sampai'], 'error' => $errs['sampai'] ?? null]);

ob_start();
foreach ($result['rows'] as $row):
    $id = (int) $row['id'];
?>
<tr class="hover:bg-surface-muted">
    <td class="px-3 py-2 font-medium whitespace-nowrap<?= $row['sifat'] === 'sangat_segera' ? ' border-l-4 border-accent' : '' ?>">
        <a href="/surat-masuk/<?= e($id) ?>" class="text-primary hover:underline"><?= e(SuratMasukService::nomor($row)) ?></a>
    </td>
    <td class="px-3 py-2 whitespace-nowrap"><?= e(tgl($row['tgl_terima'])) ?></td>
    <td class="px-3 py-2">
        <p class="font-medium"><?= e($row['asal']) ?></p>
        <p class="text-ink-muted"><?= e($row['perihal']) ?></p>
    </td>
    <td class="px-3 py-2">
        <p class="whitespace-nowrap"><?= e($row['no_surat']) ?></p>
        <p class="text-xs text-ink-muted"><?= e(tgl($row['tgl_surat'])) ?></p>
    </td>
    <td class="px-3 py-2"><?= component('sifat', ['sifat' => (string) $row['sifat']]) ?></td>
    <td class="px-3 py-2"><?= component('badge', ['label' => SuratMasukService::STATUS[$row['status']] ?? (string) $row['status'], 'variant' => $statusBadge[$row['status']] ?? 'info']) ?></td>
    <td class="px-3 py-2">
        <div class="flex justify-end">
            <?= component('button', ['label' => 'Buka', 'href' => '/surat-masuk/' . $id, 'variant' => 'teks']) ?>
        </div>
    </td>
</tr>
<?php
endforeach;
$rows = (string) ob_get_clean();
?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <h1 class="text-lg font-semibold">Surat Masuk</h1>
    <?= component('button', ['label' => 'Catat Surat', 'href' => '/surat-masuk/baru', 'icon' => 'plus']) ?>
</div>

<?= component('filter-bar', ['action' => '/surat-masuk', 'target' => 'tabel-surat', 'fields' => $fields, 'active' => $filterActive]) ?>

<?php if ($result['rows'] === []): ?>
<section class="rounded-md border border-line bg-surface shadow-sm">
    <?= $filterActive
        ? component('empty-state', ['message' => 'Tidak ada surat yang cocok dengan filter ini.', 'icon' => 'magnifying-glass', 'actionLabel' => 'Tampilkan semua', 'actionHref' => '/surat-masuk'])
        : component('empty-state', ['message' => 'Belum ada surat masuk yang tercatat.', 'icon' => 'inbox', 'actionLabel' => 'Catat Surat', 'actionHref' => '/surat-masuk/baru']) ?>
</section>
<?php else: ?>
<?= component('table', [
    'id' => 'tabel-surat',
    'caption' => 'Daftar surat masuk',
    'head' => ['No. Agenda', 'Tgl Terima', 'Asal / Perihal', 'No. Surat', 'Sifat', 'Status', 'Aksi'],
    'rows' => $rows,
]) ?>
<?= component('pagination', ['path' => '/surat-masuk', 'query' => $filter, 'page' => $result['page'], 'pages' => $result['pages'], 'total' => $result['total']]) ?>
<?php endif ?>
