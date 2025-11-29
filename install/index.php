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
        $this->MODULE_NAME = "Модуль Баннеров (Конструктор)";
        $this->MODULE_DESCRIPTION = "Управление рекламными сетками";
        $this->PARTNER_NAME = "ООО ТлиЖу";
        $this->PARTNER_URI = "http://example.com";
    }

    public function DoInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);
        $this->InstallDB();
        $this->InstallFiles();
        $this->RegisterEvents();
    }

    public function DoUninstall()
    {
        $this->UnInstallFiles();
        $this->UnInstallDB();
        $this->UnRegisterEvents();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
    
    public function InstallFiles()
    {
        CopyDirFiles($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/" . $this->MODULE_ID . "/install/components", $_SERVER["DOCUMENT_ROOT"] . "/bitrix/components", true, true);
        CopyDirFiles($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/" . $this->MODULE_ID . "/admin", $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin", true, true);

        // Явное переименование файлов для админки
        $adminFiles = [
            'banner_settings.php'    => 'mycompany_banner_settings.php',
            'banner_constructor.php' => 'mycompany_banner_constructor.php',
            'ajax_save_banner.php'   => 'mycompany_banner_ajax_save_banner.php'
        ];

        foreach ($adminFiles as $orig => $target) {
            if (file_exists($_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin/" . $orig)) {
                rename($_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin/" . $orig, $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin/" . $target);
            }
        }
        return true;
    }

    public function UnInstallFiles()
    {
        DeleteDirFilesEx("/bitrix/components/mycompany/banner");
        DeleteDirFilesEx("/bitrix/admin/mycompany_banner_settings.php");
        DeleteDirFilesEx("/bitrix/admin/mycompany_banner_constructor.php");
        DeleteDirFilesEx("/bitrix/admin/mycompany_banner_ajax_save_banner.php");
        return true;
    }

    public function InstallDB()
    {
        if (Loader::includeModule($this->MODULE_ID)) {
            try {
                \MyCompany\Banner\BannerTable::getEntity()->createDbTable();
                \MyCompany\Banner\BannerSetTable::getEntity()->createDbTable();
                if (\MyCompany\Banner\BannerSetTable::getCount() == 0) {
                    \MyCompany\Banner\BannerSetTable::add(['NAME' => 'Главная страница']);
                }
            } catch (\Exception $e) {}
        }
    }

    public function UnInstallDB()
    {
        if (Loader::includeModule($this->MODULE_ID)) {
            $conn = Application::getInstance()->getConnection();
            $conn->dropTable(\MyCompany\Banner\BannerTable::getTableName());
            $conn->dropTable(\MyCompany\Banner\BannerSetTable::getTableName());
        }
    }

    public function RegisterEvents() {
        EventManager::getInstance()->registerEventHandler("main", "OnBuildGlobalMenu", $this->MODULE_ID, "\\MyCompany\\Banner\\Event", "onBuildGlobalMenu");
    }
    public function UnRegisterEvents() {
        EventManager::getInstance()->unRegisterEventHandler("main", "OnBuildGlobalMenu", $this->MODULE_ID, "\\MyCompany\\Banner\\Event", "onBuildGlobalMenu");
    }
}
