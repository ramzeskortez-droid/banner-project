<?php
namespace MyCompany\Banner;

class Event
{
    public static function onBuildGlobalMenu(&$aGlobalMenu, &$aModuleMenu)
    {
        // Добавляем глобальную кнопку в меню
        $aGlobalMenu["global_menu_mycompany"] = [
            "menu_id" => "global_menu_mycompany",
            "text" => "ООО-ТлиЖу",
            "title" => "Модуль ООО-ТлиЖу",
            "url" => "settings.php?lang=" . LANGUAGE_ID . "&mid=mycompany.banner",
            "sort" => 1000,
            "items_id" => "global_menu_mycompany",
            "help_section" => "mycompany",
            "items" => []
        ];
    }
}