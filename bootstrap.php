<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Moscow');

$storageDir = $_ENV['APP_STORAGE_DIR'] ?? (__DIR__ . '/storage');
if (!str_starts_with($storageDir, DIRECTORY_SEPARATOR) && !preg_match('~^[A-Za-z]:[\\\\/]~', $storageDir)) {
    $storageDir = __DIR__ . '/' . ltrim($storageDir, '/');
}

if (!is_dir($storageDir)) {
    mkdir($storageDir, 0777, true);
}

if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0777, true);
}
