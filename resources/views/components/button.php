<?php

declare(strict_types=1);

/**
 * Tombol (docs/26): varian primer | garis | bahaya | teks. Dengan $href menjadi tautan.
 * Tombol submit menampilkan spinner + $loading saat formulir dikirim (app.js).
 *
 * @var string $label
 * @var string|null $variant
 * @var string|null $href
 * @var string|null $type
 * @var string|null $icon
 * @var string|null $loading
 * @var string|null $class
 * @var array<string,string>|null $attr
 */
$variant ??= 'primer';
$type ??= 'submit';
$classes = 'inline-flex h-9 items-center justify-center gap-2 rounded-md px-3 text-sm font-medium whitespace-nowrap cursor-pointer disabled:cursor-wait ' . match ($variant) {
    'garis' => 'border border-line bg-surface text-ink hover:bg-surface-muted',
    'bahaya' => 'bg-danger text-on-primary',
    'teks' => 'px-2 text-primary hover:underline',
    default => 'bg-primary text-on-primary hover:bg-primary-hover',
} . ' ' . ($class ?? '');
$attrHtml = '';
foreach ($attr ?? [] as $name => $value) {
    $attrHtml .= ' ' . e($name) . '="' . e($value) . '"';
}
?>
<?php if (isset($href)): ?>
<a href="<?= e($href) ?>" class="<?= e($classes) ?>"<?= $attrHtml ?>><?php if (isset($icon)): ?><?= icon($icon, 'size-5 shrink-0') ?><?php endif ?><span><?= e($label) ?></span></a>
<?php else: ?>
<button type="<?= e($type) ?>" class="<?= e($classes) ?>"<?php if ($type === 'submit'): ?> data-loading-label="<?= e($loading ?? 'Menyimpan…') ?>"<?php endif ?><?= $attrHtml ?>>
    <span data-spinner hidden class="size-4 shrink-0 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
    <?php if (isset($icon)): ?><span data-icon><?= icon($icon, 'size-5 shrink-0') ?></span><?php endif ?>
    <span data-label><?= e($label) ?></span>
</button>
<?php endif ?>
