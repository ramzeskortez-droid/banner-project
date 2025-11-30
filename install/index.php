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
        $this->InstallFiles();
        $this->InstallDB();
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
        $adminFiles = [
            'mycompany_banner_settings.php'    => 'admin/banner_settings.php',
            'mycompany_banner_constructor.php' => 'admin/banner_constructor.php',
            'mycompany_banner_ajax_save_banner.php'  => 'admin/ajax_save_banner.php'
        ];
        
        $moduleRootPath = dirname(__DIR__);

        foreach ($adminFiles as $targetName => $sourceRelativePath) {
            $sourceAbsolutePath = $moduleRootPath . '/' . $sourceRelativePath;
            $sourceAbsolutePath = str_replace('\\', '/', $sourceAbsolutePath); // Normalize for require
            $proxyContent = '<?php require("' . $sourceAbsolutePath . '");?>';
            file_put_contents(Application::getDocumentRoot() . "/bitrix/admin/" . $targetName, $proxyContent);
        }

        CopyDirFiles(
            dirname(__FILE__) . "/components",
            Application::getDocumentRoot() . "/bitrix/components",
            true, true
        );
        return true;
    }

    public function UnInstallFiles()
    {
        $adminProxyFiles = ['mycompany_banner_settings.php', 'mycompany_banner_constructor.php', 'mycompany_banner_ajax_save_banner.php'];
        foreach ($adminProxyFiles as $fileName) {
            $file = Application::getDocumentRoot() . "/bitrix/admin/" . $fileName;
            if(file_exists($file)) unlink($file);
        }
        
        DeleteDirFilesEx("/bitrix/components/mycompany/banner");
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
                $this->createDemoData();
            } catch (\Exception $e) { /* Log error */ }
        }
    }

    private function createDemoData()
    {
        $setResult = BannerSetTable::add(['NAME' => 'Демо-набор']);
        if (!$setResult->isSuccess()) return;
        $setId = $setResult->getId();

        if (!Loader::includeModule('iblock')) return;
        
        $iblockRes = null;
        if (Loader::includeModule('catalog')) {
             $catalogs = \Bitrix\Catalog\CatalogIblockTable::getList([
                'select' => ['IBLOCK_ID'],
                'filter' => []
            ])->fetch();
            if ($catalogs) {
                $iblockRes = \CIBlock::GetList([], ['ID' => $catalogs['IBLOCK_ID'], 'ACTIVE' => 'Y'])->Fetch();
            }
        }
        
        if (!$iblockRes) {
            $iblockRes = \CIBlock::GetList([], ['ACTIVE' => 'Y', 'TYPE' => 'catalog'], false)->Fetch();
             if (!$iblockRes) {
                 $iblockRes = \CIBlock::GetList([], ['ACTIVE' => 'Y'], false)->Fetch();
            }
        }

        if (!$iblockRes) return;

        $sectionsRes = \CIBlockSection::GetList(
            ['SORT' => 'ASC'], 
            ['IBLOCK_ID' => $iblockRes['ID'], 'ACTIVE' => 'Y', 'CNT_ACTIVE' => 'Y'], 
            false, 
            ['ID', 'NAME', 'SECTION_PAGE_URL', 'DESCRIPTION'], 
            ['nTopCount' => 8]
        );

        $slot = 1;
        while ($section = $sectionsRes->Fetch()) {
            BannerTable::add([
                'SET_ID' => $setId, 
                'SLOT_INDEX' => $slot++, 
                'TITLE' => $section['NAME'], 
                'LINK' => $section['SECTION_PAGE_URL'],
                'SUBTITLE' => TruncateText(strip_tags($section['DESCRIPTION']), 100), 
                'CATEGORY_ID' => $section['ID'],
            ]);
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

    public function RegisterEvents() { EventManager::getInstance()->registerEventHandler("main", "OnBuildGlobalMenu", $this->MODULE_ID, "\\MyCompany\\Banner\\Event", "onBuildGlobalMenu"); }
    public function UnRegisterEvents() { EventManager::getInstance()->unRegisterEventHandler("main", "OnBuildGlobalMenu", $this->MODULE_ID, "\\MyCompany\\Banner\\Event", "onBuildGlobalMenu"); }
}