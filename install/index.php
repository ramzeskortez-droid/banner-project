<?php
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;

class mycompany_banner extends CModule
{
    public $MODULE_ID = "mycompany.banner";
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = array();
        include(__DIR__ . "/version.php");
        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        $this->MODULE_NAME = "Модуль Баннеров";
        $this->MODULE_DESCRIPTION = "Модуль для управления баннерами";
        $this->PARTNER_NAME = "Моя Компания";
        $this->PARTNER_URI = "http://mycompany.ru";
    }

    public function DoInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);
        // Пересоздаем БД для применения изменений
        $this->UnInstallDB();
        $this->InstallDB();
        $this->InstallFiles();
        $this->RegisterEvents();
    }

    public function DoUninstall()
    {
        // Сначала удаляем все созданное модулем, потом его отключаем
        $this->UnInstallFiles();
        $this->UnInstallDB();
        $this->UnRegisterEvents();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
    
    public function InstallFiles()
    {
        // Копируем компоненты
        CopyDirFiles(
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/" . $this->MODULE_ID . "/install/components",
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/components",
            true, true
        );
        
        // Копируем админские страницы
        CopyDirFiles(
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/" . $this->MODULE_ID . "/admin",
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin",
            true, true
        );
        
        // Переименовываем файлы, чтобы они были уникальными в /bitrix/admin/
        if (file_exists($_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin/banner_constructor.php")) {
            rename(
                $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin/banner_constructor.php",
                $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin/mycompany_banner_constructor.php"
            );
        }
        if (file_exists($_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin/ajax_save_banner.php")) {
            rename(
                $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin/ajax_save_banner.php",
                $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin/mycompany_banner_ajax_save_banner.php"
            );
        }
        
        return true;
    }

    public function UnInstallFiles()
    {
        DeleteDirFilesEx("/bitrix/components/mycompany/banner");
        
        // Удаляем новые админские файлы
        DeleteDirFilesEx("/bitrix/admin/mycompany_banner_constructor.php");
        DeleteDirFilesEx("/bitrix/admin/mycompany_banner_ajax_save_banner.php");
        
        // Удаляем старые админские файлы на случай, если они остались
        DeleteDirFilesEx("/bitrix/admin/mycompany_banner_settings.php");
        DeleteDirFilesEx("/bitrix/admin/mycompany_banner_edit.php");
        
        return true;
    }

    public function InstallDB()
    {
        if (Loader::includeModule($this->MODULE_ID)) {
            try {
                \MyCompany\Banner\BannerTable::getEntity()->createDbTable();
                \MyCompany\Banner\BannerSetTable::getEntity()->createDbTable();
            } catch (\Exception $e) {
                // Игнорируем ошибку, если таблица уже существует
            }
        }
    }

    public function UnInstallDB()
    {
        if (Loader::includeModule($this->MODULE_ID)) {
            try {
                $connection = Application::getInstance()->getConnection();
                $connection->dropTable(\MyCompany\Banner\BannerTable::getTableName());
                $connection->dropTable(\MyCompany\Banner\BannerSetTable::getTableName());
            } catch (\Exception $e) {
                 // Игнорируем ошибку, если таблицы не существует
            }
        }
    }
    
    public function RegisterEvents() {
        $eventManager = EventManager::getInstance();
        $eventManager->registerEventHandler("main", "OnBuildGlobalMenu", $this->MODULE_ID, "\MyCompany\Banner\Event", "onBuildGlobalMenu");
    }

    public function UnRegisterEvents() {
        $eventManager = EventManager::getInstance();
        $eventManager->unRegisterEventHandler("main", "OnBuildGlobalMenu", $this->MODULE_ID, "\MyCompany\Banner\Event", "onBuildGlobalMenu");
    }
}