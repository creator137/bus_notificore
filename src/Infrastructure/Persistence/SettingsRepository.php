<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

final class SettingsRepository
{
    private const FILE_NAME = 'settings.json';

    public function __construct(
        private readonly JsonFileStore $store
    ) {}

    public function save(string $memberId, array $settings): void
    {
        $all = $this->store->read(self::FILE_NAME);
        $all[$memberId] = $settings;

        $this->store->write(self::FILE_NAME, $all);
    }

    public function findByMemberId(string $memberId): array
    {
        $all = $this->store->read(self::FILE_NAME);
        return $all[$memberId] ?? [];
    }
}
