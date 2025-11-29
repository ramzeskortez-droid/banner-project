<?php
use Bitrix\Main\Loader;

// Регистрируем класс события, чтобы он был доступен
Loader::registerAutoLoadClasses("mycompany.banner", array(
    "\MyCompany\Banner\Event" => "lib/Event.php",
));