<?php

declare(strict_types=1);

/** Escapes text for HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    return is_string($_POST['_csrf'] ?? null) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['_csrf']);
}

/** A message shown as a toast on the next page. type: success | error | info | warning */
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

/** Bytes as a short size: 1.4 GB. */
function bytes(?int $bytes): string
{
    if ($bytes === null) {
        return '—';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $size = (float)max(0, $bytes);
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return ($i === 0 ? (string)(int)$size : number_format($size, $size >= 10 ? 0 : 1)) . ' ' . $units[$i];
}

/** How long ago a time was, shortly: 5 min ago, 3 h ago, 2 d ago. */
function ago(?string $iso, ?int $now = null): string
{
    $time = $iso !== null && $iso !== '' ? strtotime($iso) : false;
    if ($time === false) {
        return 'never';
    }
    $diff = max(0, ($now ?? time()) - $time);
    return match (true) {
        $diff < 60 => 'just now',
        $diff < 3600 => intdiv($diff, 60) . ' min ago',
        $diff < 86400 => intdiv($diff, 3600) . ' h ago',
        default => intdiv($diff, 86400) . ' d ago',
    };
}

/** A UTC time in the owner's time zone: 2026-10-13 12:00. */
function local_time(?string $iso): string
{
    $time = $iso !== null && $iso !== '' ? strtotime($iso) : false;
    return $time === false ? '—' : date('Y-m-d H:i', $time);
}

/**
 * A status pill. tone: green | amber | red | blue | slate
 */
function badge(string $label, string $tone = 'slate', string $title = ''): string
{
    $tones = [
        'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'red' => 'bg-red-50 text-red-700 ring-red-200',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-200',
        'slate' => 'bg-slate-100 text-slate-600 ring-slate-200',
    ];
    return '<span class="inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ' . ($tones[$tone] ?? $tones['slate']) . '"'
        . ($title !== '' ? ' title="' . e($title) . '"' : '') . '>' . e($label) . '</span>';
}

/** Release notes (Markdown from CHANGELOG.md) as HTML: lists, nested by indent, **bold**, `code`; everything else is escaped. */
function release_notes(string $markdown): string
{
    $inline = static fn(string $text): string => preg_replace(['/\*\*(.+?)\*\*/', '/`([^`]+)`/'], ['<strong>$1</strong>', '<code class="rounded bg-slate-100 px-1 text-xs">$1</code>'], e($text)) ?? e($text);
    $html = '';
    $depth = 0;
    foreach (preg_split('/\R/', trim($markdown)) ?: [] as $line) {
        if (trim($line) === '') {
            continue;
        }
        if (preg_match('/^( *)[-*] (.*)$/', $line, $m)) {
            $level = intdiv(strlen($m[1]), 2) + 1;
            while ($depth < $level) {
                $html .= '<ul class="' . ($depth === 0 ? 'list-disc space-y-1 pl-5' : 'mt-1 list-[circle] space-y-1 pl-5') . '">';
                $depth++;
            }
            while ($depth > $level) {
                $html .= '</ul>';
                $depth--;
            }
            $html .= '<li>' . $inline($m[2]) . '</li>';
        } else {
            $html .= str_repeat('</ul>', $depth) . '<p class="mt-2">' . $inline(trim($line)) . '</p>';
            $depth = 0;
        }
    }
    return $html . str_repeat('</ul>', $depth);
}
