<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

final class MessageRepository
{
    private const FILE_NAME = 'messages.json';

    public function __construct(
        private readonly JsonFileStore $store
    ) {
    }

    public function add(array $message): void
    {
        $all = $this->store->read(self::FILE_NAME);
        $all[] = $message;
        $this->store->write(self::FILE_NAME, $all);
    }

    public function all(): array
    {
        return $this->store->read(self::FILE_NAME);
    }

    public function findByProviderMessageId(string $providerMessageId): ?array
    {
        foreach ($this->all() as $row) {
            if (($row['provider_message_id'] ?? '') === $providerMessageId) {
                return $row;
            }
        }

        return null;
    }

    public function updateByProviderMessageId(string $providerMessageId, array $patch): bool
    {
        $all = $this->all();
        $updated = false;

        foreach ($all as $index => $row) {
            if (($row['provider_message_id'] ?? '') === $providerMessageId) {
                $all[$index] = array_replace($row, $patch);
                $updated = true;
                break;
            }
        }

        if ($updated) {
            $this->store->write(self::FILE_NAME, $all);
        }

        return $updated;
    }
}