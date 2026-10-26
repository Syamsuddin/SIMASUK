<?php

declare(strict_types=1);

/**
 * Layout aplikasi: sidebar kiri (laci di bawah 1024px) + topbar (docs/26).
 *
 * @var string $content
 * @var string|null $title
 */
$authUser = \App\Core\View::shared('authUser');
$toast = \App\Core\View::shared('toast');
$isAdmin = is_array($authUser) && $authUser['role'] === 'admin';
$nav = static function (string $href, string $label, string $ikon, bool $active): string {
    $classes = $active
        ? 'bg-primary-soft text-primary'
        : 'text-ink hover:bg-surface';
    return '<a href="' . e($href) . '" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium ' . $classes . '"'
        . ($active ? ' aria-current="page"' : '') . '>' . icon($ikon, 'size-5 shrink-0') . '<span>' . e($label) . '</span></a>';
};
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? '') !== '' ? $title . ' · SIMASUK' : 'SIMASUK') ?></title>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
    <script src="<?= e(asset('app.js')) ?>" defer></script>
</head>
<body class="min-h-screen bg-canvas font-sans text-sm text-ink">
<a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-surface focus:px-3 focus:py-2">Lewati ke konten</a>

<div data-drawer-overlay hidden class="fixed inset-0 z-30 bg-ink/50 lg:hidden"></div>
<aside id="sidebar" data-drawer class="fixed inset-y-0 left-0 z-40 hidden w-60 flex-col border-r border-line bg-surface-muted lg:flex">
    <div class="flex items-center justify-between border-b border-line px-4 py-3">
        <div>
            <p class="text-base font-semibold text-primary">SIMASUK</p>
            <p class="text-xs text-ink-muted">BKPSDM Kab. Hulu Sungai Selatan</p>
        </div>
        <button type="button" data-drawer-close class="cursor-pointer rounded-md p-1 text-ink-muted lg:hidden" aria-label="Tutup menu"><?= icon('x-mark', 'size-5') ?></button>
    </div>
    <nav class="flex flex-1 flex-col gap-1 overflow-y-auto p-3" aria-label="Navigasi utama">
        <?= $nav('/', 'Dasbor', 'home', nav_active('/')) ?>
        <?= $nav('/surat-masuk', 'Surat Masuk', 'inbox', nav_active('/surat-masuk')) ?>
        <?php if ($isAdmin): ?>
        <p class="mt-4 px-3 pb-1 text-xs font-medium text-ink-muted">Pengaturan</p>
        <?= $nav('/pengguna', 'Pengguna', 'users', nav_active('/pengguna')) ?>
        <?= $nav('/master/klasifikasi', 'Klasifikasi Arsip', 'folder', nav_active('/master/klasifikasi')) ?>
        <?= $nav('/master/unit', 'Unit Kerja', 'building-office', nav_active('/master/unit')) ?>
        <?= $nav('/audit', 'Log Audit', 'clipboard-document-list', nav_active('/audit')) ?>
        <?php endif ?>
    </nav>
</aside>

<div class="flex min-h-screen min-w-0 flex-col lg:pl-60">
    <header class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-line bg-surface px-4 py-2 min-[769px]:px-6">
        <button type="button" data-drawer-open aria-controls="sidebar" aria-expanded="false" class="cursor-pointer rounded-md p-1 text-ink lg:hidden" aria-label="Buka menu"><?= icon('bars-3', 'size-6') ?></button>
        <div class="ml-auto flex items-center gap-2">
            <?php if (is_array($authUser)): ?>
            <span class="hidden text-sm font-medium min-[769px]:inline"><?= e($authUser['nama']) ?></span>
            <?= component('button', ['label' => 'Ubah Password', 'href' => '/ubah-password', 'variant' => 'teks', 'icon' => 'key']) ?>
            <form method="post" action="/logout">
                <?= csrf_field() ?>
                <?= component('button', ['label' => 'Keluar', 'variant' => 'garis', 'icon' => 'arrow-right-start-on-rectangle', 'loading' => 'Keluar…']) ?>
            </form>
            <?php endif ?>
        </div>
    </header>
    <main id="konten" class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 min-[769px]:px-6">
        <?= $content ?>
    </main>
</div>

<?php if (is_array($toast)): ?><?= component('toast', ['toast' => $toast]) ?><?php endif ?>
</body>
</html>
