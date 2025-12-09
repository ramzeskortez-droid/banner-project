<?php
use Bitrix\Main\Loader;
use Bitrix\Main\EventManager;

// 1. Автозагрузка классов
Loader::registerAutoLoadClasses("mycompany.banner", array(
    "\MyCompany\Banner\Event" => "lib/Event.php",
    "\MyCompany\Banner\BannerTable" => "lib/BannerTable.php",
));

// 2. Обработчик для создания меню
EventManager::getInstance()->addEventHandler(
    "main",
    "OnBuildGlobalMenu",
    array("\MyCompany\Banner\Event", "onBuildGlobalMenu")
);