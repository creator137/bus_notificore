<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Application\HandleSmsAction;
use App\Infrastructure\Persistence\JsonFileStore;
use App\Infrastructure\Persistence\MessageRepository;
use App\Infrastructure\Persistence\PortalRepository;
use App\Infrastructure\Persistence\SettingsRepository;
use App\Services\SendLogger;

$store = new JsonFileStore(__DIR__ . '/../storage');
$portalRepository = new PortalRepository($store);
$settingsRepository = new SettingsRepository($store);
$messageRepository = new MessageRepository($store);

$memberId = (string)($_GET['member_id'] ?? '');
$portal = $memberId !== '' ? $portalRepository->findByMemberId($memberId) : $portalRepository->findFirst();

if (!$portal) {
    http_response_code(404);
    echo 'Portal not installed yet';
    exit;
}

$memberId = (string)$portal['member_id'];
$flash = null;
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'settings') {
    $settingsRepository->save($memberId, [
        'mode' => (string)($_POST['mode'] ?? 'mock'),
        'base_url' => (string)($_POST['base_url'] ?? ''),
        'login' => (string)($_POST['login'] ?? ''),
        'password' => (string)($_POST['password'] ?? ''),
        'project_id' => (string)($_POST['project_id'] ?? ''),
        'api_key' => (string)($_POST['api_key'] ?? ''),
        'auth_mode' => (string)($_POST['auth_mode'] ?? 'bearer'),
        'request_format' => (string)($_POST['request_format'] ?? 'json'),
        'sms_send_path' => (string)($_POST['sms_send_path'] ?? '/rest/sms/create'),
        'email_send_path' => (string)($_POST['email_send_path'] ?? '/email/send'),
        'api_key_header' => (string)($_POST['api_key_header'] ?? 'Authorization'),
        'verify_ssl' => isset($_POST['verify_ssl']) ? '1' : '0',
        'originator' => (string)($_POST['originator'] ?? ''),
        'validity' => (string)($_POST['validity'] ?? ''),
        'tariff' => (string)($_POST['tariff'] ?? ''),
        'is_2way' => isset($_POST['is_2way']) ? '1' : '0',
    ]);

    $flash = 'Настройки сохранены.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'test_send') {
    try {
        $action = new HandleSmsAction(
            portalRepository: $portalRepository,
            settingsRepository: $settingsRepository,
            messageRepository: $messageRepository,
            logger: new SendLogger($_ENV['LOG_PATH'] ?? (__DIR__ . '/../logs/send_log.jsonl')),
        );

        $testResult = $action([
            'member_id' => $memberId,
            'phone' => (string)($_POST['test_phone'] ?? ''),
            'message' => (string)($_POST['test_message'] ?? ''),
            'message_id' => 'manual-test-' . time(),
        ]);

        $flash = 'Тестовая отправка выполнена.';
    } catch (Throwable $e) {
        $flash = 'Ошибка тестовой отправки: ' . $e->getMessage();
    }
}

$settings = $settingsRepository->findByMemberId($memberId);
$messages = array_reverse($messageRepository->all());
$messages = array_slice($messages, 0, 10);

function checked(string $value): string
{
    return $value === '1' ? 'checked' : '';
}
?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <title>Notificore Settings</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 24px;
            max-width: 1200px;
            margin: 0 auto;
        }

        label {
            display: block;
            margin-top: 14px;
            font-weight: 700;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            box-sizing: border-box;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        button {
            margin-top: 20px;
            padding: 10px 16px;
            cursor: pointer;
        }

        .ok {
            background: #eaf7ea;
            border: 1px solid #8fd08f;
            padding: 12px;
            margin-bottom: 16px;
        }

        .meta {
            background: #f4f6f8;
            padding: 12px;
            margin-bottom: 16px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .card {
            background: #fff;
            border: 1px solid #ddd;
            padding: 18px;
        }

        .messages {
            margin-top: 24px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
        }

        code {
            background: #f2f2f2;
            padding: 2px 4px;
        }

        pre {
            background: #f7f7f7;
            padding: 12px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 14px;
        }

        .checkbox input {
            width: auto;
            margin-top: 0;
        }

        .hint {
            color: #666;
            font-size: 12px;
            margin-top: 4px;
        }
    </style>
</head>

<body>
    <h1>Notificore — настройки провайдера</h1>

    <?php if ($flash): ?>
        <div class="ok"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <div class="meta">
        <strong>Portal:</strong> <?= htmlspecialchars((string)$portal['domain']) ?><br>
        <strong>Member ID:</strong> <?= htmlspecialchars($memberId) ?><br>
        <strong>Installed at:</strong> <?= htmlspecialchars((string)($portal['installed_at'] ?? '')) ?><br>
        <strong>Handler:</strong> <code>/sms_handler.php</code><br>
        <strong>Status callback:</strong> <code>/status_callback.php</code>
    </div>

    <div class="grid">
        <div class="card">
            <h2>Настройки</h2>
            <form method="post">
                <input type="hidden" name="form_type" value="settings">

                <label>Режим</label>
                <select name="mode">
                    <option value="mock" <?= (($settings['mode'] ?? 'mock') === 'mock') ? 'selected' : '' ?>>mock</option>
                    <option value="real" <?= (($settings['mode'] ?? '') === 'real') ? 'selected' : '' ?>>real</option>
                </select>

                <label>Notificore Base URL</label>
                <input type="text" name="base_url" value="<?= htmlspecialchars((string)($settings['base_url'] ?? 'https://api.notificore.ru')) ?>">

                <label>Auth Mode</label>
                <select name="auth_mode">
                    <option value="bearer" <?= (($settings['auth_mode'] ?? 'bearer') === 'bearer') ? 'selected' : '' ?>>bearer</option>
                    <option value="basic" <?= (($settings['auth_mode'] ?? '') === 'basic') ? 'selected' : '' ?>>basic</option>
                    <option value="header" <?= (($settings['auth_mode'] ?? '') === 'header') ? 'selected' : '' ?>>header</option>
                    <option value="none" <?= (($settings['auth_mode'] ?? '') === 'none') ? 'selected' : '' ?>>none</option>
                </select>

                <label>Request Format</label>
                <select name="request_format">
                    <option value="json" <?= (($settings['request_format'] ?? 'json') === 'json') ? 'selected' : '' ?>>json</option>
                    <option value="form" <?= (($settings['request_format'] ?? '') === 'form') ? 'selected' : '' ?>>form</option>
                </select>

                <label>Login</label>
                <input type="text" name="login" value="<?= htmlspecialchars((string)($settings['login'] ?? '')) ?>">

                <label>Password</label>
                <input type="password" name="password" value="<?= htmlspecialchars((string)($settings['password'] ?? '')) ?>">

                <label>API Key</label>
                <input type="text" name="api_key" value="<?= htmlspecialchars((string)($settings['api_key'] ?? '')) ?>">

                <label>API Key Header</label>
                <input type="text" name="api_key_header" value="<?= htmlspecialchars((string)($settings['api_key_header'] ?? 'Authorization')) ?>">

                <label>Project ID</label>
                <input type="text" name="project_id" value="<?= htmlspecialchars((string)($settings['project_id'] ?? '')) ?>">

                <label>Originator</label>
                <input type="text" name="originator" maxlength="14" value="<?= htmlspecialchars((string)($settings['originator'] ?? '')) ?>">
                <div class="hint">Обязательное поле Notificore, до 14 символов.</div>

                <label>Validity (1-72)</label>
                <input type="text" name="validity" value="<?= htmlspecialchars((string)($settings['validity'] ?? '72')) ?>">

                <label>Tariff (0-9)</label>
                <input type="text" name="tariff" value="<?= htmlspecialchars((string)($settings['tariff'] ?? '0')) ?>">

                <label>SMS Send Path</label>
                <input type="text" name="sms_send_path" value="<?= htmlspecialchars((string)($settings['sms_send_path'] ?? '/rest/sms/create')) ?>">

                <label>Email Send Path</label>
                <input type="text" name="email_send_path" value="<?= htmlspecialchars((string)($settings['email_send_path'] ?? '/email/send')) ?>">

                <label class="checkbox">
                    <input type="checkbox" name="is_2way" value="1" <?= checked((string)($settings['is_2way'] ?? '0')) ?>>
                    2 WAY SMS
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="verify_ssl" value="1" <?= checked((string)($settings['verify_ssl'] ?? '1')) ?>>
                    Проверять SSL сертификат
                </label>

                <button type="submit">Сохранить настройки</button>
            </form>
        </div>

        <div class="card">
            <h2>Тестовая отправка</h2>
            <form method="post">
                <input type="hidden" name="form_type" value="test_send">

                <label>Телефон</label>
                <input type="text" name="test_phone" placeholder="79990001122" value="79990001122">

                <label>Сообщение</label>
                <textarea name="test_message">Test send from app.php</textarea>

                <button type="submit">Отправить тест</button>
            </form>

            <?php if ($testResult): ?>
                <h3>Результат</h3>
                <pre><?= htmlspecialchars(json_encode($testResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></pre>
            <?php endif; ?>
        </div>
    </div>

    <div class="messages">
        <h2>Последние сообщения</h2>

        <?php if ($messages === []): ?>
            <p>Сообщений пока нет.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Время</th>
                        <th>Телефон</th>
                        <th>Статус</th>
                        <th>Provider ID</th>
                        <th>Текст</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $message): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($message['ts'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string)($message['phone'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string)($message['status'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string)($message['provider_message_id'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string)($message['message'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>

</html>