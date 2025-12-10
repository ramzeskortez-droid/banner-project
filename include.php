<?php
use Bitrix\Main\Loader;
use Bitrix\Main\EventManager;

Loader::registerAutoLoadClasses("mycompany.banner", array(
    "\MyCompany\Banner\Event" => "lib/Event.php",
    "\MyCompany\Banner\BannerTable" => "lib/BannerTable.php",
    "\MyCompany\Banner\BannerSetTable" => "lib/BannerSetTable.php", // ! Важно: добавлено
));

EventManager::getInstance()->addEventHandler(
    "main",
    "OnBuildGlobalMenu",
    array("\MyCompany\Banner\Event", "onBuildGlobalMenu")
);