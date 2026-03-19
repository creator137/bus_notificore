# b24_notificore

Bitrix24 Market app skeleton для интеграции SMS через Notificore.

## Что уже есть
- `install.php` — dev/install flow
- `app.php` — настройки приложения
- `sms_handler.php` — обработчик отправки SMS
- `status_callback.php` — обработчик статусов
- `mock` и `real` режимы
- локальное хранение в `storage/` для dev

## Быстрый запуск

```bash
git clone https://github.com/creator137/b24_notificore.git
cd b24_notificore

composer install
cp .env.example .env
composer dump-autoload

php -S 127.0.0.1:8000 -t public

Открыть в браузере:

http://127.0.0.1:8000/install.php

Нажать «Симулировать установку», потом открыть:

http://127.0.0.1:8000/app.php
Режимы
Mock

Для локальной проверки без реального Notificore:

в app.php поставить mode = mock

сохранить настройки

выполнить тестовую отправку

Real

Для реального Notificore:

в app.php поставить mode = real

base_url = https://api.notificore.ru

auth_mode = bearer

api_key = ваш ключ

sms_send_path = /rest/sms/create

originator = ваш отправитель

Полезные адреса
/health.php
/install.php
/app.php
/sms_handler.php
/status_callback.php
