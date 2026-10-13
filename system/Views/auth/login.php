<?php
/** @var string $email @var string $error */
use Omni\Core\View;
?>
<?php if ($error !== ''): ?>
  <p class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"><?= e($error) ?></p>
<?php endif; ?>
<form method="post" action="/login" class="space-y-3">
  <?= csrf_field() ?>
  <?= View::fetch('partials/field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $email, 'autocomplete' => 'username']) ?>
  <?= View::fetch('partials/field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'current-password']) ?>
  <button type="submit" class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-700">Sign in</button>
</form>
