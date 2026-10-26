<?php

declare(strict_types=1);

use Modules\Disposisi\Service\DisposisiService;

/**
 * Pohon disposisi (docs/26): indentasi 24px per jenjang dengan garis kiri `line`; lanjutan dirender rekursif.
 *
 * @var list<array<string,mixed>> $nodes simpul dengan kunci 'children'
 * @var bool $terbuka aksi Disposisi Lanjutan / Tandai Selesai hanya tampil selama surat belum selesai/dibatalkan
 */
$today = date('Y-m-d');
?>
<ul class="flex flex-col gap-3">
    <?php foreach ($nodes as $node):
        $nodeId = (int) $node['id'];
        $dikirim = $node['status'] === 'dikirim';
        $lewat = $dikirim && $node['tenggat'] !== null && $node['tenggat'] < $today;
    ?>
    <li>
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="flex min-w-0 flex-col gap-1">
                <p class="text-sm">
                    <span class="font-medium"><?= e($node['unit_nama']) ?></span>
                    <?php if ($node['unit_jabatan'] !== null): ?><span class="text-xs text-ink-muted">· <?= e($node['unit_jabatan']) ?></span><?php endif ?>
                </p>
                <p class="text-sm"><?= e($node['instruksi']) ?></p>
                <p class="text-xs text-ink-muted">
                    Disposisi <?= e(tgl($node['tgl_disposisi'])) ?>
                    <?php if ($node['tenggat'] !== null): ?>
                    · <span class="<?= $lewat ? 'font-medium text-warning' : '' ?>">Tenggat <?= e(tgl($node['tenggat'])) ?><?= $lewat ? ' (lewat tenggat)' : '' ?></span>
                    <?php endif ?>
                </p>
                <?php if ($node['catatan'] !== null): ?>
                <p class="text-xs text-ink-muted">Catatan: <?= e($node['catatan']) ?></p>
                <?php endif ?>
                <?php if (!$dikirim): ?>
                <p class="text-xs text-ink-muted">Selesai <?= e(tgl_waktu($node['selesai_at'])) ?><?= $node['catatan_selesai'] !== null ? ' — ' . e($node['catatan_selesai']) : '' ?></p>
                <?php endif ?>
            </div>
            <div class="flex flex-wrap items-center gap-1">
                <?= component('badge', ['label' => DisposisiService::STATUS[$node['status']] ?? (string) $node['status'], 'variant' => $dikirim ? 'info' : 'success']) ?>
                <?php if ($terbuka): ?>
                <?= component('button', ['label' => 'Disposisi Lanjutan', 'href' => '/surat-masuk/' . (int) $node['surat_id'] . '/disposisi/baru?induk=' . $nodeId, 'variant' => 'teks']) ?>
                <?php if ($dikirim): ?>
                <?= component('button', ['label' => 'Tandai Selesai', 'variant' => 'teks', 'type' => 'button', 'attr' => ['data-dialog-open' => 'selesai-dispo-' . $nodeId]]) ?>
                <?= component('confirm-dialog', [
                    'id' => 'selesai-dispo-' . $nodeId,
                    'title' => 'Tandai disposisi ke ' . $node['unit_nama'] . ' selesai?',
                    'uraian' => 'Status disposisi menjadi Selesai dan tidak bisa dikembalikan.',
                    'action' => '/disposisi/' . $nodeId . '/selesai',
                    'confirmLabel' => 'Tandai Selesai',
                    'reasonName' => 'catatan_selesai',
                    'reasonLabel' => 'Catatan tindak lanjut',
                    'reasonRequired' => false,
                    'reasonMax' => 500,
                ]) ?>
                <?php endif ?>
                <?php endif ?>
            </div>
        </div>
        <?php if ($node['children'] !== []): ?>
        <div class="mt-3 ml-6 border-l border-line pl-3">
            <?= component('dispo-tree', ['nodes' => $node['children'], 'terbuka' => $terbuka]) ?>
        </div>
        <?php endif ?>
    </li>
    <?php endforeach ?>
</ul>
