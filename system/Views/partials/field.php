<?php
/**
 * A labelled input.
 * @var string $name @var string $label @var string $type @var string $value @var string $autocomplete @var string $hint
 */
$type ??= 'text';
$value ??= '';
$autocomplete ??= 'off';
$hint ??= '';
?>
<label class="block">
  <span class="mb-1 block text-xs font-medium text-slate-600"><?= e($label) ?></span>
  <input type="<?= e($type) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>" autocomplete="<?= e($autocomplete) ?>" required
         class="block w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
  <?php if ($hint !== ''): ?><span class="mt-1 block text-xs text-slate-400"><?= e($hint) ?></span><?php endif; ?>
</label>
