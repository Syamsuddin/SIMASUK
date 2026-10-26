<?php

declare(strict_types=1);

use Modules\Agenda\Service\SuratMasukService;
use Modules\Disposisi\Service\DisposisiService;

/**
 * Formulir catat disposisi dari lembar kertas pimpinan; dengan $induk menjadi Disposisi Lanjutan (docs/06 P2).
 *
 * @var string $judul
 * @var array<string,mixed> $surat
 * @var array<string,mixed>|null $induk
 * @var list<array<string,mixed>> $units unit tujuan aktif, urutan pohon dengan kunci depth
 * @var bool $isAdmin
 */
$suratId = (int) $surat['id'];
$cancelHref = '/surat-masuk/' . $suratId;
$tujuanErr = error('tujuan');
?>
<div class="mb-6">
    <h1 class="text-lg font-semibold"><?= e($judul) ?></h1>
    <p class="text-xs text-ink-muted">Surat <?= e(SuratMasukService::nomor($surat)) ?> · diterima <?= e(tgl($surat['tgl_terima'])) ?></p>
</div>
<section class="max-w-4xl rounded-md border border-line bg-surface p-6 shadow-sm">
    <dl class="mb-4 grid grid-cols-1 gap-x-6 gap-y-3 min-[769px]:grid-cols-2">
        <div>
            <dt class="text-xs font-medium text-ink-muted">Perihal</dt>
            <dd class="text-sm"><?= e($surat['perihal']) ?></dd>
        </div>
        <div>
            <dt class="text-xs font-medium text-ink-muted">Asal surat</dt>
            <dd class="text-sm"><?= e($surat['asal']) ?></dd>
        </div>
    </dl>
    <?php if ($induk !== null): ?>
    <div class="mb-4 rounded-md border border-line bg-surface-muted px-3 py-2 text-sm">
        <p class="text-xs font-medium text-ink-muted">Lanjutan dari disposisi ke <?= e($induk['unit_nama']) ?></p>
        <p><?= e($induk['instruksi']) ?></p>
    </div>
    <?php endif ?>
    <?php if (errors() !== []): ?>
    <p role="alert" class="mb-4 rounded-md border border-danger px-3 py-2 text-sm text-danger">Periksa kembali isian yang ditandai.<?= error('parent_id') !== null ? ' ' . e(error('parent_id')) : '' ?></p>
    <?php endif ?>

    <?php if ($units === []): ?>
    <?= component('empty-state', $isAdmin
        ? ['message' => 'Belum ada unit tujuan aktif. Tambahkan unit sebelum mencatat disposisi.', 'icon' => 'building-office', 'actionLabel' => 'Tambah Unit', 'actionHref' => '/master/unit/baru']
        : ['message' => 'Belum ada unit tujuan aktif. Hubungi Admin untuk menambahkan unit.', 'icon' => 'building-office']) ?>
    <div class="flex justify-center">
        <?= component('button', ['label' => 'Kembali', 'href' => $cancelHref, 'variant' => 'garis']) ?>
    </div>
    <?php else: ?>
    <form method="post" action="/surat-masuk/<?= e($suratId) ?>/disposisi" class="flex flex-col gap-4" novalidate>
        <?= csrf_field() ?>
        <?php if ($induk !== null): ?>
        <input type="hidden" name="parent_id" value="<?= e((int) $induk['id']) ?>">
        <?php endif ?>
        <div class="grid grid-cols-1 gap-4 min-[769px]:grid-cols-2">
            <?= component('field', ['name' => 'tgl_disposisi', 'label' => 'Tanggal disposisi', 'type' => 'date', 'required' => true, 'value' => date('Y-m-d'), 'hint' => 'Tanggal pada lembar disposisi']) ?>
            <?= component('field', ['name' => 'tenggat', 'label' => 'Tenggat', 'type' => 'date', 'hint' => 'Opsional; tidak boleh sebelum ' . tgl($surat['tgl_terima'])]) ?>
        </div>
        <fieldset class="flex flex-col gap-1"<?= $tujuanErr !== null ? ' aria-describedby="f-tujuan-err"' : '' ?>>
            <legend class="text-xs font-medium text-ink-muted">Unit tujuan <span class="text-danger" aria-hidden="true">*</span></legend>
            <p class="text-xs text-ink-muted">Pilih satu atau lebih; setiap unit mendapat satu disposisi.</p>
            <div class="flex flex-col gap-2 rounded-md border px-3 py-2 <?= $tujuanErr !== null ? 'border-danger' : 'border-line' ?>">
                <?php foreach ($units as $unit): ?>
                <div class="flex items-start">
                    <?= str_repeat('<span class="inline-block w-6 shrink-0" aria-hidden="true"></span>', (int) $unit['depth']) ?>
                    <?= component('field', [
                        'name' => DisposisiService::PREFIX_TUJUAN . (int) $unit['id'],
                        'label' => $unit['nama'] . ($unit['jabatan_pimpinan'] !== null ? ' — ' . $unit['jabatan_pimpinan'] : ''),
                        'type' => 'checkbox',
                    ]) ?>
                </div>
                <?php endforeach ?>
            </div>
            <?php if ($tujuanErr !== null): ?>
            <p id="f-tujuan-err" class="text-xs font-medium text-danger"><?= e($tujuanErr) ?></p>
            <?php endif ?>
        </fieldset>
        <?= component('field', ['name' => 'instruksi', 'label' => 'Instruksi', 'type' => 'textarea', 'required' => true, 'hint' => 'Salin dari lembar disposisi pimpinan']) ?>
        <?= component('field', ['name' => 'catatan', 'label' => 'Catatan', 'type' => 'textarea']) ?>
        <div class="flex gap-2">
            <?= component('button', ['label' => 'Simpan']) ?>
            <?= component('button', ['label' => 'Batal', 'href' => $cancelHref, 'variant' => 'garis']) ?>
        </div>
    </form>
    <?php endif ?>
</section>
