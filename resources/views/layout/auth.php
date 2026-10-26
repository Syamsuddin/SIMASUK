<?php

declare(strict_types=1);

/**
 * Layout tanpa sidebar: halaman login dan halaman kesalahan (docs/26).
 *
 * @var string $content
 * @var string|null $title
 */
$toast = \App\Core\View::shared('toast');
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
<main class="flex min-h-screen items-center justify-center px-4 py-8">
    <div class="w-full max-w-md">
        <div class="mb-6 text-center">
            <p class="text-lg font-semibold text-primary">SIMASUK</p>
            <p class="text-xs text-ink-muted">Agenda dan Disposisi Surat Masuk · BKPSDM Kab. Hulu Sungai Selatan</p>
        </div>
        <?= $content ?>
    </div>
</main>
<?php if (is_array($toast)): ?><?= component('toast', ['toast' => $toast]) ?><?php endif ?>
</body>
</html>
