# Notificore SMS for BUS

Модуль расположен в `local/modules/notificore.sms` и представляет собой событийную BUS-версию интеграции с Notificore без внешнего app server и без моков.

## Что делает модуль

- хранит настройки интеграции в БД сайта
- отправляет SMS через реальный REST API Notificore
- хранит историю сообщений, статусы и логи
- реагирует на события сайта
- позволяет менять тексты SMS в админке без правок кода
- поддерживает очередь напоминаний через агент

## Поддерживаемые сценарии

- `sale_order_created` — создание заказа
- `mail_event` — почтовые события Bitrix
- `form_result_added` — отправка веб-формы Bitrix
- `reminder` — отложенные напоминания
- `custom_event` — произвольные события через API модуля

## Структура

- `admin/` — административные файлы модуля
- `install/` — установка, копирование admin/tools, регистрация событий и агента
- `lang/` — языковые файлы
- `lib/Admin/` — страница настроек и правил
- `lib/Agent/` — агент напоминаний
- `lib/Event/` — обработчики событий Bitrix
- `lib/Facade/` — прямой API модуля
- `lib/Helper/` — утилиты
- `lib/Http/` — HTTP-клиент
- `lib/Integration/Notificore/` — клиент API Notificore
- `lib/Orm/` — ORM-таблицы
- `lib/Repository/` — репозитории
- `lib/Service/` — бизнес-логика модуля

## Установка

1. Скопировать каталог `local/modules/notificore.sms` в проект на БУС.
2. Установить модуль из списка локальных модулей.
3. Открыть `/bitrix/admin/notificore_sms.php`.
4. Заполнить API-ключ, sender и внешний URL сайта.
5. Сохранить настройки и проверить подключение.
6. Создать правила событий и при необходимости настроить callback в кабинете Notificore.

## Правила событий

Для каждого правила задаются:
- тип события
- код события
- путь к телефону в payload события
- текст SMS
- шаблон external_id для защиты от дублей
- включение и описание

Текст SMS меняется прямо в правилах модуля, без изменения PHP-кода.

## Примеры интеграции

### Кастомное событие

```php
\Bitrix\Main\Loader::includeModule('notificore.sms');

\Notificore\Sms\Facade\ModuleApi::triggerCustom('care_service', [
    'PHONE' => '+79582431074',
    'NAME' => 'Иван',
    'ENTITY_ID' => 15,
]);
```

### Напоминание

```php
\Bitrix\Main\Loader::includeModule('notificore.sms');

\Notificore\Sms\Facade\ModuleApi::scheduleReminder(
    'care_follow_up',
    '+79582431074',
    '+2 days',
    ['NAME' => 'Иван', 'ENTITY_ID' => 15],
    'care-follow-up-15'
);
```

## Notificore API

По официальной документации используются:
- `POST /v1.0/sms/create` — отправка SMS
- `GET /v1.0/sms/{id}` — статус по ID
- `GET /v1.0/sms/reference/{reference}` — статус по reference
- `GET /rest/common/balance` — баланс

Авторизация — через `X-API-KEY`.
