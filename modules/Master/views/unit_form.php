<?php

declare(strict_types=1);

/**
 * @var array<string,mixed>|null $item null = tambah baru
 * @var list<array<string,mixed>> $parents tanpa unit ini dan turunannya
 */
$isEdit = $item !== null;
$action = $isEdit ? '/master/unit/' . (int) $item['id'] . '/ubah' : '/master/unit';
$options = [];
foreach ($parents as $p) {
    $options[(int) $p['id']] = str_repeat('— ', (int) $p['depth']) . $p['nama'] . ((int) $p['is_active'] === 1 ? '' : ' (nonaktif)');
}
?>
<div class="mb-6">
    <h1 class="text-lg font-semibold"><?= $isEdit ? 'Ubah Unit Kerja' : 'Tambah Unit Kerja' ?></h1>
</div>
<section class="max-w-2xl rounded-md border border-line bg-surface p-6 shadow-sm">
    <?php if (errors() !== []): ?>
    <p role="alert" class="mb-4 rounded-md border border-danger px-3 py-2 text-sm text-danger">Periksa kembali isian yang ditandai.</p>
    <?php endif ?>
    <form method="post" action="<?= e($action) ?>" class="flex flex-col gap-4" novalidate>
        <?= csrf_field() ?>
        <?= component('field', ['name' => 'nama', 'label' => 'Nama unit', 'required' => true, 'value' => $item['nama'] ?? '', 'hint' => 'Contoh: Bidang Pengembangan Sumber Daya Manusia']) ?>
        <?= component('field', ['name' => 'jabatan_pimpinan', 'label' => 'Jabatan pimpinan', 'value' => $item['jabatan_pimpinan'] ?? '', 'hint' => 'Contoh: Kepala Bidang PSDM']) ?>
        <?= component('field', ['name' => 'parent_id', 'label' => 'Unit induk', 'type' => 'select', 'options' => $options, 'placeholder' => 'Tanpa induk (unit teratas)', 'value' => $item['parent_id'] ?? '']) ?>
        <?= component('field', ['name' => 'urutan', 'label' => 'Urutan tampil', 'type' => 'number', 'value' => $item['urutan'] ?? 0, 'hint' => 'Angka kecil tampil lebih dulu di antara unit sejenjang.']) ?>
        <div class="flex gap-2">
            <?= component('button', ['label' => 'Simpan']) ?>
            <?= component('button', ['label' => 'Batal', 'href' => '/master/unit', 'variant' => 'garis']) ?>
        </div>
    </form>
</section>
