<?php

declare(strict_types=1);

use Modules\Agenda\Service\SuratMasukService;

/**
 * Formulir catat / ubah surat (docs/06 P1). Berkas hanya diunggah saat mencatat; nomor agenda tidak bisa diubah.
 *
 * @var array<string,mixed>|null $item null = surat baru
 * @var array<int,string> $klasifikasi
 * @var list<array<string,mixed>> $duplikat surat mirip saat peringatan dugaan ganda
 * @var int $maxMb
 */
$isEdit = $item !== null;
$action = $isEdit ? '/surat-masuk/' . (int) $item['id'] . '/ubah' : '/surat-masuk';
$cancelHref = $isEdit ? '/surat-masuk/' . (int) $item['id'] : '/surat-masuk';
$hasDuplicate = error('tetap_simpan') !== null;
?>
<div class="mb-6">
    <h1 class="text-lg font-semibold"><?= e($isEdit ? 'Ubah Surat ' . SuratMasukService::nomor($item) : 'Catat Surat') ?></h1>
    <?php if ($isEdit): ?>
    <p class="text-xs text-ink-muted">Nomor agenda dan tahun agenda tidak dapat diubah.</p>
    <?php endif ?>
</div>
<section class="max-w-4xl rounded-md border border-line bg-surface p-6 shadow-sm">
    <?php if (errors() !== [] && !(count(errors()) === 1 && $hasDuplicate)): ?>
    <p role="alert" class="mb-4 rounded-md border border-danger px-3 py-2 text-sm text-danger">Periksa kembali isian yang ditandai.<?= $isEdit ? '' : ' Berkas PDF perlu dipilih ulang.' ?></p>
    <?php endif ?>
    <?php if ($hasDuplicate): ?>
    <div role="alert" class="mb-4 rounded-md border border-warning px-3 py-2 text-sm">
        <p class="font-medium text-warning">Dugaan surat ganda</p>
        <?php if (!$isEdit): ?>
        <p class="text-ink-muted">Bila memang surat berbeda, pilih ulang berkas PDF lalu centang "Tetap simpan".</p>
        <?php endif ?>
        <?php if ($duplikat !== []): ?>
        <ul class="mt-1 flex flex-col gap-1">
            <?php foreach ($duplikat as $dup): ?>
            <li><a href="/surat-masuk/<?= e((int) $dup['id']) ?>" class="text-primary hover:underline"><?= e(SuratMasukService::nomor($dup)) ?></a> — <?= e($dup['perihal']) ?></li>
            <?php endforeach ?>
        </ul>
        <?php endif ?>
    </div>
    <?php endif ?>
    <form method="post" action="<?= e($action) ?>" class="flex flex-col gap-4" novalidate<?= $isEdit ? '' : ' enctype="multipart/form-data"' ?>>
        <?= csrf_field() ?>
        <div class="grid grid-cols-1 gap-4 min-[769px]:grid-cols-2">
            <?= component('field', ['name' => 'tgl_terima', 'label' => 'Tanggal terima', 'type' => 'date', 'required' => true, 'value' => $item['tgl_terima'] ?? date('Y-m-d'), 'autofocus' => !$isEdit]) ?>
            <?= component('field', ['name' => 'sifat', 'label' => 'Sifat', 'type' => 'select', 'required' => true, 'value' => $item['sifat'] ?? 'biasa', 'options' => SuratMasukService::SIFAT]) ?>
            <?= component('field', ['name' => 'no_surat', 'label' => 'Nomor surat', 'required' => true, 'value' => $item['no_surat'] ?? '']) ?>
            <?= component('field', ['name' => 'tgl_surat', 'label' => 'Tanggal surat', 'type' => 'date', 'required' => true, 'value' => $item['tgl_surat'] ?? '']) ?>
            <?= component('field', ['name' => 'asal', 'label' => 'Asal surat', 'required' => true, 'value' => $item['asal'] ?? '', 'hint' => 'Instansi atau pengirim surat']) ?>
            <?= component('field', ['name' => 'klasifikasi_id', 'label' => 'Kode klasifikasi', 'type' => 'select', 'required' => true, 'value' => $item['klasifikasi_id'] ?? '', 'options' => $klasifikasi, 'placeholder' => 'Pilih kode klasifikasi']) ?>
        </div>
        <?= component('field', ['name' => 'perihal', 'label' => 'Perihal', 'type' => 'textarea', 'required' => true, 'value' => $item['perihal'] ?? '']) ?>
        <div class="grid grid-cols-1 gap-4 min-[769px]:grid-cols-2">
            <?= component('field', ['name' => 'no_srikandi', 'label' => 'Nomor SRIKANDI', 'value' => $item['no_srikandi'] ?? '', 'hint' => 'Opsional, hanya rujukan']) ?>
            <?= component('field', ['name' => 'jumlah_lampiran', 'label' => 'Jumlah lampiran', 'value' => $item['jumlah_lampiran'] ?? '', 'placeholder' => 'Contoh: 1 berkas']) ?>
        </div>
        <?php if (!$isEdit): ?>
        <?= component('field', ['name' => 'berkas', 'label' => 'Berkas scan', 'type' => 'file', 'multiple' => true, 'required' => true, 'hint' => 'PDF, 1–' . \App\Core\FileStore::MAX_FILES . ' berkas, maks ' . $maxMb . ' MB per berkas.']) ?>
        <?php endif ?>
        <?php if ($hasDuplicate): ?>
        <?= component('field', ['name' => 'tetap_simpan', 'label' => 'Tetap simpan', 'type' => 'checkbox']) ?>
        <?php endif ?>
        <div class="flex gap-2">
            <?= component('button', ['label' => 'Simpan']) ?>
            <?= component('button', ['label' => 'Batal', 'href' => $cancelHref, 'variant' => 'garis']) ?>
        </div>
    </form>
</section>
