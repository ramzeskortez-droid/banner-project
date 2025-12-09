<?php
use Bitrix\Main\Loader;
use Bitrix\Main\EventManager;

// 1. Автозагрузка классов
Loader::registerAutoLoadClasses("mycompany.banner", array(
    "\MyCompany\Banner\Event" => "lib/Event.php",
    "\MyCompany\Banner\BannerTable" => "lib/BannerTable.php", // На всякий случай добавим и таблицу, если она есть
));

// 2. Обработчик создания глобального меню (ООО тлижу)
EventManager::getInstance()->addEventHandler("main", "OnBuildGlobalMenu", function(&$aGlobalMenu, &$aModuleMenu) {
    // Создаем свой глобальный раздел
    $aGlobalMenu["global_menu_mycompany"] = [
        "menu_id" => "global_menu_mycompany",
        "text" => "ООО тлижу",      // Название, которое вы видите в меню
        "title" => "ООО тлижу",
        "sort" => 1000,             // Сортировка (чем больше, тем ниже будет пункт)
        "items_id" => "global_menu_mycompany",
        "help_section" => "mycompany",
        "items" => []
    ];
});