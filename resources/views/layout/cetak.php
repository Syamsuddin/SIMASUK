<?php

declare(strict_types=1);

/**
 * Layout halaman cetak: tanpa sidebar/topbar; isi berupa lembar A4 (docs/26 Halaman cetak).
 *
 * @var string $content
 * @var string|null $title
 */
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
<body class="min-h-screen bg-canvas font-sans text-sm text-ink print:bg-surface">
<main class="mx-auto w-full max-w-7xl px-4 py-6 min-[769px]:px-6 print:max-w-none print:p-0">
    <?= $content ?>
</main>
</body>
</html>
