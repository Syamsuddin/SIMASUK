<?php

declare(strict_types=1);

use Modules\Agenda\Service\SuratMasukService;
use Modules\Dasbor\Service\DasborService;

/**
 * Dasbor (docs/26): 3 kartu statistik + tabel "Belum didisposisi" + tabel "Lewat tenggat".
 *
 * @var array<string,mixed> $user
 * @var array{hari:string,bulan_ini:int,belum:array{rows:list<array<string,mixed>>,total:int},
 *   lewat:array{rows:list<array<string,mixed>>,total:int},batas:int} $ringkasan
 */
$peran = $user['role'] === 'admin' ? 'Admin' : 'Operator TU';
$hari = $ringkasan['hari'];
$belum = $ringkasan['belum'];
$lewat = $ringkasan['lewat'];
$sisa = static fn (array $tabel): string => $tabel['total'] > count($tabel['rows'])
    ? '<p class="mt-2 text-xs text-ink-muted">Menampilkan ' . e(count($tabel['rows'])) . ' dari ' . e($tabel['total']) . ' data, yang terlama lebih dulu.</p>'
    : '';

ob_start();
foreach ($belum['rows'] as $row):
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
    <td class="px-3 py-2"><?= component('sifat', ['sifat' => (string) $row['sifat']]) ?></td>
    <td class="px-3 py-2">
        <div class="flex justify-end">
            <?= component('button', ['label' => 'Catat Disposisi', 'href' => '/surat-masuk/' . $id . '/disposisi/baru', 'variant' => 'teks']) ?>
        </div>
    </td>
</tr>
<?php
endforeach;
$rowsBelum = (string) ob_get_clean();

ob_start();
foreach ($lewat['rows'] as $row):
    $suratId = (int) $row['surat_id'];
    $hariLewat = DasborService::hariLewat((string) $row['tenggat'], $hari);
?>
<tr class="hover:bg-surface-muted">
    <td class="px-3 py-2 whitespace-nowrap<?= $row['sifat'] === 'sangat_segera' ? ' border-l-4 border-accent' : '' ?>">
        <a href="/surat-masuk/<?= e($suratId) ?>" class="font-medium text-primary hover:underline"><?= e(SuratMasukService::nomor($row)) ?></a>
    </td>
    <td class="px-3 py-2">
        <p class="font-medium"><?= e($row['unit_nama']) ?></p>
        <p class="text-ink-muted"><?= e($row['perihal']) ?></p>
    </td>
    <td class="px-3 py-2"><?= e($row['instruksi']) ?></td>
    <td class="px-3 py-2 whitespace-nowrap">
        <p><?= e(tgl($row['tenggat'])) ?></p>
        <p class="text-xs font-medium text-warning">Lewat <?= e($hariLewat) ?> hari</p>
    </td>
    <td class="px-3 py-2">
        <div class="flex justify-end">
            <?= component('button', ['label' => 'Buka', 'href' => '/surat-masuk/' . $suratId, 'variant' => 'teks']) ?>
        </div>
    </td>
</tr>
<?php
endforeach;
$rowsLewat = (string) ob_get_clean();
?>
<div class="mb-6">
    <h1 class="text-lg font-semibold">Dasbor</h1>
    <p class="text-ink-muted">Selamat datang, <?= e($user['nama']) ?> (<?= e($peran) ?>).</p>
</div>

<div class="mb-6 grid grid-cols-1 gap-4 min-[769px]:grid-cols-3">
    <?= component('stat', [
        'label' => 'Surat masuk bulan ini',
        'value' => $ringkasan['bulan_ini'],
        'hint' => 'Diterima ' . tgl(substr($hari, 0, 7) . '-01') . ' s.d. ' . tgl($hari) . ', tanpa yang dibatalkan.',
    ]) ?>
    <?= component('stat', [
        'label' => 'Belum didisposisi',
        'value' => $belum['total'],
        'href' => '/surat-masuk?status=diterima',
    ]) ?>
    <?= component('stat', [
        'label' => 'Disposisi lewat tenggat',
        'value' => $lewat['total'],
        'hint' => 'Tenggat sebelum ' . tgl($hari) . ' dan belum selesai.',
        'warning' => true,
    ]) ?>
</div>

<section class="mb-6" aria-labelledby="judul-belum">
    <h2 id="judul-belum" class="mb-2 text-base font-semibold">Belum didisposisi</h2>
    <?php if ($belum['rows'] === []): ?>
    <div class="rounded-md border border-line bg-surface shadow-sm">
        <?= component('empty-state', ['message' => 'Tidak ada surat yang menunggu disposisi.', 'icon' => 'check-circle', 'actionLabel' => 'Catat Surat', 'actionHref' => '/surat-masuk/baru']) ?>
    </div>
    <?php else: ?>
    <?= component('table', [
        'id' => 'tabel-belum',
        'caption' => 'Surat yang belum didisposisi',
        'head' => ['No. Agenda', 'Tgl Terima', 'Asal / Perihal', 'Sifat', 'Aksi'],
        'rows' => $rowsBelum,
    ]) ?>
    <?= $sisa($belum) ?>
    <?php endif ?>
</section>

<section aria-labelledby="judul-lewat">
    <h2 id="judul-lewat" class="mb-2 text-base font-semibold">Lewat tenggat</h2>
    <?php if ($lewat['rows'] === []): ?>
    <div class="rounded-md border border-line bg-surface shadow-sm">
        <?= component('empty-state', ['message' => 'Tidak ada disposisi yang lewat tenggat.', 'icon' => 'check-circle']) ?>
    </div>
    <?php else: ?>
    <?= component('table', [
        'id' => 'tabel-lewat',
        'caption' => 'Disposisi yang lewat tenggat',
        'head' => ['No. Agenda', 'Unit Tujuan / Perihal', 'Instruksi', 'Tenggat', 'Aksi'],
        'rows' => $rowsLewat,
    ]) ?>
    <?= $sisa($lewat) ?>
    <?php endif ?>
</section>
