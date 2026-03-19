<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Application\HandleSmsAction;
use App\Infrastructure\Persistence\JsonFileStore;
use App\Infrastructure\Persistence\MessageRepository;
use App\Infrastructure\Persistence\PortalRepository;
use App\Infrastructure\Persistence\SettingsRepository;
use App\Services\SendLogger;

header('Content-Type: application/json; charset=utf-8');

try {
    $store = new JsonFileStore(__DIR__ . '/../storage');

    $action = new HandleSmsAction(
        portalRepository: new PortalRepository($store),
        settingsRepository: new SettingsRepository($store),
        messageRepository: new MessageRepository($store),
        logger: new SendLogger($_ENV['LOG_PATH'] ?? (__DIR__ . '/../logs/send_log.jsonl')),
    );

    $result = $action($_REQUEST);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'request' => $_REQUEST,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}