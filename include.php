<?php
use Bitrix\Main\Loader;
use Bitrix\Main\EventManager;

// Define the log file path.
// Logging will be active if this constant is defined.
// You can wrap this in additional logic, e.g. check for a developer's IP or a special cookie/parameter.
define('MYCOMPANY_BANNER_LOG_FILE', '/upload/mycompany_banner.log');

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