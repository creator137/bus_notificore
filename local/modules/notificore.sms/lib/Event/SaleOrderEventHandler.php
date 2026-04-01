<?php

namespace Notificore\Sms\Event;

use Bitrix\Main\Event;
use Bitrix\Sale\Order;
use Notificore\Sms\Service\Container;
use Throwable;

final class SaleOrderEventHandler
{
    public static function onSaleOrderSaved(mixed ...$arguments): void
    {
        try {
            $event = self::resolveEvent($arguments);

            if ($event === null) {
                return;
            }

            Container::getInstance()->saleOrderEventService()->handleOrderSaved($event);
        } catch (Throwable $exception) {
            Container::getInstance()->logRepository()->add('error', 'sale_order_handler_error', 'Ошибка обработчика OnSaleOrderSaved.', [
                'error_message' => $exception->getMessage(),
            ]);
        }
    }

    private static function resolveEvent(array $arguments): ?Event
    {
        $first = $arguments[0] ?? null;

        if ($first instanceof Event) {
            return $first;
        }

        if ($first instanceof Order) {
            return new Event('sale', 'OnSaleOrderSaved', [
                'ENTITY' => $first,
                'IS_NEW' => (bool)($arguments[1] ?? false),
            ]);
        }

        return null;
    }
}
