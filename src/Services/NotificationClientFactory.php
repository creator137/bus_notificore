<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\NotificationClientInterface;
use RuntimeException;

final class NotificationClientFactory
{
    public static function make(): NotificationClientInterface
    {
        return self::makeFromSettings([
            'mode' => (string)($_ENV['NOTIFICORE_MODE'] ?? 'mock'),
            'base_url' => (string)($_ENV['NOTIFICORE_BASE_URL'] ?? ''),
            'login' => (string)($_ENV['NOTIFICORE_LOGIN'] ?? ''),
            'password' => (string)($_ENV['NOTIFICORE_PASSWORD'] ?? ''),
            'project_id' => (string)($_ENV['NOTIFICORE_PROJECT_ID'] ?? ''),
            'api_key' => (string)($_ENV['NOTIFICORE_API_KEY'] ?? ''),
            'auth_mode' => (string)($_ENV['NOTIFICORE_AUTH_MODE'] ?? 'bearer'),
            'request_format' => (string)($_ENV['NOTIFICORE_REQUEST_FORMAT'] ?? 'json'),
            'sms_send_path' => (string)($_ENV['NOTIFICORE_SMS_SEND_PATH'] ?? '/rest/sms/create'),
            'email_send_path' => (string)($_ENV['NOTIFICORE_EMAIL_SEND_PATH'] ?? '/email/send'),
            'api_key_header' => (string)($_ENV['NOTIFICORE_API_KEY_HEADER'] ?? 'Authorization'),
            'verify_ssl' => (string)($_ENV['NOTIFICORE_VERIFY_SSL'] ?? '1'),
            'originator' => (string)($_ENV['NOTIFICORE_ORIGINATOR'] ?? ''),
            'validity' => (string)($_ENV['NOTIFICORE_VALIDITY'] ?? ''),
            'tariff' => (string)($_ENV['NOTIFICORE_TARIFF'] ?? ''),
            'is_2way' => (string)($_ENV['NOTIFICORE_2WAY'] ?? '0'),
        ]);
    }

    public static function makeFromSettings(array $settings): NotificationClientInterface
    {
        $mode = strtolower((string)($settings['mode'] ?? 'mock'));

        return match ($mode) {
            'mock' => new MockNotificoreClient(),
            'real', 'notificore' => new NotificoreClient(
                baseUrl: (string)($settings['base_url'] ?? ''),
                login: (string)($settings['login'] ?? ''),
                password: (string)($settings['password'] ?? ''),
                projectId: (string)($settings['project_id'] ?? ''),
                apiKey: (string)($settings['api_key'] ?? ''),
                authMode: (string)($settings['auth_mode'] ?? 'bearer'),
                requestFormat: (string)($settings['request_format'] ?? 'json'),
                smsSendPath: (string)($settings['sms_send_path'] ?? '/rest/sms/create'),
                emailSendPath: (string)($settings['email_send_path'] ?? '/email/send'),
                apiKeyHeader: (string)($settings['api_key_header'] ?? 'Authorization'),
                verifySsl: self::toBool($settings['verify_ssl'] ?? '1'),
                originator: (string)($settings['originator'] ?? ''),
                validity: (string)($settings['validity'] ?? ''),
                tariff: (string)($settings['tariff'] ?? ''),
                is2way: (string)($settings['is_2way'] ?? '0'),
            ),
            default => throw new RuntimeException('Unsupported notificore mode: ' . $mode),
        };
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }
}
