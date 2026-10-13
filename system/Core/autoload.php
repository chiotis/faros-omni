<?php

declare(strict_types=1);

// Omni\Core\Router lives in system/Core/Router.php, and so on.
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Omni\\')) {
        return;
    }
    $file = dirname(__DIR__) . '/' . str_replace('\\', '/', substr($class, 5)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
