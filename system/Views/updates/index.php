<?php
/**
 * @var array{checked_at: string, manifest: ?array<string, mixed>, error: string, current: string, has_update: bool} $status
 * @var array<string, mixed>|null $last
 */
$m = $status['manifest'];
$card = 'rounded-lg border border-slate-200 bg-white';
$btn = 'rounded-md px-3 py-1.5 text-sm font-medium shadow-sm';
?>
<div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-4">
  <div>
    <h1 class="text-lg font-semibold text-slate-900">Updates</h1>
    <p class="text-xs text-slate-500">New versions come from the GitHub Releases of Omni. Only the code is replaced; the sites, their reports and your account stay as they are.</p>
  </div>
  <form method="post" action="/updates/check">
    <?= csrf_field() ?>
    <button type="submit" class="<?= $btn ?> border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">Check now</button>
  </form>
</div>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
  <section class="<?= $card ?>" data-version>
    <div class="border-b border-slate-200 px-4 py-2.5"><h2 class="text-sm font-semibold text-slate-900">Version</h2></div>
    <dl class="divide-y divide-slate-50 text-sm">
      <div class="flex items-center justify-between gap-3 px-4 py-2"><dt class="text-slate-500">Installed</dt><dd class="text-slate-800"><?= e($status['current']) ?></dd></div>
      <div class="flex items-center justify-between gap-3 px-4 py-2"><dt class="text-slate-500">Latest</dt><dd><?= $m !== null ? badge((string)$m['version'], $status['has_update'] ? 'blue' : 'green', $status['has_update'] ? 'Newer than the one installed' : 'Up to date') : '—' ?></dd></div>
      <div class="flex items-center justify-between gap-3 px-4 py-2"><dt class="text-slate-500">Checked</dt><dd class="text-slate-800"><?= e(local_time($status['checked_at'])) ?></dd></div>
      <?php if ($status['error'] !== ''): ?>
        <div class="px-4 py-2 text-sm text-red-700" role="alert"><?= e($status['error']) ?></div>
      <?php endif; ?>
    </dl>
    <?php if ($last !== null): ?>
      <form method="post" action="/updates/undo" class="border-t border-slate-100 px-4 py-3" data-confirm="Put Omni <?= e((string)$last['from']) ?> back? Only the code goes back; what was reported since stays.">
        <?= csrf_field() ?>
        <p class="mb-2 text-xs text-slate-500">Updated from <?= e((string)$last['from']) ?> to <?= e((string)$last['to']) ?> on <?= e(local_time((string)$last['at'])) ?>.</p>
        <button type="submit" class="<?= $btn ?> border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">Undo this update</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="<?= $card ?> lg:col-span-2" data-release>
    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-2.5">
      <h2 class="text-sm font-semibold text-slate-900"><?= $m !== null ? 'Omni ' . e((string)$m['version']) : 'Latest release' ?></h2>
      <?php if ($status['has_update']): ?>
        <form method="post" action="/updates/install" data-confirm="Install Omni <?= e((string)$m['version']) ?>? It takes a few seconds; if the new version does not work here, <?= e($status['current']) ?> is put back by itself.">
          <?= csrf_field() ?>
          <button type="submit" class="<?= $btn ?> bg-slate-900 font-semibold text-white hover:bg-slate-700">Install <?= e((string)$m['version']) ?></button>
        </form>
      <?php endif; ?>
    </div>
    <div class="px-4 py-3 text-sm text-slate-700">
      <?php if ($m === null): ?>
        <p class="text-slate-500">No release is known yet.</p>
      <?php elseif (trim((string)$m['notes']) === ''): ?>
        <p class="text-slate-500">This release has no notes.</p>
      <?php else: ?>
        <?= release_notes((string)$m['notes']) ?>
      <?php endif; ?>
    </div>
  </section>
</div>
