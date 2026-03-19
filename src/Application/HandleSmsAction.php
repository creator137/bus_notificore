<?php

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\Persistence\MessageRepository;
use App\Infrastructure\Persistence\PortalRepository;
use App\Infrastructure\Persistence\SettingsRepository;
use App\Services\NotificationClientFactory;
use App\Services\SendLogger;

final class HandleSmsAction
{
    public function __construct(
        private readonly PortalRepository $portalRepository,
        private readonly SettingsRepository $settingsRepository,
        private readonly MessageRepository $messageRepository,
        private readonly SendLogger $logger,
    ) {
    }

    public function __invoke(array $payload): array
    {
        $memberId = (string)($payload['member_id'] ?? '');

        if ($memberId === '') {
            $portal = $this->portalRepository->findFirst();
        } else {
            $portal = $this->portalRepository->findByMemberId($memberId);
        }

        if (!$portal) {
            throw new \RuntimeException('Portal is not registered');
        }

        $settings = $this->settingsRepository->findByMemberId((string)$portal['member_id']);
        $client = NotificationClientFactory::makeFromSettings($settings);

        $phone = (string)(
            $payload['message_to']
            ?? $payload['PHONE']
            ?? $payload['phone']
            ?? ''
        );

        $message = (string)(
            $payload['message_body']
            ?? $payload['MESSAGE']
            ?? $payload['message']
            ?? ''
        );

        $bitrixMessageId = (string)(
            $payload['message_id']
            ?? $payload['MESSAGE_ID']
            ?? ''
        );

        if ($phone === '' || $message === '') {
            throw new \RuntimeException('Incoming payload does not contain phone or message');
        }

        $sendResult = $client->sendSms($phone, $message);

        $record = [
            'ts' => date('c'),
            'member_id' => (string)$portal['member_id'],
            'bitrix_message_id' => $bitrixMessageId,
            'provider_message_id' => (string)($sendResult['provider_message_id'] ?? ''),
            'phone' => $phone,
            'message' => $message,
            'status' => (string)($sendResult['status'] ?? 'unknown'),
            'channel' => 'sms',
            'send_result' => $sendResult,
            'raw_payload' => $payload,
        ];

        $this->logger->log($record);
        $this->messageRepository->add($record);

        return [
            'success' => true,
            'data' => $record,
        ];
    }
}