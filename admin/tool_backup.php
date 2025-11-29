<?php
// This is a simple script to backup the module files.
// It should be placed in the /bitrix/admin/ folder with the name mycompany_banner_backup.php or accessed via a menu link.

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

$module_id = 'mycompany.banner';
$module_root = $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/" . $module_id;

$APPLICATION->SetTitle("Бэкап модуля " . $module_id);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

if (!check_bitrix_sessid()) {
    CAdminMessage::ShowMessage([
        "MESSAGE" => "Ошибка: Неверная сессия. Попробуйте обновить страницу.",
        "TYPE" => "ERROR"
    ]);
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
    die();
}

try {
    // --- Get current version for backup path ---
    $arModuleVersion = array();
    if (file_exists($module_root . "/install/version.php")) {
        include($module_root . "/install/version.php");
        $version = $arModuleVersion['VERSION'] ?: 'unknown';
    } else {
        $version = '1.0.8'; // Fallback as requested
    }
    
    $backup_dir_name = 'vers/' . $version;
    $backup_path = $module_root . '/' . $backup_dir_name;

    // --- Create backup directory ---
    if (!file_exists($backup_path)) {
        if (!mkdir($backup_path, 0775, true)) {
            throw new \Exception('Не удалось создать папку для бэкапа: ' . $backup_path);
        }
    }

    // --- Define what to copy ---
    $source_folders = ['admin', 'install', 'lib'];
    $source_files = ['include.php', 'options.php'];

    // --- Copy folders ---
    foreach ($source_folders as $folder) {
        $source = $module_root . '/' . $folder;
        $destination = $backup_path . '/' . $folder;
        if (file_exists($source)) {
            CopyDirFiles($source, $destination, true, true);
        }
    }

    // --- Copy files ---
    foreach ($source_files as $file) {
        $source = $module_root . '/' . $file;
        $destination = $backup_path . '/' . $file;
        if (file_exists($source)) {
            copy($source, $destination);
        }
    }

    CAdminMessage::ShowMessage([
        "MESSAGE" => "Бэкап версии {$version} успешно создан!",
        "DETAILS" => "Файлы сохранены в: <br><code>" . $backup_dir_name . "</code>",
        "TYPE" => "OK",
        "HTML" => true
    ]);

} catch (\Exception $e) {
    CAdminMessage::ShowMessage([
        "MESSAGE" => "Ошибка при создании бэкапа!",
        "DETAILS" => $e->getMessage(),
        "TYPE" => "ERROR",
        "HTML" => true
    ]);
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
?>
