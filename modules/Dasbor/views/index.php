<?php

declare(strict_types=1);

/**
 * Dasbor sementara F1. Kartu statistik dan tabel kerja menyusul di F4 (docs/03).
 *
 * @var array<string,mixed> $user
 */
$isAdmin = $user['role'] === 'admin';
$peran = $isAdmin ? 'Admin' : 'Operator TU';
?>
<div class="mb-6">
    <h1 class="text-lg font-semibold">Dasbor</h1>
    <p class="text-ink-muted">Selamat datang, <?= e($user['nama']) ?> (<?= e($peran) ?>).</p>
</div>
<div class="grid grid-cols-1 gap-4 min-[769px]:grid-cols-2">
    <?= component('card', [
        'title' => 'Surat masuk',
        'body' => component('empty-state', ['message' => 'Belum ada surat masuk yang dicatat.', 'icon' => 'inbox']),
    ]) ?>
    <?php if ($isAdmin): ?>
    <?php
    $links = '';
    foreach ([
        ['/pengguna', 'Kelola akun Admin dan Operator TU', 'users', 'Pengguna'],
        ['/master/klasifikasi', 'Kode klasifikasi arsip untuk formulir surat', 'folder', 'Klasifikasi Arsip'],
        ['/master/unit', 'Unit tujuan disposisi beserta hierarkinya', 'building-office', 'Unit Kerja'],
    ] as [$href, $desc, $ikon, $label]) {
        $links .= '<li><a href="' . e($href) . '" class="flex items-start gap-3 rounded-md p-2 hover:bg-surface-muted">'
            . '<span class="text-primary">' . icon($ikon, 'size-5') . '</span><span><span class="block font-medium text-primary">'
            . e($label) . '</span><span class="block text-xs text-ink-muted">' . e($desc) . '</span></span></a></li>';
    }
    ?>
    <?= component('card', ['title' => 'Pengaturan', 'body' => '<ul class="flex flex-col gap-1">' . $links . '</ul>']) ?>
    <?php endif ?>
</div>
