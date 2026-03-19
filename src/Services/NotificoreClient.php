<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\NotificationClientInterface;
use App\Infrastructure\Http\CurlHttpClient;
use RuntimeException;

final class NotificoreClient implements NotificationClientInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $login = '',
        private readonly string $password = '',
        private readonly string $projectId = '',
        private readonly string $apiKey = '',
        private readonly string $authMode = 'bearer',
        private readonly string $requestFormat = 'json',
        private readonly string $smsSendPath = '/rest/sms/create',
        private readonly string $emailSendPath = '/email/send',
        private readonly string $apiKeyHeader = 'Authorization',
        private readonly bool $verifySsl = true,
        private readonly string $originator = '',
        private readonly string $validity = '',
        private readonly string $tariff = '',
        private readonly string $is2way = '0',
        private readonly ?CurlHttpClient $httpClient = null,
    ) {}

    public function sendSms(string $phone, string $message): array
    {
        $this->guardSmsConfig();

        $normalizedPhone = $this->normalizePhone($phone);
        if ($normalizedPhone === '') {
            throw new RuntimeException('Phone is empty after normalization');
        }

        $reference = $this->buildReference($normalizedPhone, $message);

        $payload = [
            'destination' => 'phone',
            'originator' => $this->originator,
            'body' => $message,
            'msisdn' => $normalizedPhone,
            'reference' => $reference,
        ];

        if ($this->validity !== '') {
            $payload['validity'] = (int)$this->validity;
        }

        if ($this->tariff !== '') {
            $payload['tariff'] = (int)$this->tariff;
        }

        if ($this->isTruthy($this->is2way)) {
            $payload['2way'] = 1;
        }

        $response = $this->request($this->smsSendPath, $payload);
        $json = $response['body_json'] ?? [];

        $apiError = (string)($json['error'] ?? '');
        $successHttp = $response['http_code'] >= 200 && $response['http_code'] < 300;
        $successApi = ($apiError === '' || $apiError === '0');

        return [
            'success' => $successHttp && $successApi,
            'status' => $this->extractStatus($response),
            'provider_message_id' => (string)($json['id'] ?? ''),
            'provider_reference' => (string)($json['reference'] ?? $reference),
            'price' => (string)($json['price'] ?? ''),
            'currency' => (string)($json['currency'] ?? ''),
            'channel' => 'sms',
            'request_payload' => $payload,
            'response' => $response,
        ];
    }

    public function sendEmail(string $email, string $subject, string $message): array
    {
        throw new RuntimeException('Email Notificore API is not configured yet');
    }

    private function request(string $path, array $payload): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        $client = $this->httpClient ?? new CurlHttpClient();

        return $client->post(
            url: $url,
            payload: $payload,
            headers: $this->buildHeaders(),
            format: $this->requestFormat,
            verifySsl: $this->verifySsl,
        );
    }

    private function buildHeaders(): array
    {
        return match (strtolower($this->authMode)) {
            'bearer' => [
                'Authorization: Bearer ' . $this->apiKey,
            ],
            'header' => [
                $this->apiKeyHeader . ': ' . $this->apiKey,
            ],
            'basic' => [
                'Authorization: Basic ' . base64_encode($this->login . ':' . $this->password),
            ],
            'none' => [],
            default => throw new RuntimeException('Unsupported Notificore auth mode: ' . $this->authMode),
        };
    }

    private function guardSmsConfig(): void
    {
        if ($this->baseUrl === '') {
            throw new RuntimeException('Notificore baseUrl is empty');
        }

        if (strtolower($this->authMode) === 'bearer' && $this->apiKey === '') {
            throw new RuntimeException('Notificore apiKey is empty');
        }

        if (strtolower($this->authMode) === 'basic' && ($this->login === '' || $this->password === '')) {
            throw new RuntimeException('Notificore basic auth requires login and password');
        }

        if ($this->originator === '') {
            throw new RuntimeException('Notificore originator is empty');
        }

        if (mb_strlen($this->originator) > 14) {
            throw new RuntimeException('Notificore originator must be 14 chars or less');
        }

        if ($this->validity !== '') {
            $validity = (int)$this->validity;
            if ($validity < 1 || $validity > 72) {
                throw new RuntimeException('Notificore validity must be from 1 to 72');
            }
        }

        if ($this->tariff !== '') {
            $tariff = (int)$this->tariff;
            if ($tariff < 0 || $tariff > 9) {
                throw new RuntimeException('Notificore tariff must be from 0 to 9');
            }
        }
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function buildReference(string $phone, string $message): string
    {
        return 'b24' . time() . substr(md5($phone . $message), 0, 12);
    }

    private function isTruthy(string $value): bool
    {
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    private function extractStatus(array $response): string
    {
        $json = $response['body_json'] ?? [];
        $apiError = (string)($json['error'] ?? '');

        if ($response['http_code'] >= 200 && $response['http_code'] < 300 && ($apiError === '' || $apiError === '0')) {
            return 'accepted';
        }

        return (string)($json['status'] ?? 'http_error');
    }
}
