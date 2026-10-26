<?php

declare(strict_types=1);

/**
 * Log audit (docs/26): daftar + filter pengguna, aksi, rentang tanggal. Khusus Admin.
 *
 * @var array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,filter:array<string,string>,
 *   errors:array<string,string>,actions:array<string,string>,users:array<int,string>} $result
 */
$filter = $result['filter'];
$errs = $result['errors'];
$filterActive = implode('', $filter) !== '';

$fields = component('field', ['name' => 'user_id', 'label' => 'Pengguna', 'type' => 'select', 'value' => $filter['user_id'], 'options' => $result['users'], 'placeholder' => 'Semua pengguna', 'error' => $errs['user_id'] ?? null])
    . component('field', ['name' => 'aksi', 'label' => 'Aksi', 'type' => 'select', 'value' => $filter['aksi'], 'options' => $result['actions'], 'placeholder' => 'Semua aksi', 'error' => $errs['aksi'] ?? null])
    . component('field', ['name' => 'dari', 'label' => 'Dari tanggal', 'type' => 'date', 'value' => $filter['dari'], 'error' => $errs['dari'] ?? null])
    . component('field', ['name' => 'sampai', 'label' => 'Sampai tanggal', 'type' => 'date', 'value' => $filter['sampai'], 'error' => $errs['sampai'] ?? null]);

ob_start();
foreach ($result['rows'] as $row):
    $entitasId = $row['entitas_id'] === null ? null : (int) $row['entitas_id'];
?>
<tr class="align-top hover:bg-surface-muted">
    <td class="px-3 py-2 whitespace-nowrap"><?= e(tgl_waktu($row['created_at'])) ?></td>
    <td class="px-3 py-2">
        <?php if ($row['user_id'] === null): ?>
        <span class="text-ink-muted">Tanpa pengguna</span>
        <?php else: ?>
        <p class="font-medium"><?= e($row['user_nama']) ?></p>
        <p class="text-xs text-ink-muted"><?= e($row['user_username']) ?></p>
        <?php endif ?>
    </td>
    <td class="px-3 py-2 whitespace-nowrap"><?= e($row['aksi']) ?></td>
    <td class="px-3 py-2 whitespace-nowrap">
        <?php if ($row['entitas'] === 'surat_masuk' && $entitasId !== null): ?>
        <a href="/surat-masuk/<?= e($entitasId) ?>" class="text-primary hover:underline">surat_masuk #<?= e($entitasId) ?></a>
        <?php elseif ($row['entitas'] !== null): ?>
        <?= e($row['entitas']) ?><?= $entitasId !== null ? ' #' . e($entitasId) : '' ?>
        <?php else: ?>
        <span class="text-ink-muted">-</span>
        <?php endif ?>
    </td>
    <td class="px-3 py-2 whitespace-nowrap"><?= e($row['ip'] ?? '') ?></td>
    <td class="px-3 py-2 text-right">
        <?php if ($row['data_tampil'] === ''): ?>
        <span class="text-ink-muted">-</span>
        <?php else: ?>
        <details>
            <summary class="cursor-pointer font-medium text-primary">Lihat</summary>
            <pre class="mt-2 text-left text-xs break-all whitespace-pre-wrap"><?= e($row['data_tampil']) ?></pre>
        </details>
        <?php endif ?>
    </td>
</tr>
<?php
endforeach;
$rows = (string) ob_get_clean();
?>
<div class="mb-6">
    <h1 class="text-lg font-semibold">Log Audit</h1>
    <p class="text-ink-muted">Catatan setiap perubahan data, terbaru lebih dulu. Log tidak dapat diubah atau dihapus.</p>
</div>

<?= component('filter-bar', ['action' => '/audit', 'target' => 'tabel-audit', 'fields' => $fields, 'active' => $filterActive]) ?>

<?php if ($result['rows'] === []): ?>
<section class="rounded-md border border-line bg-surface shadow-sm">
    <?= $filterActive
        ? component('empty-state', ['message' => 'Tidak ada catatan audit yang cocok dengan filter ini.', 'icon' => 'magnifying-glass', 'actionLabel' => 'Tampilkan semua', 'actionHref' => '/audit'])
        : component('empty-state', ['message' => 'Belum ada catatan audit.', 'icon' => 'clipboard-document-list']) ?>
</section>
<?php else: ?>
<?= component('table', [
    'id' => 'tabel-audit',
    'caption' => 'Daftar log audit',
    'head' => ['Waktu', 'Pengguna', 'Aksi', 'Entitas', 'IP', 'Data'],
    'rows' => $rows,
]) ?>
<?= component('pagination', ['path' => '/audit', 'query' => $filter, 'page' => $result['page'], 'pages' => $result['pages'], 'total' => $result['total']]) ?>
<?php endif ?>
