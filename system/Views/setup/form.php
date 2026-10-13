<?php
/** @var array<string, string> $values @var string $error @var array<int, string> $timezones */
use Omni\Controllers\AuthController;
use Omni\Core\View;
?>
<p class="mb-4 text-sm text-slate-500">Make the owner's account. It is the only one Omni has, and this page closes once it exists.</p>
<?php if ($error !== ''): ?>
  <p class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"><?= e($error) ?></p>
<?php endif; ?>
<form method="post" action="/setup" class="space-y-3">
  <?= csrf_field() ?>
  <?= View::fetch('partials/field', ['name' => 'name', 'label' => 'Your name', 'value' => $values['name'], 'autocomplete' => 'name']) ?>
  <?= View::fetch('partials/field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $values['email'], 'autocomplete' => 'email']) ?>
  <?= View::fetch('partials/field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'new-password', 'hint' => 'At least ' . AuthController::MIN_PASSWORD . ' characters.']) ?>
  <?= View::fetch('partials/field', ['name' => 'password_confirm', 'label' => 'Password again', 'type' => 'password', 'autocomplete' => 'new-password']) ?>
  <label class="block">
    <span class="mb-1 block text-xs font-medium text-slate-600">Time zone</span>
    <select name="timezone" class="block w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
      <?php foreach ($timezones as $zone): ?>
        <option value="<?= e($zone) ?>"<?= $zone === $values['timezone'] ? ' selected' : '' ?>><?= e($zone) ?></option>
      <?php endforeach; ?>
    </select>
    <span class="mt-1 block text-xs text-slate-400">Times are shown in it.</span>
  </label>
  <button type="submit" class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-700">Create account</button>
</form>
