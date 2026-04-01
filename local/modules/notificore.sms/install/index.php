<?php

use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Notificore\Sms\Service\Container;

Loc::loadMessages(__FILE__);

require_once __DIR__ . '/../include.php';
require_once __DIR__ . '/version.php';

class notificore_sms extends CModule
{
    public $MODULE_ID = 'notificore.sms';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        global $arModuleVersion;

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('NOTIFICORE_SMS_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('NOTIFICORE_SMS_MODULE_DESCRIPTION');
        $this->PARTNER_NAME = Loc::getMessage('NOTIFICORE_SMS_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('NOTIFICORE_SMS_PARTNER_URI');
    }

    public function DoInstall(): void
    {
        ModuleManager::registerModule($this->MODULE_ID);
        $this->InstallDB();
        $this->InstallFiles();
        $this->InstallEvents();
        $this->InstallAgents();
    }

    public function DoUninstall(): void
    {
        $this->UnInstallAgents();
        $this->UnInstallEvents();
        $this->UnInstallFiles();
        $this->UnInstallDB();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    public function InstallDB(): void
    {
        Container::getInstance()->schemaManager()->ensure();
        Container::getInstance()->settingsRepository()->ensureDefaults();
    }

    public function UnInstallDB(): void
    {
        $connection = \Bitrix\Main\Application::getConnection();

        foreach (['b_notificore_reminders', 'b_notificore_logs', 'b_notificore_messages', 'b_notificore_event_rules', 'b_notificore_form_rules', 'b_notificore_settings'] as $tableName) {
            if ($connection->isTableExists($tableName)) {
                $connection->dropTable($tableName);
            }
        }
    }

    public function InstallFiles(): void
    {
        CopyDirFiles(__DIR__ . '/admin', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin', true, true);
        CopyDirFiles(__DIR__ . '/tools', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/tools', true, true);
    }

    public function UnInstallFiles(): void
    {
        DeleteDirFiles(__DIR__ . '/admin', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin');
        DeleteDirFilesEx('/bitrix/tools/notificore.sms');
    }

    public function InstallEvents(): void
    {
        EventManager::getInstance()->registerEventHandlerCompatible('main', 'OnBeforeEventAdd', $this->MODULE_ID, \Notificore\Sms\Event\MailEventHandler::class, 'onBeforeEventAdd');
        EventManager::getInstance()->registerEventHandler('sale', 'OnSaleOrderSaved', $this->MODULE_ID, \Notificore\Sms\Event\SaleOrderEventHandler::class, 'onSaleOrderSaved');
        EventManager::getInstance()->registerEventHandlerCompatible('form', 'onAfterResultAdd', $this->MODULE_ID, \Notificore\Sms\Event\FormEventHandler::class, 'onAfterResultAdd');
    }

    public function UnInstallEvents(): void
    {
        EventManager::getInstance()->unRegisterEventHandler('main', 'OnBeforeEventAdd', $this->MODULE_ID, \Notificore\Sms\Event\MailEventHandler::class, 'onBeforeEventAdd');
        EventManager::getInstance()->unRegisterEventHandler('sale', 'OnSaleOrderSaved', $this->MODULE_ID, \Notificore\Sms\Event\SaleOrderEventHandler::class, 'onSaleOrderSaved');
        EventManager::getInstance()->unRegisterEventHandler('form', 'onAfterResultAdd', $this->MODULE_ID, \Notificore\Sms\Event\FormEventHandler::class, 'onAfterResultAdd');
    }

    public function InstallAgents(): void
    {
        if (class_exists('CAgent')) {
            \CAgent::AddAgent('\\Notificore\\Sms\\Agent\\ReminderAgent::run();', $this->MODULE_ID, 'N', 60, '', 'Y');
            \CAgent::AddAgent('\\Notificore\\Sms\\Agent\\StatusSyncAgent::run();', $this->MODULE_ID, 'N', 300, '', 'Y');
        }
    }

    public function UnInstallAgents(): void
    {
        if (class_exists('CAgent')) {
            \CAgent::RemoveAgent('\\Notificore\\Sms\\Agent\\ReminderAgent::run();', $this->MODULE_ID);
            \CAgent::RemoveAgent('\\Notificore\\Sms\\Agent\\StatusSyncAgent::run();', $this->MODULE_ID);
        }
    }
}
