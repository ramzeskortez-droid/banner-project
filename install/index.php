<?php
use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use MyCompany\Banner\BannerSetTable;
use MyCompany\Banner\BannerTable;

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

        $adminFiles = [
            'banner_settings.php'    => 'mycompany_banner_settings.php',
            'banner_constructor.php' => 'mycompany_banner_constructor.php',
            'ajax_save_banner.php'   => 'mycompany_banner_ajax_save_banner.php',
            'tool_backup.php'        => 'mycompany_banner_backup.php'
        ];

        foreach ($adminFiles as $orig => $target) {
            $pathOrig = $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin/" . $orig;
            $pathTarget = $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin/" . $target;
            if (file_exists($pathOrig) && !file_exists($pathTarget)) {
                rename($pathOrig, $pathTarget);
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
        DeleteDirFilesEx("/bitrix/admin/mycompany_banner_backup.php");
        return true;
    }

    public function InstallDB()
    {
        if (Loader::includeModule($this->MODULE_ID)) {
            $conn = Application::getInstance()->getConnection();
            $conn->queryExecute('DROP TABLE IF EXISTS ' . BannerTable::getTableName());
            $conn->queryExecute('DROP TABLE IF EXISTS ' . BannerSetTable::getTableName());

            try {
                BannerTable::getEntity()->createDbTable();
                BannerSetTable::getEntity()->createDbTable();
                
                if (BannerSetTable::getCount() == 0) {
                    $this->smartFill();
                }
            } catch (\Exception $e) {
                // Log error
            }
        }
    }

    public function smartFill()
    {
        $res = BannerSetTable::add(['NAME' => 'Главная страница']);
        if (!$res->isSuccess()) {
            return;
        }
        $setId = $res->getId();

        if (!Loader::includeModule('iblock')) {
            return;
        }

        $sectionsRes = \CIBlockSection::GetList(
            ['ID' => 'ASC'],
            ['ACTIVE' => 'Y', 'GLOBAL_ACTIVE' => 'Y', 'CNT_ACTIVE' => 'Y'],
            false,
            ['ID', 'NAME', 'SECTION_PAGE_URL', 'DESCRIPTION'],
            ['nTopCount' => 8]
        );

        $slot = 1;
        $colors = ['#ff4d4d', '#ff9f43', '#feca57', '#1dd1a1', '#5f27cd', '#54a0ff', '#ff6b81', '#576574'];
        
        while ($section = $sectionsRes->Fetch()) {
            BannerTable::add([
                'SET_ID' => $setId,
                'SLOT_INDEX' => $slot,
                'TITLE' => $section['NAME'],
                'SUBTITLE' => TruncateText(strip_tags($section['DESCRIPTION']), 100),
                'LINK' => $section['SECTION_PAGE_URL'],
                'COLOR' => $colors[$slot - 1],
                'CATEGORY_ID' => $section['ID'],
            ]);
            $slot++;
        }
    }

    public function UnInstallDB()
    {
        if (Loader::includeModule($this->MODULE_ID)) {
            $conn = Application::getInstance()->getConnection();
            $conn->queryExecute('DROP TABLE IF EXISTS ' . BannerTable::getTableName());
            $conn->queryExecute('DROP TABLE IF EXISTS ' . BannerSetTable::getTableName());
        }
    }

    public function RegisterEvents() {
        EventManager::getInstance()->registerEventHandler("main", "OnBuildGlobalMenu", $this->MODULE_ID, "\\MyCompany\\Banner\\Event", "onBuildGlobalMenu");
    }

    public function UnRegisterEvents() {
        EventManager::getInstance()->unRegisterEventHandler("main", "OnBuildGlobalMenu", $this->MODULE_ID, "\\MyCompany\\Banner\\Event", "onBuildGlobalMenu");
    }
}
