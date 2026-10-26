<?php

declare(strict_types=1);

/**
 * Badge berteks (docs/26): info | warning | success | danger | danger-garis.
 *
 * @var string $label
 * @var string|null $variant
 */
$classes = match ($variant ?? 'info') {
    'warning' => 'bg-warning text-on-primary',
    'success' => 'bg-success text-on-primary',
    'danger' => 'bg-danger text-on-primary',
    'danger-garis' => 'border border-danger bg-surface text-danger',
    default => 'bg-info text-on-primary',
};
?>
<span class="inline-flex items-center rounded-full px-2 text-xs font-medium whitespace-nowrap <?= e($classes) ?>"><?= e($label) ?></span>
