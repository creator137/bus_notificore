<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use RuntimeException;

final class CurlHttpClient
{
    public function post(
        string $url,
        array $payload = [],
        array $headers = [],
        string $format = 'json',
        bool $verifySsl = true,
    ): array {
        $ch = curl_init($url);

        $normalizedHeaders = $headers;
        $body = '';

        if ($format === 'json') {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($body === false) {
                throw new RuntimeException('Cannot encode request payload to JSON');
            }

            $normalizedHeaders[] = 'Content-Type: application/json';
        } elseif ($format === 'form') {
            $body = http_build_query($payload);
            $normalizedHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
        } else {
            throw new RuntimeException('Unsupported request format: ' . $format);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $normalizedHeaders,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('HTTP client cURL error: ' . $error);
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        return [
            'http_code' => $httpCode,
            'content_type' => $contentType,
            'body_raw' => $response,
            'body_json' => is_array($decoded) ? $decoded : null,
        ];
    }
}
