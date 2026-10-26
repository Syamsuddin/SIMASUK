<?php

declare(strict_types=1);

/**
 * Daftar pengguna + filter (docs/26). Empat state: kosong, memuat (kerangka/tombol), gagal (errors/*), sukses (toast).
 *
 * @var array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,filter:array<string,string>} $result
 * @var array<string,string> $peran
 * @var int $currentUserId
 */
$filter = $result['filter'];
$filterActive = implode('', $filter) !== '';
$now = date('Y-m-d H:i:s');

$fields = component('field', ['name' => 'q', 'label' => 'Cari', 'value' => $filter['q'], 'placeholder' => 'Username atau nama'])
    . component('field', ['name' => 'role', 'label' => 'Peran', 'type' => 'select', 'value' => $filter['role'], 'options' => $peran, 'placeholder' => 'Semua peran'])
    . component('field', ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'value' => $filter['status'], 'options' => ['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'], 'placeholder' => 'Semua status']);

ob_start();
foreach ($result['rows'] as $row):
    $id = (int) $row['id'];
    $self = $id === $currentUserId;
    $locked = $row['locked_until'] !== null && $row['locked_until'] > $now;
?>
<tr class="hover:bg-surface-muted">
    <td class="px-3 py-2 font-medium"><?= e($row['nama']) ?><?php if ($self): ?> <span class="text-xs text-ink-muted">(Anda)</span><?php endif ?></td>
    <td class="px-3 py-2"><?= e($row['username']) ?></td>
    <td class="px-3 py-2"><?= e($peran[$row['role']] ?? $row['role']) ?></td>
    <td class="px-3 py-2">
        <div class="flex flex-wrap gap-1">
            <?= (int) $row['is_active'] === 1
                ? component('badge', ['label' => 'Aktif', 'variant' => 'success'])
                : component('badge', ['label' => 'Nonaktif', 'variant' => 'danger-garis']) ?>
            <?php if ($locked): ?><?= component('badge', ['label' => 'Terkunci', 'variant' => 'warning']) ?><?php endif ?>
        </div>
    </td>
    <td class="px-3 py-2 whitespace-nowrap text-ink-muted"><?= $row['last_login_at'] !== null ? e(tgl_waktu($row['last_login_at'])) : 'Belum pernah' ?></td>
    <td class="px-3 py-2">
        <div class="flex justify-end gap-1">
            <?= component('button', ['label' => 'Reset Password', 'href' => '/pengguna/' . $id . '/reset-password', 'variant' => 'teks']) ?>
            <?php if ((int) $row['is_active'] === 1 && !$self): ?>
            <?= component('button', ['label' => 'Nonaktifkan', 'variant' => 'teks', 'type' => 'button', 'class' => 'text-danger', 'attr' => ['data-dialog-open' => 'nonaktif-' . $id]]) ?>
            <?= component('confirm-dialog', [
                'id' => 'nonaktif-' . $id,
                'title' => 'Nonaktifkan akun ' . $row['username'] . '?',
                'uraian' => 'Pengguna ini tidak bisa login sampai akunnya diaktifkan kembali. Data yang pernah dicatatnya tetap tersimpan.',
                'action' => '/pengguna/' . $id . '/nonaktifkan',
                'confirmLabel' => 'Nonaktifkan',
            ]) ?>
            <?php elseif ((int) $row['is_active'] !== 1): ?>
            <form method="post" action="/pengguna/<?= e($id) ?>/aktifkan">
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
    <h1 class="text-lg font-semibold">Pengguna</h1>
    <?= component('button', ['label' => 'Tambah Pengguna', 'href' => '/pengguna/baru', 'icon' => 'plus']) ?>
</div>

<?= component('filter-bar', ['action' => '/pengguna', 'target' => 'tabel-pengguna', 'fields' => $fields, 'active' => $filterActive]) ?>

<?php if ($result['rows'] === []): ?>
<section class="rounded-md border border-line bg-surface shadow-sm">
    <?= $filterActive
        ? component('empty-state', ['message' => 'Tidak ada pengguna yang cocok dengan filter ini.', 'icon' => 'magnifying-glass', 'actionLabel' => 'Tampilkan semua', 'actionHref' => '/pengguna'])
        : component('empty-state', ['message' => 'Belum ada akun pengguna.', 'icon' => 'users', 'actionLabel' => 'Tambah Pengguna', 'actionHref' => '/pengguna/baru']) ?>
</section>
<?php else: ?>
<?= component('table', [
    'id' => 'tabel-pengguna',
    'caption' => 'Daftar pengguna',
    'head' => ['Nama', 'Username', 'Peran', 'Status', 'Login terakhir', 'Aksi'],
    'rows' => $rows,
]) ?>
<?= component('pagination', ['path' => '/pengguna', 'query' => $filter, 'page' => $result['page'], 'pages' => $result['pages'], 'total' => $result['total']]) ?>
<?php endif ?>
