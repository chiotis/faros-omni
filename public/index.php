<?php

declare(strict_types=1);

// PHP's own server (development, tests) serves the files under public/ itself.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file) && !str_ends_with($file, '.php')) {
        return false;
    }
}

define('ROOT_PATH', dirname(__DIR__));
define('SYSTEM_PATH', ROOT_PATH . '/system');
// Tests point Omni at a storage folder of their own.
define('STORAGE_PATH', getenv('OMNI_STORAGE') ?: ROOT_PATH . '/storage');

require SYSTEM_PATH . '/Core/autoload.php';
require SYSTEM_PATH . '/Core/helpers.php';

$config = require SYSTEM_PATH . '/config.php';
$app = new Omni\Core\App($config);
$app->run();
