<?php

declare(strict_types=1);

/**
 * Field formulir (docs/26): text | password | date | number | select | textarea | checkbox.
 * Selalu berlabel; pesan error tampil di bawah input. Nilai lama (old()) diutamakan bila ada.
 *
 * @var string $name
 * @var string $label
 * @var string|null $type
 * @var string|int|null $value
 * @var array<int|string,string>|null $options nilai => label (select)
 * @var string|null $placeholder pilihan kosong untuk select / placeholder input
 * @var string|null $hint
 * @var bool|null $required
 * @var string|null $autocomplete
 * @var bool|null $autofocus
 */
$type ??= 'text';
$id = 'f-' . preg_replace('/[^a-z0-9_-]/i', '-', $name);
$isPassword = $type === 'password';
$current = $isPassword ? '' : old($name, (string) ($value ?? ''));
$err = error($name);
$describedBy = trim(($hint ?? null) !== null ? $id . '-hint' : '') . ($err !== null ? ' ' . $id . '-err' : '');
$control = 'w-full rounded-md border bg-surface px-3 text-sm text-ink placeholder:text-ink-muted '
    . ($err !== null ? 'border-danger' : 'border-line');
$common = 'id="' . e($id) . '" name="' . e($name) . '"'
    . (($required ?? false) ? ' required' : '')
    . (($autofocus ?? false) ? ' autofocus' : '')
    . (isset($autocomplete) ? ' autocomplete="' . e($autocomplete) . '"' : '')
    . ($err !== null ? ' aria-invalid="true"' : '')
    . (trim($describedBy) !== '' ? ' aria-describedby="' . e(trim($describedBy)) . '"' : '');
?>
<div class="flex flex-col gap-1">
<?php if ($type === 'checkbox'): ?>
    <label for="<?= e($id) ?>" class="inline-flex items-center gap-2 text-sm text-ink">
        <input type="checkbox" value="1" class="size-4 rounded-md border-line accent-primary" <?= $common ?><?= $current === '1' ? ' checked' : '' ?>>
        <span><?= e($label) ?></span>
    </label>
<?php else: ?>
    <label for="<?= e($id) ?>" class="text-xs font-medium text-ink-muted"><?= e($label) ?><?php if ($required ?? false): ?> <span class="text-danger" aria-hidden="true">*</span><?php endif ?></label>
    <?php if ($type === 'select'): ?>
    <select class="h-9 <?= e($control) ?>" <?= $common ?>>
        <?php if (isset($placeholder)): ?><option value=""><?= e($placeholder) ?></option><?php endif ?>
        <?php foreach ($options ?? [] as $optValue => $optLabel): ?>
        <option value="<?= e($optValue) ?>"<?= (string) $optValue === $current ? ' selected' : '' ?>><?= e($optLabel) ?></option>
        <?php endforeach ?>
    </select>
    <?php elseif ($type === 'textarea'): ?>
    <textarea rows="3" class="py-2 <?= e($control) ?>" <?= $common ?>><?= e($current) ?></textarea>
    <?php else: ?>
    <input type="<?= e($type) ?>" value="<?= e($current) ?>" class="h-9 <?= e($control) ?>" <?= $common ?><?= isset($placeholder) ? ' placeholder="' . e($placeholder) . '"' : '' ?>>
    <?php endif ?>
<?php endif ?>
<?php if (isset($hint)): ?>
    <p id="<?= e($id) ?>-hint" class="text-xs text-ink-muted"><?= e($hint) ?></p>
<?php endif ?>
<?php if ($err !== null): ?>
    <p id="<?= e($id) ?>-err" class="text-xs font-medium text-danger"><?= e($err) ?></p>
<?php endif ?>
</div>
