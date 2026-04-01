<?php

namespace Notificore\Sms\Helper;

final class TextHelper
{
    public static function sourceLabel(string $source): string
    {
        return match (mb_strtolower(trim($source))) {
            'manual_ui' => 'Тест из админки',
            'mail_event' => 'Почтовое событие',
            'form_result_added' => 'Результат веб-формы',
            'sale_order_created' => 'Создание заказа',
            'reminder' => 'Напоминание',
            'custom_event' => 'Кастомное событие',
            'module_api' => 'Прямой API модуля',
            'status_callback' => 'Callback статуса',
            'manual_sync' => 'Ручная синхронизация',
            default => $source === '' ? 'Не указан' : str_replace('_', ' ', $source),
        };
    }

    public static function eventTypeLabel(string $eventType): string
    {
        return match (mb_strtolower(trim($eventType))) {
            'sale_order_created' => 'Создание заказа',
            'mail_event' => 'Почтовое событие',
            'form_result_added' => 'Результат веб-формы',
            'reminder' => 'Напоминание',
            'custom_event' => 'Кастомное событие',
            default => $eventType === '' ? 'Не указан' : $eventType,
        };
    }

    public static function statusMeta(string $status): array
    {
        $status = mb_strtolower(trim($status));

        return match ($status) {
            'accepted' => ['label' => 'В обработке', 'tone' => 'adm-info-message'],
            'sent' => ['label' => 'Передано оператору', 'tone' => 'adm-info-message'],
            'scheduled' => ['label' => 'Запланировано', 'tone' => 'adm-info-message-yellow'],
            'delivered' => ['label' => 'Доставлено', 'tone' => 'adm-info-message-green'],
            'failed', 'http_error', 'rejected' => ['label' => 'Ошибка', 'tone' => 'adm-info-message-red'],
            'undeliverable', 'undelivered', 'expired', 'cancelled', 'canceled' => ['label' => 'Не доставлено', 'tone' => 'adm-info-message-red'],
            'duplicate_skipped' => ['label' => 'Дубль пропущен', 'tone' => 'adm-info-message-yellow'],
            default => ['label' => $status === '' ? 'Ожидает' : $status, 'tone' => 'adm-info-message-yellow'],
        };
    }

    public static function humanizeError(string $message): string
    {
        $message = trim($message);

        if ($message === '') {
            return 'Произошла ошибка. Проверьте настройки интеграции.';
        }

        $normalized = mb_strtolower($message);

        if (str_contains($normalized, 'apikey') || str_contains($normalized, 'api key') || str_contains($normalized, 'unauthorized') || str_contains($normalized, 'forbidden')) {
            return 'Проверьте API-ключ Notificore.';
        }

        if (str_contains($normalized, 'originator') || str_contains($normalized, 'sender')) {
            return 'Проверьте имя отправителя. Скорее всего sender не разрешён у провайдера.';
        }

        if (str_contains($normalized, 'phone')) {
            return 'Проверьте номер телефона получателя.';
        }

        return $message;
    }

    public static function preview(string $value, int $limit = 110): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if ($value === '') {
            return '—';
        }

        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit - 1) . '…';
    }
}
