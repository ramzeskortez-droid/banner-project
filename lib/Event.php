<?php
namespace MyCompany\Banner;

class Event
{
    public static function onBuildGlobalMenu(&$aGlobalMenu, &$aModuleMenu)
    {
        // Создаем только родительский пункт (Глобальное меню)
        // Само меню из admin/menu.php подтянется автоматически, так как модуль установлен
        $aGlobalMenu["global_menu_mycompany"] = [
            "menu_id" => "global_menu_mycompany",
            "text" => "ООО-ТлиЖу",
            "title" => "Модуль ООО-ТлиЖу",
            "url" => "mycompany_banner_settings.php?lang=" . LANGUAGE_ID,
            "sort" => 1000,
            "items_id" => "global_menu_mycompany",
            "help_section" => "mycompany",
            "items" => []
        ];
    }
}