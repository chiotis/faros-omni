<?php
/** @var string $content @var string $title @var array<string, mixed> $owner */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="en" class="h-full bg-slate-50">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · Omni</title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="icon" href="/assets/img/omni.svg" type="image/svg+xml">
</head>
<body class="min-h-full text-slate-800 antialiased"<?php if (is_array($flash)): ?> data-flash-message="<?= e($flash['message']) ?>" data-flash-type="<?= e($flash['type']) ?>"<?php endif; ?>>
  <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
    <div class="mx-auto flex h-12 max-w-7xl items-center justify-between gap-3 px-4">
      <a href="/" class="flex items-center gap-2 text-sm font-semibold text-slate-900">
        <img src="/assets/img/omni.svg" alt="" class="h-5 w-5">
        Omni
      </a>
      <div class="flex items-center gap-3 text-sm">
        <a href="/updates" class="relative rounded-md px-2 py-1 text-slate-600 hover:bg-slate-100 hover:text-slate-900"<?= ($update_available ?? null) !== null ? ' title="Omni ' . e($update_available) . ' is available"' : '' ?>>
          Updates<?php if (($update_available ?? null) !== null): ?><span class="absolute right-0.5 top-0.5 h-2 w-2 rounded-full bg-blue-500" aria-label="An update is available"></span><?php endif; ?>
        </a>
        <span class="hidden text-slate-500 sm:inline"><?= e($owner['name'] ?? '') ?></span>
        <form method="post" action="/logout">
          <?= csrf_field() ?>
          <button type="submit" class="rounded-md px-2 py-1 text-slate-600 hover:bg-slate-100 hover:text-slate-900">Sign out</button>
        </form>
      </div>
    </div>
  </header>
  <main class="mx-auto max-w-7xl px-4 py-5">
    <?= $content ?>
  </main>
  <div id="toast-container" class="fixed bottom-4 left-4 z-50 w-80 max-w-[calc(100vw-2rem)] space-y-2" aria-live="polite"></div>
  <script src="/assets/js/app.js"></script>
</body>
</html>
