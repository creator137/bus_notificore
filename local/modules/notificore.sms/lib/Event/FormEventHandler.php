<?php

namespace Notificore\Sms\Event;

use Notificore\Sms\Service\Container;
use Throwable;

final class FormEventHandler
{
    public static function onAfterResultAdd($webFormId, $resultId): void
    {
        $webFormId = (int)$webFormId;
        $resultId = (int)$resultId;

        if ($webFormId <= 0 || $resultId <= 0) {
            return;
        }

        try {
            $payload = self::buildPayload($webFormId, $resultId);
            $eventCode = self::resolveEventCode($webFormId);

            Container::getInstance()->eventSmsService()->dispatch('form_result_added', $eventCode, $payload);
        } catch (Throwable $exception) {
            Container::getInstance()->logRepository()->add('error', 'form_event_handler_error', 'Ошибка обработчика onAfterResultAdd.', [
                'web_form_id' => $webFormId,
                'result_id' => $resultId,
                'error_message' => $exception->getMessage(),
            ]);
        }
    }

    private static function buildPayload(int $webFormId, int $resultId): array
    {
        $payload = [
            'FORM_ID' => $webFormId,
            'RESULT_ID' => $resultId,
        ];

        if (!class_exists('CFormResult')) {
            return $payload;
        }

        $result = [];
        $answers = [];
        \CFormResult::GetDataByID($resultId, [], $result, $answers);

        foreach ($result as $key => $value) {
            if (!is_scalar($value) || trim((string)$value) === '') {
                continue;
            }

            $payload[(string)$key] = (string)$value;
        }

        foreach ($answers as $sid => $items) {
            $value = self::extractAnswerValue((array)$items);

            if ($value !== '') {
                $payload[(string)$sid] = $value;
            }
        }

        return $payload;
    }

    private static function extractAnswerValue(array $items): string
    {
        $values = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            foreach (['USER_TEXT', 'ANSWER_TEXT', 'VALUE'] as $key) {
                $value = trim((string)($item[$key] ?? ''));

                if ($value !== '') {
                    $values[] = $value;
                    break;
                }
            }
        }

        return $values === [] ? '' : implode(', ', array_unique($values));
    }

    private static function resolveEventCode(int $webFormId): string
    {
        if (class_exists('CForm')) {
            $formResult = \CForm::GetByID($webFormId);

            if (is_object($formResult)) {
                $form = $formResult->Fetch();
                $sid = trim((string)($form['SID'] ?? ''));

                if ($sid !== '') {
                    return $sid;
                }
            }
        }

        return (string)$webFormId;
    }
}
