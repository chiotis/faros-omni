<?php /** @var string $content @var string $title */ ?>
<!doctype html>
<html lang="en" class="h-full bg-slate-50">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · Omni</title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="icon" href="/assets/img/omni.svg" type="image/svg+xml">
</head>
<body class="flex min-h-full items-center justify-center px-4 py-10 text-slate-800 antialiased">
  <div class="w-full max-w-sm">
    <div class="mb-5 flex items-center justify-center gap-2 text-base font-semibold text-slate-900">
      <img src="/assets/img/omni.svg" alt="" class="h-6 w-6">
      Omni
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
      <h1 class="mb-4 text-base font-semibold text-slate-900"><?= e($title) ?></h1>
      <?= $content ?>
    </div>
  </div>
</body>
</html>
