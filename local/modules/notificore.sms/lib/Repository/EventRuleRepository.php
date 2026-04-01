<?php

namespace Notificore\Sms\Repository;

use Notificore\Sms\Helper\DateTimeHelper;
use Notificore\Sms\Orm\EventRuleTable;

final class EventRuleRepository
{
    public function getAll(): array
    {
        $items = [];
        $result = EventRuleTable::getList([
            'order' => ['UPDATED_AT' => 'DESC', 'ID' => 'DESC'],
        ]);

        while ($row = $result->fetch()) {
            $items[] = $this->hydrate($row);
        }

        return $items;
    }

    public function findActiveForEvent(string $eventType, string $eventCode = ''): array
    {
        $items = [];
        $result = EventRuleTable::getList([
            'filter' => [
                '=ACTIVE' => 'Y',
                '=EVENT_TYPE' => trim($eventType),
            ],
            'order' => ['ID' => 'ASC'],
        ]);

        while ($row = $result->fetch()) {
            $rule = $this->hydrate($row);
            $ruleCode = trim((string)$rule['event_code']);

            if ($ruleCode !== '' && $ruleCode !== '*' && $ruleCode !== trim($eventCode)) {
                continue;
            }

            $items[] = $rule;
        }

        return $items;
    }

    public function save(array $rule): int
    {
        $id = (int)($rule['id'] ?? 0);
        $payload = [
            'ACTIVE' => strtoupper((string)($rule['active'] ?? 'N')) === 'Y' ? 'Y' : 'N',
            'EVENT_TYPE' => trim((string)($rule['event_type'] ?? 'custom_event')) ?: 'custom_event',
            'EVENT_CODE' => $this->nullify($rule['event_code'] ?? null),
            'DESCRIPTION' => trim((string)($rule['description'] ?? '')),
            'PHONE_PATH' => trim((string)($rule['phone_path'] ?? '')),
            'MESSAGE_TEMPLATE' => trim((string)($rule['message_template'] ?? '')),
            'EXTERNAL_ID_TEMPLATE' => $this->nullify($rule['external_id_template'] ?? null),
            'UPDATED_AT' => DateTimeHelper::now(),
        ];

        if ($id > 0) {
            EventRuleTable::update($id, $payload);
            return $id;
        }

        $duplicateId = $this->findDuplicateId($payload);

        if ($duplicateId > 0) {
            EventRuleTable::update($duplicateId, $payload);
            return $duplicateId;
        }

        $result = EventRuleTable::add($payload + [
            'CREATED_AT' => DateTimeHelper::now(),
        ]);

        return (int)$result->getId();
    }

    public function delete(int $id): void
    {
        if ($id > 0) {
            EventRuleTable::delete($id);
        }
    }

    private function hydrate(array $row): array
    {
        return [
            'id' => (int)($row['ID'] ?? 0),
            'active' => (string)($row['ACTIVE'] ?? 'N'),
            'event_type' => (string)($row['EVENT_TYPE'] ?? ''),
            'event_code' => (string)($row['EVENT_CODE'] ?? ''),
            'description' => (string)($row['DESCRIPTION'] ?? ''),
            'phone_path' => (string)($row['PHONE_PATH'] ?? ''),
            'message_template' => (string)($row['MESSAGE_TEMPLATE'] ?? ''),
            'external_id_template' => (string)($row['EXTERNAL_ID_TEMPLATE'] ?? ''),
            'created_at' => DateTimeHelper::toString($row['CREATED_AT'] ?? ''),
            'updated_at' => DateTimeHelper::toString($row['UPDATED_AT'] ?? ''),
        ];
    }

    private function nullify(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function findDuplicateId(array $payload): int
    {
        $result = EventRuleTable::getList([
            'select' => ['ID'],
            'filter' => [
                '=EVENT_TYPE' => (string)($payload['EVENT_TYPE'] ?? ''),
                '=EVENT_CODE' => $payload['EVENT_CODE'] ?? null,
                '=PHONE_PATH' => (string)($payload['PHONE_PATH'] ?? ''),
                '=MESSAGE_TEMPLATE' => (string)($payload['MESSAGE_TEMPLATE'] ?? ''),
                '=EXTERNAL_ID_TEMPLATE' => $payload['EXTERNAL_ID_TEMPLATE'] ?? null,
            ],
            'order' => ['ID' => 'ASC'],
            'limit' => 1,
        ]);

        $row = $result->fetch();

        return (int)($row['ID'] ?? 0);
    }
}
