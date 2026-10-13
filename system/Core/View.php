<?php

declare(strict_types=1);

namespace Omni\Core;

/** Renders a PHP view from system/Views inside a layout. */
final class View
{
    /** @var array<string, mixed> Data every view gets (the layout's header). */
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /** @param array<string, mixed> $data */
    public static function render(string $view, array $data = [], string $layout = 'app'): void
    {
        $content = self::fetch($view, $data);
        echo self::fetch('layouts/' . $layout, $data + ['content' => $content]);
    }

    /** @param array<string, mixed> $data */
    public static function fetch(string $view, array $data = []): string
    {
        extract($data + self::$shared, EXTR_SKIP);
        ob_start();
        include SYSTEM_PATH . '/Views/' . $view . '.php';
        return (string)ob_get_clean();
    }
}
