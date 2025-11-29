<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

$module_id = 'mycompany.banner';
$APPLICATION->SetTitle("Бэкап модуля " . $module_id);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

if (!check_bitrix_sessid()) {
    CAdminMessage::ShowMessage(["MESSAGE" => "Ошибка сессии.", "TYPE" => "ERROR"]);
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
    die();
}

try {
    $module_root = $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/" . $module_id;
    $backup_version = '1.0.9'; // Version to back up
    $backup_dir_name = 'vers/' . $backup_version;
    $backup_path = $module_root . '/' . $backup_dir_name;

    if (!file_exists($backup_path)) {
        if (!mkdir($backup_path, 0775, true)) {
            throw new \Exception('Не удалось создать папку для бэкапа: ' . $backup_path);
        }
    }

    $source_items = ['admin', 'install', 'lib', 'include.php', 'options.php'];
    
    foreach ($source_items as $item) {
        $source = $module_root . '/' . $item;
        $destination = $backup_path . '/' . $item;
        if (file_exists($source)) {
            if (is_dir($source)) {
                CopyDirFiles($source, $destination, true, true);
            } else {
                copy($source, $destination);
            }
        }
    }

    CAdminMessage::ShowMessage([
        "MESSAGE" => "Бэкап версии {$backup_version} успешно создан!",
        "DETAILS" => "Файлы сохранены в: <code>{$backup_dir_name}</code>",
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