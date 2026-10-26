<?php

declare(strict_types=1);

use Modules\Agenda\Service\SuratMasukService;

/**
 * Lembar disposisi cetak A4 untuk ditulisi pimpinan (docs/26 Halaman cetak, docs/06 P2 langkah 1).
 * Garis memakai ink karena line terlalu pucat di kertas.
 *
 * @var array<string,mixed> $surat
 * @var list<array<string,mixed>> $units dari UnitKerjaService::pilihanTujuan()
 * @var list<string> $kop
 * @var string $dicetak
 */
$id = (int) $surat['id'];
$sel = 'border border-ink px-3 py-2 align-top';
$label = $sel . ' w-1/5 text-left font-medium';
$isian = $sel . ' break-words';
$barisKosong = $units === [] ? 5 : 2;
$instruksi = ['Tanggapan dan saran', 'Proses lebih lanjut', 'Koordinasi/konfirmasikan'];
?>
<div class="mb-4 flex flex-wrap items-center justify-between gap-4 print:hidden">
    <p class="text-xs text-ink-muted">Pilih "Simpan sebagai PDF" pada dialog cetak untuk membuat berkas PDF.</p>
    <div class="flex flex-wrap gap-2">
        <?= component('button', ['label' => 'Kembali', 'href' => '/surat-masuk/' . $id, 'variant' => 'garis']) ?>
        <?= component('button', ['label' => 'Cetak', 'type' => 'button', 'icon' => 'printer', 'attr' => ['data-print' => '']]) ?>
    </div>
</div>

<article class="rounded-md border border-line bg-surface p-6 shadow-sm print:rounded-none print:border-0 print:p-0 print:shadow-none">
    <header class="border-b border-ink pb-3 text-center">
        <?php foreach ($kop as $baris): ?>
        <p class="text-base font-semibold"><?= e($baris) ?></p>
        <?php endforeach ?>
    </header>
    <h1 class="my-4 text-center text-base font-semibold underline">LEMBAR DISPOSISI</h1>

    <table class="w-full table-fixed border-collapse">
        <tbody>
            <tr>
                <th scope="row" class="<?= e($label) ?>">Surat dari</th>
                <td class="<?= e($isian) ?>" colspan="3"><?= e($surat['asal']) ?></td>
            </tr>
            <tr>
                <th scope="row" class="<?= e($label) ?>">Nomor surat</th>
                <td class="<?= e($isian) ?>"><?= e($surat['no_surat']) ?></td>
                <th scope="row" class="<?= e($label) ?>">Diterima tanggal</th>
                <td class="<?= e($isian) ?>"><?= e(tgl($surat['tgl_terima'])) ?></td>
            </tr>
            <tr>
                <th scope="row" class="<?= e($label) ?>">Tanggal surat</th>
                <td class="<?= e($isian) ?>"><?= e(tgl($surat['tgl_surat'])) ?></td>
                <th scope="row" class="<?= e($label) ?>">Nomor agenda</th>
                <td class="<?= e($isian) ?> font-semibold"><?= e(SuratMasukService::nomor($surat)) ?></td>
            </tr>
            <tr>
                <th scope="row" class="<?= e($label) ?>">Kode klasifikasi</th>
                <td class="<?= e($isian) ?>"><?= e($surat['klasifikasi_kode'] . ' — ' . $surat['klasifikasi_uraian']) ?></td>
                <th scope="row" class="<?= e($label) ?>">Sifat</th>
                <td class="<?= e($isian) ?>"><?= e(SuratMasukService::SIFAT[$surat['sifat']] ?? (string) $surat['sifat']) ?></td>
            </tr>
            <tr>
                <th scope="row" class="<?= e($label) ?>">Perihal</th>
                <td class="<?= e($isian) ?>" colspan="3"><?= e($surat['perihal']) ?></td>
            </tr>
        </tbody>
    </table>

    <div class="grid grid-cols-5 border border-t-0 border-ink">
        <section class="col-span-3 flex flex-col gap-2 border-r border-ink p-3">
            <h2 class="font-semibold">Diteruskan kepada Sdr.:</h2>
            <?php if ($units === []): ?>
            <p class="text-xs text-ink-muted print:hidden">Belum ada unit tujuan aktif; tulis tujuan secara manual.</p>
            <?php endif ?>
            <ul class="flex flex-col gap-2">
                <?php foreach ($units as $unit): ?>
                <li class="flex items-center gap-2">
                    <?= str_repeat('<span class="inline-block w-6 shrink-0" aria-hidden="true"></span>', (int) $unit['depth']) ?>
                    <span class="inline-block size-4 shrink-0 border border-ink" aria-hidden="true"></span>
                    <span><?= e($unit['jabatan_pimpinan'] ?? $unit['nama']) ?></span>
                </li>
                <?php endforeach ?>
                <?php for ($i = 0; $i < $barisKosong; $i++): ?>
                <li class="flex items-end gap-2"><span class="inline-block size-4 shrink-0 border border-ink" aria-hidden="true"></span><span class="h-5 flex-1 border-b border-dotted border-ink" aria-hidden="true"></span></li>
                <?php endfor ?>
            </ul>
        </section>
        <section class="col-span-2 flex flex-col gap-2 p-3">
            <h2 class="font-semibold">Dengan hormat harap:</h2>
            <ul class="flex flex-col gap-2">
                <?php foreach ($instruksi as $teks): ?>
                <li class="flex items-center gap-2"><span class="inline-block size-4 shrink-0 border border-ink" aria-hidden="true"></span><span><?= e($teks) ?></span></li>
                <?php endforeach ?>
                <li class="flex items-end gap-2"><span class="inline-block size-4 shrink-0 border border-ink" aria-hidden="true"></span><span class="h-5 flex-1 border-b border-dotted border-ink" aria-hidden="true"></span></li>
            </ul>
            <p class="mt-4 flex items-end gap-2"><span class="font-semibold">Batas waktu:</span><span class="h-5 flex-1 border-b border-dotted border-ink" aria-hidden="true"></span></p>
            <p class="mt-auto flex items-end gap-2 pt-6"><span>Tanggal</span><span class="h-5 flex-1 border-b border-dotted border-ink" aria-hidden="true"></span></p>
            <p class="flex items-end gap-2 pt-6"><span>Paraf</span><span class="h-5 flex-1 border-b border-dotted border-ink" aria-hidden="true"></span></p>
        </section>
    </div>

    <section class="flex flex-col gap-2 border border-t-0 border-ink p-3">
        <h2 class="font-semibold">Catatan:</h2>
        <?php for ($i = 0; $i < 3; $i++): ?>
        <p class="flex pt-2"><span class="h-5 flex-1 border-b border-dotted border-ink" aria-hidden="true"></span></p>
        <?php endfor ?>
    </section>

    <p class="mt-6 text-xs text-ink-muted">Dicetak dari SIMASUK · <?= e($dicetak) ?></p>
</article>
