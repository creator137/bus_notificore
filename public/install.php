<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Application\InstallAppAction;
use App\Application\RegisterSenderAction;
use App\Infrastructure\Persistence\JsonFileStore;
use App\Infrastructure\Persistence\PortalRepository;

$store = new JsonFileStore(__DIR__ . '/../storage');
$portalRepository = new PortalRepository($store);

$action = new InstallAppAction(
    portalRepository: $portalRepository,
    registerSenderAction: new RegisterSenderAction(),
);

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($isPost) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $payload = [
            'DOMAIN' => (string)($_POST['DOMAIN'] ?? ''),
            'AUTH_ID' => (string)($_POST['AUTH_ID'] ?? ''),
            'REFRESH_ID' => (string)($_POST['REFRESH_ID'] ?? ''),
            'AUTH_EXPIRES' => (string)($_POST['AUTH_EXPIRES'] ?? '3600'),
            'member_id' => (string)($_POST['member_id'] ?? ''),
        ];

        $result = $action($payload);

        echo json_encode([
            'success' => true,
            'message' => 'Application installed and sender registered',
            'data' => $result,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;

    } catch (Throwable $e) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Dev Install</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 24px; max-width: 900px; margin: 0 auto; }
        label { display: block; margin-top: 14px; font-weight: 700; }
        input { width: 100%; padding: 10px; margin-top: 6px; box-sizing: border-box; }
        button { margin-top: 20px; padding: 10px 16px; cursor: pointer; }
        .meta { background: #f4f6f8; padding: 12px; margin-bottom: 16px; }
        code { background: #f2f2f2; padding: 2px 4px; }
    </style>
</head>
<body>
    <h1>Dev Install — Bitrix24 Market App</h1>

    <div class="meta">
        <strong>DEV_MODE:</strong> <?= htmlspecialchars((string)($_ENV['DEV_MODE'] ?? 'false')) ?><br>
        <strong>DEV_INSTALL_MOCK:</strong> <?= htmlspecialchars((string)($_ENV['DEV_INSTALL_MOCK'] ?? 'false')) ?><br>
        <strong>APP_BASE_URL:</strong> <code><?= htmlspecialchars((string)($_ENV['APP_BASE_URL'] ?? '')) ?></code>
    </div>

    <form method="post">
        <label>DOMAIN</label>
        <input type="text" name="DOMAIN" value="local.test">

        <label>AUTH_ID</label>
        <input type="text" name="AUTH_ID" value="mock-token">

        <label>REFRESH_ID</label>
        <input type="text" name="REFRESH_ID" value="mock-refresh-token">

        <label>AUTH_EXPIRES</label>
        <input type="text" name="AUTH_EXPIRES" value="3600">

        <label>member_id</label>
        <input type="text" name="member_id" value="dev-installed-portal">

        <button type="submit">Симулировать установку</button>
    </form>
</body>
</html>