<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

final class PortalRepository
{
    private const FILE_NAME = 'portals.json';

    public function __construct(
        private readonly JsonFileStore $store
    ) {}

    public function save(array $portal): void
    {
        $memberId = (string)($portal['member_id'] ?? '');
        if ($memberId === '') {
            throw new \RuntimeException('member_id is required');
        }

        $all = $this->store->read(self::FILE_NAME);
        $all[$memberId] = $portal;

        $this->store->write(self::FILE_NAME, $all);
    }

    public function findByMemberId(string $memberId): ?array
    {
        $all = $this->store->read(self::FILE_NAME);
        return $all[$memberId] ?? null;
    }

    public function findFirst(): ?array
    {
        $all = $this->store->read(self::FILE_NAME);
        if ($all === []) {
            return null;
        }

        return reset($all) ?: null;
    }

    public function all(): array
    {
        return $this->store->read(self::FILE_NAME);
    }
}
