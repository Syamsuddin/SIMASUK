<?php

declare(strict_types=1);

use Modules\Agenda\Service\SuratMasukService;
use Modules\Disposisi\Service\DisposisiService;

/**
 * Detail surat: metadata, berkas, pohon disposisi, aksi (docs/26).
 *
 * @var array<string,mixed> $surat
 * @var list<array<string,mixed>> $berkas
 * @var list<array<string,mixed>> $disposisi pohon dari DisposisiService::tree()
 * @var bool $isAdmin
 */
$id = (int) $surat['id'];
$nomor = SuratMasukService::nomor($surat);
$batal = $surat['status'] === 'dibatalkan';
$terbuka = DisposisiService::terbuka($surat);
$dikirim = DisposisiService::jumlahDikirim($disposisi);
$statusBadge = ['diterima' => 'info', 'didisposisi' => 'warning', 'selesai' => 'success', 'dibatalkan' => 'danger-garis'];
$ukuran = static fn (int $byte): string => $byte >= 1048576
    ? number_format($byte / 1048576, 1, ',', '.') . ' MB'
    : number_format(max(1, (int) round($byte / 1024)), 0, ',', '.') . ' KB';
$meta = [
    'Tanggal terima' => tgl($surat['tgl_terima']),
    'Nomor surat' => $surat['no_surat'],
    'Tanggal surat' => tgl($surat['tgl_surat']),
    'Asal surat' => $surat['asal'],
    'Kode klasifikasi' => $surat['klasifikasi_kode'] . ' — ' . $surat['klasifikasi_uraian'],
    'Nomor SRIKANDI' => $surat['no_srikandi'] ?? '—',
    'Jumlah lampiran' => $surat['jumlah_lampiran'] ?? '—',
    'Dicatat' => tgl_waktu($surat['created_at']) . (($surat['created_by_nama'] ?? null) !== null ? ' oleh ' . $surat['created_by_nama'] : ''),
];
?>
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div class="flex flex-col gap-1">
        <h1 class="text-lg font-semibold">Surat <?= e($nomor) ?></h1>
        <div class="flex flex-wrap items-center gap-2">
            <?= component('badge', ['label' => SuratMasukService::STATUS[$surat['status']] ?? (string) $surat['status'], 'variant' => $statusBadge[$surat['status']] ?? 'info']) ?>
            <?= component('sifat', ['sifat' => (string) $surat['sifat']]) ?>
        </div>
    </div>
    <div class="flex flex-wrap gap-2">
        <?= component('button', ['label' => 'Kembali', 'href' => '/surat-masuk', 'variant' => 'garis']) ?>
        <?php if ($terbuka): ?>
        <?= component('button', ['label' => 'Catat Disposisi', 'href' => '/surat-masuk/' . $id . '/disposisi/baru', 'icon' => 'plus']) ?>
        <?= component('button', ['label' => 'Cetak Lembar Disposisi', 'href' => '/surat-masuk/' . $id . '/lembar-disposisi', 'variant' => 'garis', 'icon' => 'printer']) ?>
        <?= component('button', ['label' => 'Tandai Selesai', 'variant' => 'garis', 'type' => 'button', 'icon' => 'check-circle', 'attr' => ['data-dialog-open' => 'selesai-surat']]) ?>
        <?= component('confirm-dialog', [
            'id' => 'selesai-surat',
            'title' => 'Tandai surat ' . $nomor . ' selesai?',
            'uraian' => 'Status surat menjadi Selesai dan disposisi tidak dapat dicatat lagi.'
                . ($dikirim > 0 ? ' ' . $dikirim . ' disposisi yang masih Dikirim ikut ditutup dengan catatan "' . DisposisiService::CATATAN_TUTUP_SURAT . '".' : ''),
            'action' => '/surat-masuk/' . $id . '/selesai',
            'confirmLabel' => 'Tandai Selesai',
        ]) ?>
        <?php endif ?>
        <?php if (!$batal): ?>
        <?= component('button', ['label' => 'Ubah', 'href' => '/surat-masuk/' . $id . '/ubah', 'variant' => 'garis', 'icon' => 'pencil-square']) ?>
        <?php if ($isAdmin): ?>
        <?= component('button', ['label' => 'Batalkan Surat', 'variant' => 'bahaya', 'type' => 'button', 'icon' => 'no-symbol', 'attr' => ['data-dialog-open' => 'batal-surat']]) ?>
        <?= component('confirm-dialog', [
            'id' => 'batal-surat',
            'title' => 'Batalkan surat ' . $nomor . '?',
            'uraian' => 'Status surat menjadi Dibatalkan dan tidak bisa dikembalikan. Nomor agenda ' . $nomor . ' tidak dipakai ulang; surat tetap tampil di daftar dengan filter Dibatalkan.',
            'action' => '/surat-masuk/' . $id . '/batalkan',
            'confirmLabel' => 'Batalkan Surat',
            'reasonName' => 'alasan',
            'reasonLabel' => 'Alasan pembatalan',
        ]) ?>
        <?php endif ?>
        <?php endif ?>
    </div>
</div>

<?php if (error('alasan') !== null): ?>
<p role="alert" class="mb-4 rounded-md border border-danger bg-surface px-3 py-2 text-sm text-danger">Surat belum dibatalkan. Alasan pembatalan: <?= e(error('alasan')) ?></p>
<?php endif ?>
<?php if (error('catatan_selesai') !== null): ?>
<p role="alert" class="mb-4 rounded-md border border-danger bg-surface px-3 py-2 text-sm text-danger">Disposisi belum ditandai selesai. Catatan tindak lanjut: <?= e(error('catatan_selesai')) ?></p>
<?php endif ?>
<?php if ($batal): ?>
<div role="status" class="mb-4 rounded-md border border-danger bg-surface px-3 py-2 text-sm">
    <p class="font-medium text-danger">Dibatalkan <?= e(tgl_waktu($surat['cancelled_at'])) ?></p>
    <p class="text-ink-muted"><?= e($surat['cancel_reason']) ?></p>
</div>
<?php endif ?>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <section class="rounded-md border border-line bg-surface shadow-sm lg:col-span-2">
        <header class="border-b border-line px-4 py-3">
            <h2 class="text-base font-semibold">Perihal</h2>
            <p class="mt-1 text-sm"><?= e($surat['perihal']) ?></p>
        </header>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 p-4 min-[769px]:grid-cols-2">
            <?php foreach ($meta as $label => $value): ?>
            <div>
                <dt class="text-xs font-medium text-ink-muted"><?= e($label) ?></dt>
                <dd class="text-sm"><?= e($value) ?></dd>
            </div>
            <?php endforeach ?>
        </dl>
    </section>

    <section class="rounded-md border border-line bg-surface shadow-sm">
        <header class="border-b border-line px-4 py-3">
            <h2 class="text-base font-semibold">Berkas scan</h2>
        </header>
        <?php if ($berkas === []): ?>
        <?= component('empty-state', ['message' => 'Belum ada berkas aktif pada surat ini.', 'icon' => 'inbox']) ?>
        <?php else: ?>
        <ul class="divide-y divide-line">
            <?php foreach ($berkas as $file): ?>
            <li class="flex items-center justify-between gap-3 px-4 py-2">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium" title="<?= e($file['nama_asli']) ?>"><?= e($file['nama_asli']) ?></p>
                    <p class="text-xs text-ink-muted"><?= e($ukuran((int) $file['ukuran'])) ?></p>
                </div>
                <?= component('button', ['label' => 'Unduh', 'href' => '/berkas/' . (int) $file['id'], 'variant' => 'teks']) ?>
            </li>
            <?php endforeach ?>
        </ul>
        <?php endif ?>
    </section>
</div>

<section class="mt-6 rounded-md border border-line bg-surface shadow-sm">
    <header class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
        <h2 class="text-base font-semibold">Disposisi</h2>
        <?php if ($disposisi !== [] && $terbuka): ?>
        <?= component('button', ['label' => 'Catat Disposisi', 'href' => '/surat-masuk/' . $id . '/disposisi/baru', 'variant' => 'teks']) ?>
        <?php endif ?>
    </header>
    <?php if ($disposisi === []): ?>
    <?= component('empty-state', $terbuka
        ? ['message' => 'Belum ada disposisi untuk surat ini.', 'icon' => 'inbox', 'actionLabel' => 'Catat Disposisi', 'actionHref' => '/surat-masuk/' . $id . '/disposisi/baru']
        : ['message' => 'Surat ini ' . ($batal ? 'dibatalkan' : 'selesai') . ' tanpa disposisi.', 'icon' => 'inbox']) ?>
    <?php else: ?>
    <div class="p-4">
        <?= component('dispo-tree', ['nodes' => $disposisi, 'terbuka' => $terbuka]) ?>
    </div>
    <?php endif ?>
</section>
