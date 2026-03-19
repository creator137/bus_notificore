<?php

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\Persistence\MessageRepository;
use App\Services\SendLogger;

final class HandleStatusCallbackAction
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly SendLogger $logger,
    ) {
    }

    public function __invoke(array $payload): array
    {
        $providerMessageId = (string)(
            $payload['provider_message_id']
            ?? $payload['message_id']
            ?? $payload['id']
            ?? ''
        );

        $status = (string)(
            $payload['status']
            ?? $payload['delivery_status']
            ?? 'unknown'
        );

        if ($providerMessageId === '') {
            throw new \RuntimeException('provider_message_id is required');
        }

        $existing = $this->messageRepository->findByProviderMessageId($providerMessageId);

        if (!$existing) {
            $record = [
                'ts' => date('c'),
                'provider_message_id' => $providerMessageId,
                'status' => $status,
                'callback_only' => true,
                'raw_payload' => $payload,
            ];

            $this->logger->log($record);

            return [
                'success' => true,
                'message' => 'Status callback received, but message not found in local storage',
                'data' => $record,
            ];
        }

        $patch = [
            'status' => $status,
            'status_updated_at' => date('c'),
            'status_payload' => $payload,
        ];

        $this->messageRepository->updateByProviderMessageId($providerMessageId, $patch);

        $record = array_replace($existing, $patch);

        $this->logger->log([
            'ts' => date('c'),
            'type' => 'status_callback',
            'provider_message_id' => $providerMessageId,
            'status' => $status,
            'raw_payload' => $payload,
        ]);

        return [
            'success' => true,
            'message' => 'Status updated',
            'data' => $record,
        ];
    }
}