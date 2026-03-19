<?php

declare(strict_types=1);

namespace App\Infrastructure\Bitrix;

use RuntimeException;

final class BitrixAppClient
{
    public function __construct(
        private readonly string $domain,
        private readonly string $accessToken
    ) {}

    public function call(string $method, array $fields = []): array
    {
        $domain = preg_replace('~^https?://~', '', trim($this->domain));
        $url = 'https://' . rtrim($domain, '/') . '/rest/' . $method . '.json?auth=' . urlencode($this->accessToken);

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_SSL_VERIFYPEER => false, // локально пока так; на VPS вернуть true
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error: ' . $error);
        }

        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new RuntimeException('HTTP error: ' . $httpCode . ' / ' . $response);
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON response: ' . $response);
        }

        if (!empty($data['error'])) {
            throw new RuntimeException('Bitrix24 error: ' . $data['error'] . ' / ' . ($data['error_description'] ?? ''));
        }

        return $data['result'] ?? [];
    }

    public function ensureSmsSenderRegistered(string $code, string $name, string $handlerUrl): array
    {
        try {
            return $this->call('messageservice.sender.add', [
                'CODE' => $code,
                'TYPE' => 'SMS',
                'HANDLER' => $handlerUrl,
                'NAME' => $name,
            ]);
        } catch (\Throwable $e) {
            return $this->call('messageservice.sender.update', [
                'CODE' => $code,
                'HANDLER' => $handlerUrl,
                'NAME' => $name,
            ]);
        }
    }
}
