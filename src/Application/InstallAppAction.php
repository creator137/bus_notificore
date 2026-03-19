<?php

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\Persistence\PortalRepository;

final class InstallAppAction
{
    public function __construct(
        private readonly PortalRepository $portalRepository,
        private readonly RegisterSenderAction $registerSenderAction,
    ) {}

    public function __invoke(array $payload): array
    {
        $accessToken = (string)(
            $payload['AUTH_ID']
            ?? $payload['auth']['access_token']
            ?? $payload['AUTH']['access_token']
            ?? ''
        );

        $refreshToken = (string)(
            $payload['REFRESH_ID']
            ?? $payload['auth']['refresh_token']
            ?? $payload['AUTH']['refresh_token']
            ?? ''
        );

        $expires = (int)(
            $payload['AUTH_EXPIRES']
            ?? $payload['auth']['expires']
            ?? $payload['AUTH']['expires']
            ?? 3600
        );

        $domain = (string)(
            $payload['DOMAIN']
            ?? $payload['auth']['domain']
            ?? $payload['AUTH']['domain']
            ?? ''
        );

        $memberId = (string)(
            $payload['member_id']
            ?? $payload['auth']['member_id']
            ?? $payload['AUTH']['member_id']
            ?? ''
        );

        if ($accessToken === '' || $domain === '' || $memberId === '') {
            throw new \RuntimeException('Install payload is incomplete');
        }

        $portal = [
            'member_id' => $memberId,
            'domain' => $domain,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => time() + $expires,
            'installed_at' => date('c'),
            'raw' => $payload,
        ];

        $this->portalRepository->save($portal);

        $senderResult = ($this->registerSenderAction)($portal);

        return [
            'portal' => $portal,
            'sender_result' => $senderResult,
        ];
    }
}
