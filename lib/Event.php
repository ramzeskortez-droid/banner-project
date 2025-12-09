<?php
namespace MyCompany\Banner;

/**
 * Class Event
 *
 * Handles Bitrix event subscriptions for the module.
 * Currently, it's only used to add a top-level item to the admin global menu.
 *
 * @package MyCompany\Banner
 */
class Event
{
    /**
     * Event handler for 'onBuildGlobalMenu'.
     * Adds a custom global menu section for all company modules.
     * The specific module menu items are added via admin/menu.php.
     *
     * @param array $aGlobalMenu The global menu structure.
     * @param array $aModuleMenu The module menu structure.
     */
    public static function onBuildGlobalMenu(&$aGlobalMenu, &$aModuleMenu)
    {
        $aGlobalMenu["global_menu_mycompany"] = [
            "menu_id" => "global_menu_mycompany",
            "text" => "Мои модули", // A more generic name
            "title" => "Модули компании MyCompany",
            "url" => "mycompany_banner_settings.php?lang=" . LANGUAGE_ID, // Link to the main page of this module
            "sort" => 1000,
            "items_id" => "global_menu_mycompany_items", // Unique ID for items
            "help_section" => "mycompany",
            "items" => [] // Sub-items will be added automatically by Bitrix from admin/menu.php
        ];
    }
}
