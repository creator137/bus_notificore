<?php

namespace Notificore\Sms\Admin;

use Bitrix\Main\Application;
use Notificore\Sms\Helper\DateTimeHelper;
use Notificore\Sms\Helper\JsonHelper;
use Notificore\Sms\Helper\TextHelper;
use Notificore\Sms\Service\Container;
use RuntimeException;
use Throwable;

final class SettingsPage
{
    private array $feedback = [];
    private array $settings = [];
    private array $rules = [];
    private array $messages = [];
    private array $logs = [];
    private array $reminders = [];

    public function handleRequest(): void
    {
        global $APPLICATION;

        $APPLICATION->SetTitle('Notificore SMS для БУС');
        $container = Container::getInstance();
        $container->settingsRepository()->ensureDefaults();

        $request = Application::getInstance()->getContext()->getRequest();

        if ($request->isPost() && check_bitrix_sessid()) {
            $action = trim((string)$request->getPost('action'));

            try {
                match ($action) {
                    'save_settings' => $this->saveSettings(),
                    'check_connection' => $this->checkConnection(),
                    'test_send' => $this->testSend(),
                    'save_rule' => $this->saveRule(),
                    'delete_rule' => $this->deleteRule(),
                    'schedule_reminder' => $this->scheduleReminder(),
                    'sync_statuses' => $this->syncStatuses(),
                    default => null,
                };
            } catch (Throwable $exception) {
                $this->feedback = [
                    'type' => 'error',
                    'text' => TextHelper::humanizeError($exception->getMessage()),
                ];
            }
        }

        $this->settings = $container->settingsRepository()->getAll();
        $this->rules = $container->eventRuleRepository()->getAll();
        $this->messages = $container->messageRepository()->getRecent(50);
        $this->logs = $container->logRepository()->getRecent(50);
        $this->reminders = $container->reminderService()->getRecent(30);
    }

    public function render(): void
    {
        $request = Application::getInstance()->getContext()->getRequest();
        $callbackUrl = Container::getInstance()->settingsRepository()->buildCallbackUrl($this->settings);
        $isReady = trim((string)($this->settings['api_key'] ?? '')) !== '' && trim((string)($this->settings['originator'] ?? '')) !== '';
        ?>
        <style>
            .notificore-wrap{display:grid;gap:18px;margin-top:18px}
            .notificore-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
            .notificore-card{background:#fff;border:1px solid #dce3ea;border-radius:14px;padding:18px;box-shadow:0 1px 2px rgba(15,23,42,.04)}
            .notificore-card h2{margin:0 0 12px;font-size:20px}
            .notificore-card h3{margin:0 0 10px;font-size:16px}
            .notificore-muted{color:#6b7280}
            .notificore-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
            .notificore-summary-item{padding:12px;border:1px solid #e5e7eb;border-radius:10px;background:#f8fafc}
            .notificore-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
            .notificore-form-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
            .notificore-field label{display:block;font-weight:600;margin-bottom:6px}
            .notificore-field input[type=text],.notificore-field input[type=password],.notificore-field input[type=datetime-local],.notificore-field input[type=number],.notificore-field textarea,.notificore-field select{width:100%;min-height:39px;padding:8px 10px;border:1px solid #cdd5df;border-radius:8px;box-sizing:border-box}
            .notificore-field textarea{min-height:120px;resize:vertical}
            .notificore-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:14px}
            .notificore-note{margin-top:8px;font-size:12px;color:#6b7280}
            .notificore-table{width:100%;border-collapse:collapse;margin-top:12px}
            .notificore-table th,.notificore-table td{padding:10px;border-bottom:1px solid #e5e7eb;vertical-align:top;text-align:left}
            .notificore-table th{font-size:12px;text-transform:uppercase;color:#6b7280}
            .notificore-code{display:block;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;padding:10px;word-break:break-all;font-family:Consolas,monospace;white-space:pre-wrap}
            .notificore-pill{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;font-weight:600;font-size:12px}
            .notificore-pill.ok{background:#dcfce7;color:#166534}
            .notificore-pill.warn{background:#fef3c7;color:#92400e}
            .notificore-full{grid-column:1 / -1}
            @media (max-width: 1200px){.notificore-grid,.notificore-form-grid,.notificore-form-grid-3,.notificore-summary{grid-template-columns:1fr}}
        </style>
        <div class="notificore-wrap">
            <?php $this->renderFeedback(); ?>

            <div class="notificore-card">
                <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;">
                    <div>
                        <h2>Событийный модуль Notificore SMS</h2>
                        <div class="notificore-muted">Модуль реагирует на события сайта и отправляет SMS через реальный REST API Notificore. Тексты сообщений меняются в админке, история и статусы хранятся в БД БУС.</div>
                    </div>
                    <span class="notificore-pill <?= $isReady ? 'ok' : 'warn' ?>"><?= $isReady ? 'API настроен' : 'Нужно заполнить API и sender' ?></span>
                </div>
                <div class="notificore-summary" style="margin-top:14px;">
                    <div class="notificore-summary-item">
                        <div class="notificore-muted">Режим</div>
                        <div style="margin-top:6px;font-weight:700;"><?= ($this->settings['active'] ?? 'Y') === 'Y' ? 'Интеграция включена' : 'Интеграция выключена' ?></div>
                    </div>
                    <div class="notificore-summary-item">
                        <div class="notificore-muted">Callback URL</div>
                        <div style="margin-top:6px;font-weight:700;"><?= $this->e($callbackUrl !== '' ? $callbackUrl : 'Укажите внешний URL сайта') ?></div>
                    </div>
                    <div class="notificore-summary-item">
                        <div class="notificore-muted">Поддерживаемые события</div>
                        <div style="margin-top:6px;font-weight:700;">Заказы, обращения, напоминания, кастомные события</div>
                    </div>
                </div>
            </div>

            <div class="notificore-grid">
                <div class="notificore-card">
                    <h2>Настройки подключения</h2>
                    <form method="post">
                        <?= bitrix_sessid_post() ?>
                        <input type="hidden" name="action" value="save_settings">
                        <div class="notificore-form-grid">
                            <div class="notificore-field">
                                <label><input type="checkbox" name="active" value="Y" <?= ($this->settings['active'] ?? 'Y') === 'Y' ? 'checked' : '' ?>> Интеграция включена</label>
                            </div>
                            <div class="notificore-field">
                                <label><input type="checkbox" name="verify_ssl" value="Y" <?= ($this->settings['verify_ssl'] ?? 'Y') === 'Y' ? 'checked' : '' ?>> Проверять SSL-сертификаты</label>
                            </div>
                            <div class="notificore-field">
                                <label for="api_key">API-ключ Notificore</label>
                                <input id="api_key" type="password" name="api_key" value="" placeholder="Оставьте пустым, чтобы не менять текущий ключ">
                            </div>
                            <div class="notificore-field">
                                <label for="originator">Имя отправителя</label>
                                <input id="originator" type="text" name="originator" value="<?= $this->e($this->settings['originator'] ?? '') ?>">
                                <div class="notificore-note">Используйте sender, который разрешён в кабинете Notificore.</div>
                            </div>
                        </div>
                        <div class="notificore-form-grid-3" style="margin-top:14px;">
                            <div class="notificore-field">
                                <label for="base_url">Базовый URL API</label>
                                <input id="base_url" type="text" name="base_url" value="<?= $this->e($this->settings['base_url'] ?? '') ?>">
                            </div>
                            <div class="notificore-field">
                                <label for="api_key_header">Заголовок API-ключа</label>
                                <input id="api_key_header" type="text" name="api_key_header" value="<?= $this->e($this->settings['api_key_header'] ?? 'X-API-KEY') ?>">
                            </div>
                            <div class="notificore-field">
                                <label for="site_base_url">Внешний URL сайта</label>
                                <input id="site_base_url" type="text" name="site_base_url" value="<?= $this->e($this->settings['site_base_url'] ?? '') ?>" placeholder="https://site.ru">
                            </div>
                        </div>
                        <div class="notificore-form-grid-3" style="margin-top:14px;">
                            <div class="notificore-field">
                                <label for="sms_send_path">Путь отправки SMS</label>
                                <input id="sms_send_path" type="text" name="sms_send_path" value="<?= $this->e($this->settings['sms_send_path'] ?? '/v1.0/sms/create') ?>">
                            </div>
                            <div class="notificore-field">
                                <label for="balance_path">Путь проверки баланса</label>
                                <input id="balance_path" type="text" name="balance_path" value="<?= $this->e($this->settings['balance_path'] ?? '/rest/common/balance') ?>">
                            </div>
                            <div class="notificore-field">
                                <label for="callback_token">Токен callback</label>
                                <input id="callback_token" type="text" name="callback_token" value="<?= $this->e($this->settings['callback_token'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="notificore-form-grid-3" style="margin-top:14px;">
                            <div class="notificore-field">
                                <label for="sms_status_path">Путь статуса по ID</label>
                                <input id="sms_status_path" type="text" name="sms_status_path" value="<?= $this->e($this->settings['sms_status_path'] ?? '/v1.0/sms/{id}') ?>">
                            </div>
                            <div class="notificore-field">
                                <label for="sms_status_reference_path">Путь статуса по reference</label>
                                <input id="sms_status_reference_path" type="text" name="sms_status_reference_path" value="<?= $this->e($this->settings['sms_status_reference_path'] ?? '/v1.0/sms/reference/{reference}') ?>">
                            </div>
                            <div class="notificore-field">
                                <label for="validity">Срок жизни SMS</label>
                                <input id="validity" type="number" min="1" max="72" name="validity" value="<?= $this->e($this->settings['validity'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="notificore-form-grid" style="margin-top:14px;">
                            <div class="notificore-field">
                                <label for="tariff">Тариф</label>
                                <input id="tariff" type="number" min="0" max="9" name="tariff" value="<?= $this->e($this->settings['tariff'] ?? '') ?>">
                            </div>
                            <div class="notificore-field">
                                <label>URL для callback статусов</label>
                                <span class="notificore-code"><?= $this->e($callbackUrl !== '' ? $callbackUrl : 'Заполните внешний URL сайта, чтобы сформировать callback.') ?></span>
                            </div>
                        </div>
                        <div class="notificore-actions">
                            <button class="adm-btn adm-btn-save" type="submit">Сохранить настройки</button>
                        </div>
                    </form>
                </div>

                <div class="notificore-card">
                    <h2>Проверка и тесты</h2>
                    <form method="post">
                        <?= bitrix_sessid_post() ?>
                        <input type="hidden" name="action" value="check_connection">
                        <div class="notificore-note">Проверка использует реальный endpoint баланса Notificore.</div>
                        <div class="notificore-actions">
                            <button class="adm-btn" type="submit">Проверить подключение</button>
                        </div>
                    </form>

                    <hr style="margin:18px 0;border:none;border-top:1px solid #e5e7eb;">

                    <form method="post">
                        <?= bitrix_sessid_post() ?>
                        <input type="hidden" name="action" value="test_send">
                        <div class="notificore-field">
                            <label for="test_phone">Телефон</label>
                            <input id="test_phone" type="text" name="test_phone" value="<?= $this->e((string)$request->getPost('test_phone')) ?>" placeholder="+7 (900) 000-00-00">
                        </div>
                        <div class="notificore-field" style="margin-top:14px;">
                            <label for="test_message">Текст SMS</label>
                            <textarea id="test_message" name="test_message"><?= $this->e((string)($request->getPost('test_message') ?: 'Спасибо за обращение в службу заботы застройщика «Атмосфера». Наш специалист свяжется с вами в течение 4 рабочих дней.')) ?></textarea>
                        </div>
                        <div class="notificore-actions">
                            <button class="adm-btn adm-btn-save" type="submit">Отправить тестовую SMS</button>
                        </div>
                    </form>

                    <hr style="margin:18px 0;border:none;border-top:1px solid #e5e7eb;">

                    <form method="post">
                        <?= bitrix_sessid_post() ?>
                        <input type="hidden" name="action" value="schedule_reminder">
                        <h3>Тестовое напоминание</h3>
                        <div class="notificore-form-grid-3">
                            <div class="notificore-field">
                                <label for="reminder_event_code">Код напоминания</label>
                                <input id="reminder_event_code" type="text" name="reminder_event_code" value="<?= $this->e((string)$request->getPost('reminder_event_code') ?: 'care_follow_up') ?>">
                            </div>
                            <div class="notificore-field">
                                <label for="reminder_phone">Телефон</label>
                                <input id="reminder_phone" type="text" name="reminder_phone" value="<?= $this->e((string)$request->getPost('reminder_phone')) ?>">
                            </div>
                            <div class="notificore-field">
                                <label for="reminder_send_at">Когда отправить</label>
                                <input id="reminder_send_at" type="datetime-local" name="reminder_send_at" value="<?= $this->e((string)$request->getPost('reminder_send_at')) ?>">
                            </div>
                        </div>
                        <div class="notificore-actions">
                            <button class="adm-btn" type="submit">Поставить в очередь</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="notificore-card">
                <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;">
                    <div>
                        <h2>Правила событий</h2>
                        <div class="notificore-muted">Тут настраиваются все SMS-реакции. Текст можно менять в любой момент без правок кода.</div>
                    </div>
                    <span class="notificore-pill <?= $isReady ? 'ok' : 'warn' ?>"><?= $isReady ? 'Можно настраивать правила' : 'Сначала заполните API-ключ и sender' ?></span>
                </div>
                <table class="notificore-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Событие</th>
                        <th>Код</th>
                        <th>Телефон</th>
                        <th>Текст</th>
                        <th>External ID</th>
                        <th>Статус</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($this->rules === []): ?>
                        <tr><td colspan="8" class="notificore-muted">Правила ещё не созданы.</td></tr>
                    <?php else: ?>
                        <?php foreach ($this->rules as $rule): ?>
                            <tr>
                                <td><?= (int)$rule['id'] ?></td>
                                <td><?= $this->e(TextHelper::eventTypeLabel((string)$rule['event_type'])) ?></td>
                                <td><?= $this->e((string)$rule['event_code'] !== '' ? (string)$rule['event_code'] : '*') ?></td>
                                <td><?= $this->e((string)$rule['phone_path']) ?></td>
                                <td><?= $this->e(TextHelper::preview((string)$rule['message_template'], 170)) ?></td>
                                <td><?= $this->e((string)$rule['external_id_template'] !== '' ? (string)$rule['external_id_template'] : 'авто') ?></td>
                                <td><?= $this->e((string)$rule['active'] === 'Y' ? 'Активно' : 'Выключено') ?></td>
                                <td>
                                    <form method="post" style="margin:0;">
                                        <?= bitrix_sessid_post() ?>
                                        <input type="hidden" name="action" value="delete_rule">
                                        <input type="hidden" name="rule_id" value="<?= (int)$rule['id'] ?>">
                                        <button class="adm-btn" type="submit">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>

                <form method="post" style="margin-top:18px;">
                    <?= bitrix_sessid_post() ?>
                    <input type="hidden" name="action" value="save_rule">
                    <div class="notificore-form-grid-3">
                        <div class="notificore-field">
                            <label for="event_type">Тип события</label>
                            <select id="event_type" name="event_type">
                                <?php foreach ($this->eventTypeOptions() as $value => $label): ?>
                                    <option value="<?= $this->e($value) ?>" <?= $this->posted('event_type', 'mail_event') === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="notificore-field">
                            <label for="event_code">Код события</label>
                            <input id="event_code" type="text" name="event_code" value="<?= $this->e((string)$this->posted('event_code')) ?>" placeholder="MAIL_EVENT / care_follow_up / s1 / *">
                        </div>
                        <div class="notificore-field">
                            <label for="phone_path">Путь к телефону</label>
                            <input id="phone_path" type="text" name="phone_path" value="<?= $this->e((string)$this->posted('phone_path', 'PHONE')) ?>" placeholder="PHONE или PROPERTIES.PHONE">
                        </div>
                    </div>
                    <div class="notificore-form-grid-3" style="margin-top:14px;">
                        <div class="notificore-field">
                            <label for="description">Описание</label>
                            <input id="description" type="text" name="description" value="<?= $this->e((string)$this->posted('description', 'Авто-SMS по событию')) ?>">
                        </div>
                        <div class="notificore-field">
                            <label for="external_id_template">Шаблон external_id</label>
                            <input id="external_id_template" type="text" name="external_id_template" value="<?= $this->e((string)$this->posted('external_id_template')) ?>" placeholder="{EVENT_TYPE}:{EVENT_CODE}:{ORDER_ID}:{RULE_ID}">
                        </div>
                        <div class="notificore-field">
                            <label><input type="checkbox" name="rule_active" value="Y" <?= $this->posted('rule_active', 'Y') === 'Y' ? 'checked' : '' ?>> Правило активно</label>
                        </div>
                    </div>
                    <div class="notificore-form-grid" style="margin-top:14px;">
                        <div class="notificore-field">
                            <label for="message_template">Текст SMS</label>
                            <textarea id="message_template" name="message_template"><?= $this->e((string)$this->posted('message_template', 'Спасибо за обращение в службу заботы застройщика «Атмосфера». Наш специалист свяжется с вами в течение 4 рабочих дней.')) ?></textarea>
                            <div class="notificore-note">Текст редактируется здесь же, без правок кода. Можно использовать плейсхолдеры вроде <code>{PHONE}</code>, <code>{ORDER_ID}</code>, <code>{EVENT_CODE}</code>, <code>{PROPERTIES.PHONE}</code>.</div>
                        </div>
                        <div class="notificore-field">
                            <label>Подсказка по типам</label>
                                <div class="notificore-summary-item">
                                    <div><strong>Создание заказа</strong>: тип <code>sale_order_created</code>, код можно оставить пустым или указать <code>s1</code>.</div>
                                    <div class="notificore-note"><strong>Обращения</strong>: тип <code>mail_event</code>, код = имя почтового события Bitrix.</div>
                                    <div class="notificore-note"><strong>Веб-формы</strong>: тип <code>form_result_added</code>, код = SID формы (или ID, если SID пустой).</div>
                                    <div class="notificore-note"><strong>Напоминания</strong>: тип <code>reminder</code>, код = произвольный код напоминания.</div>
                                    <div class="notificore-note"><strong>Кастомное событие</strong>: тип <code>custom_event</code>, вызывается через API модуля.</div>
                                </div>
                        </div>
                    </div>
                    <div class="notificore-actions">
                        <button class="adm-btn adm-btn-save" type="submit">Сохранить правило</button>
                    </div>
                </form>
            </div>

            <div class="notificore-card">
                <h2>API для разработчика</h2>
                <div class="notificore-form-grid">
                    <div>
                        <h3>Кастомное событие</h3>
                        <span class="notificore-code">\Bitrix\Main\Loader::includeModule('notificore.sms');
\Notificore\Sms\Facade\ModuleApi::triggerCustom('care_service', [
    'PHONE' => '+79582431074',
    'NAME' => 'Иван',
    'ENTITY_ID' => 15,
]);</span>
                    </div>
                    <div>
                        <h3>Постановка напоминания</h3>
                        <span class="notificore-code">\Bitrix\Main\Loader::includeModule('notificore.sms');
\Notificore\Sms\Facade\ModuleApi::scheduleReminder(
    'care_follow_up',
    '+79582431074',
    '+2 days',
    ['NAME' => 'Иван', 'ENTITY_ID' => 15],
    'care-follow-up-15'
);</span>
                    </div>
                </div>
            </div>

            <div class="notificore-card">
                <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;">
                    <div>
                        <h2>История сообщений</h2>
                        <div class="notificore-muted">Последние 50 отправок и обновлений статусов.</div>
                    </div>
                    <form method="post" style="margin:0;display:flex;gap:10px;align-items:center;">
                        <?= bitrix_sessid_post() ?>
                        <input type="hidden" name="action" value="sync_statuses">
                        <input type="number" name="sync_limit" min="1" max="100" value="20" style="width:90px;min-height:39px;padding:8px 10px;border:1px solid #cdd5df;border-radius:8px;">
                        <button class="adm-btn" type="submit">Обновить статусы</button>
                    </form>
                </div>
                <table class="notificore-table">
                    <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Источник</th>
                        <th>Телефон</th>
                        <th>Сообщение</th>
                        <th>Статус</th>
                        <th>Событие</th>
                        <th>Провайдер</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($this->messages === []): ?>
                        <tr><td colspan="7" class="notificore-muted">История пока пуста.</td></tr>
                    <?php else: ?>
                        <?php foreach ($this->messages as $message): ?>
                            <?php $meta = TextHelper::statusMeta((string)$message['status']); ?>
                            <tr>
                                <td><?= $this->e(DateTimeHelper::formatForUi((string)$message['created_at'])) ?></td>
                                <td><?= $this->e(TextHelper::sourceLabel((string)$message['source'])) ?><?= !empty($message['is_test']) ? ' / тест' : '' ?></td>
                                <td><?= $this->e((string)$message['phone']) ?></td>
                                <td><?= $this->e(TextHelper::preview((string)$message['message'], 150)) ?></td>
                                <td>
                                    <span class="<?= $this->e($meta['tone']) ?>" style="display:inline-block;padding:4px 8px;border-radius:6px;"><?= $this->e($meta['label']) ?></span>
                                    <?php if ((string)$message['error_message'] !== ''): ?>
                                        <div class="notificore-note"><?= $this->e(TextHelper::humanizeError((string)$message['error_message'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= $this->e($this->messageContext($message)) ?></td>
                                <td>
                                    <div>ID: <?= $this->e((string)($message['provider_message_id'] ?: '—')) ?></div>
                                    <div class="notificore-note">Ref: <?= $this->e((string)($message['provider_reference'] ?: '—')) ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="notificore-grid">
                <div class="notificore-card">
                    <h2>Очередь напоминаний</h2>
                    <table class="notificore-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Код</th>
                            <th>Телефон</th>
                            <th>Когда</th>
                            <th>Статус</th>
                            <th>Ошибка</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($this->reminders === []): ?>
                            <tr><td colspan="6" class="notificore-muted">Напоминаний пока нет.</td></tr>
                        <?php else: ?>
                            <?php foreach ($this->reminders as $reminder): ?>
                                <tr>
                                    <td><?= (int)$reminder['id'] ?></td>
                                    <td><?= $this->e((string)$reminder['event_code']) ?></td>
                                    <td><?= $this->e((string)$reminder['phone']) ?></td>
                                    <td><?= $this->e(DateTimeHelper::formatForUi((string)$reminder['send_at'])) ?></td>
                                    <td><?= $this->e((string)$reminder['status']) ?></td>
                                    <td><?= $this->e(TextHelper::preview((string)$reminder['error_message'], 120)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="notificore-card">
                    <h2>Логи</h2>
                    <table class="notificore-table">
                        <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Уровень</th>
                            <th>Событие</th>
                            <th>Сообщение</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($this->logs === []): ?>
                            <tr><td colspan="4" class="notificore-muted">Логи пока отсутствуют.</td></tr>
                        <?php else: ?>
                            <?php foreach ($this->logs as $log): ?>
                                <tr>
                                    <td><?= $this->e(DateTimeHelper::formatForUi((string)$log['created_at'])) ?></td>
                                    <td><?= $this->e((string)$log['level']) ?></td>
                                    <td><?= $this->e((string)$log['event_type']) ?></td>
                                    <td>
                                        <div><?= $this->e((string)$log['message']) ?></div>
                                        <div class="notificore-note"><?= $this->e(JsonHelper::encode($log['context'])) ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    private function saveSettings(): void
    {
        $request = Application::getInstance()->getContext()->getRequest();
        $repository = Container::getInstance()->settingsRepository();
        $current = $repository->getAll();
        $apiKey = trim((string)$request->getPost('api_key'));
        $callbackToken = trim((string)$request->getPost('callback_token'));

        $repository->save([
            'active' => $request->getPost('active') === 'Y' ? 'Y' : 'N',
            'verify_ssl' => $request->getPost('verify_ssl') === 'Y' ? 'Y' : 'N',
            'api_key' => $apiKey !== '' ? $apiKey : (string)($current['api_key'] ?? ''),
            'originator' => trim((string)$request->getPost('originator')),
            'base_url' => trim((string)$request->getPost('base_url')) ?: 'https://api.notificore.ru',
            'api_key_header' => trim((string)$request->getPost('api_key_header')) ?: 'X-API-KEY',
            'site_base_url' => trim((string)$request->getPost('site_base_url')),
            'sms_send_path' => trim((string)$request->getPost('sms_send_path')) ?: '/v1.0/sms/create',
            'balance_path' => trim((string)$request->getPost('balance_path')) ?: '/rest/common/balance',
            'sms_status_path' => trim((string)$request->getPost('sms_status_path')) ?: '/v1.0/sms/{id}',
            'sms_status_reference_path' => trim((string)$request->getPost('sms_status_reference_path')) ?: '/v1.0/sms/reference/{reference}',
            'validity' => trim((string)$request->getPost('validity')),
            'tariff' => trim((string)$request->getPost('tariff')),
            'callback_token' => $callbackToken !== '' ? $callbackToken : (string)($current['callback_token'] ?? ''),
        ]);

        $this->feedback = ['type' => 'ok', 'text' => 'Настройки модуля сохранены.'];
    }

    private function checkConnection(): void
    {
        $result = Container::getInstance()->clientFactory()->create()->getBalance();

        if (($result['success'] ?? false) === true) {
            $balance = trim((string)($result['balance'] ?? ''));
            $currency = trim((string)($result['currency'] ?? ''));
            $this->feedback = [
                'type' => 'ok',
                'text' => $balance !== '' ? 'Подключение успешно. Баланс: ' . $balance . ' ' . $currency : 'Подключение к Notificore подтверждено.',
            ];
            return;
        }

        $this->feedback = ['type' => 'error', 'text' => TextHelper::humanizeError((string)($result['error_message'] ?? 'Не удалось проверить подключение.'))];
    }

    private function testSend(): void
    {
        $request = Application::getInstance()->getContext()->getRequest();
        $result = Container::getInstance()->smsDispatchService()->send([
            'phone' => trim((string)$request->getPost('test_phone')),
            'message' => trim((string)$request->getPost('test_message')),
            'source' => 'manual_ui',
            'is_test' => true,
            'force' => true,
        ]);

        if (($result['success'] ?? false) === true) {
            $this->feedback = ['type' => 'ok', 'text' => 'Тестовая SMS отправлена и записана в историю.'];
            return;
        }

        $this->feedback = ['type' => 'error', 'text' => TextHelper::humanizeError((string)($result['error_message'] ?? 'Не удалось отправить тестовую SMS.'))];
    }

    private function saveRule(): void
    {
        $request = Application::getInstance()->getContext()->getRequest();
        $eventType = trim((string)$request->getPost('event_type'));
        $phonePath = trim((string)$request->getPost('phone_path'));
        $messageTemplate = trim((string)$request->getPost('message_template'));

        if ($eventType === '') {
            throw new RuntimeException('Укажите тип события.');
        }

        if ($phonePath === '' || $messageTemplate === '') {
            throw new RuntimeException('Заполните путь к телефону и текст SMS.');
        }

        $ruleId = Container::getInstance()->eventRuleRepository()->save([
            'active' => $request->getPost('rule_active') === 'Y' ? 'Y' : 'N',
            'event_type' => $eventType,
            'event_code' => trim((string)$request->getPost('event_code')),
            'description' => trim((string)$request->getPost('description')),
            'phone_path' => $phonePath,
            'message_template' => $messageTemplate,
            'external_id_template' => trim((string)$request->getPost('external_id_template')),
        ]);

        $this->feedback = ['type' => 'ok', 'text' => 'Правило сохранено. ID: ' . $ruleId . '.'];
    }

    private function deleteRule(): void
    {
        $ruleId = (int)Application::getInstance()->getContext()->getRequest()->getPost('rule_id');
        Container::getInstance()->eventRuleRepository()->delete($ruleId);
        $this->feedback = ['type' => 'ok', 'text' => 'Правило удалено.'];
    }

    private function scheduleReminder(): void
    {
        $request = Application::getInstance()->getContext()->getRequest();
        $eventCode = trim((string)$request->getPost('reminder_event_code'));
        $phone = trim((string)$request->getPost('reminder_phone'));
        $sendAt = trim((string)$request->getPost('reminder_send_at'));

        if ($eventCode === '' || $phone === '' || $sendAt === '') {
            throw new RuntimeException('Для напоминания нужны код, телефон и дата отправки.');
        }

        $reminderId = Container::getInstance()->reminderService()->schedule($eventCode, $phone, $sendAt, [
            'EVENT_CODE' => $eventCode,
            'PHONE' => $phone,
        ]);

        $this->feedback = ['type' => 'ok', 'text' => 'Напоминание поставлено в очередь. ID: ' . $reminderId . '.'];
    }

    private function syncStatuses(): void
    {
        $limit = max(1, min(100, (int)Application::getInstance()->getContext()->getRequest()->getPost('sync_limit')));
        $result = Container::getInstance()->statusService()->syncPending($limit);
        $this->feedback = [
            'type' => 'ok',
            'text' => sprintf('Синхронизация завершена: обновлено %d, пропущено %d.', (int)($result['updated_count'] ?? 0), (int)($result['skipped_count'] ?? 0)),
        ];
    }

    private function renderFeedback(): void
    {
        if ($this->feedback === []) {
            return;
        }

        $className = match ($this->feedback['type']) {
            'ok' => 'adm-info-message-wrap adm-info-message-green',
            'error' => 'adm-info-message-wrap adm-info-message-red',
            default => 'adm-info-message-wrap adm-info-message',
        };

        echo '<div class="' . $this->e($className) . '">'
            . '<div class="adm-info-message">'
            . '<div class="adm-info-message-title">' . $this->e((string)$this->feedback['text']) . '</div>'
            . '<div class="adm-info-message-icon"></div>'
            . '</div>'
            . '</div>';
    }

    private function messageContext(array $message): string
    {
        $parts = [];

        if ((string)($message['event_type'] ?? '') !== '') {
            $parts[] = TextHelper::eventTypeLabel((string)$message['event_type']);
        }

        if ((string)($message['event_code'] ?? '') !== '') {
            $parts[] = 'code=' . (string)$message['event_code'];
        }

        if ((int)($message['rule_id'] ?? 0) > 0) {
            $parts[] = 'rule=' . (int)$message['rule_id'];
        }

        if ((string)($message['external_id'] ?? '') !== '') {
            $parts[] = 'external=' . (string)$message['external_id'];
        }

        return $parts === [] ? '—' : implode('; ', $parts);
    }

    private function eventTypeOptions(): array
    {
        return [
            'sale_order_created' => 'Создание заказа',
            'mail_event' => 'Почтовое событие',
            'form_result_added' => 'Результат веб-формы',
            'reminder' => 'Напоминание',
            'custom_event' => 'Кастомное событие',
        ];
    }

    private function posted(string $name, string $default = ''): string
    {
        $request = Application::getInstance()->getContext()->getRequest();
        $value = $request->getPost($name);

        if ($value === null || $value === '') {
            return $default;
        }

        return trim((string)$value);
    }

    private function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
