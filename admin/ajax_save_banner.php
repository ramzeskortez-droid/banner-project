<?php
// Это AJAX обработчик, отключаем статистику и визуальную часть
define("NO_KEEP_STATISTIC", true);
define("NO_AGENT_STATISTIC", true);
define("NO_AGENT_CHECK", true);
define("NOT_CHECK_PERMISSIONS", true); // Доверяем проверке sessid

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use MyCompany\Banner\BannerTable;

// --- Начало обработки ---

$response = ['success' => false, 'errors' => []];
$request = Application::getInstance()->getContext()->getRequest();
$module_id = 'mycompany.banner';

try {
    if (!Loader::includeModule($module_id)) {
        throw new \Exception('Модуль mycompany.banner не установлен.');
    }
    if (!$request->isPost()) {
        throw new \Exception('Неверный метод запроса.');
    }
    if (!check_bitrix_sessid()) {
        throw new \Exception('Ошибка сессии.');
    }

    $post = $request->getPostList()->toArray();
    $slotIndex = (int)$post['slot_index'];
    if ($slotIndex <= 0 || $slotIndex > 10) {
        throw new \Exception('Неверный индекс слота.');
    }

    $data = [
        'SET_ID' => 1, // Пока работаем с одним сетом
        'SLOT_INDEX' => $slotIndex,
        'TITLE' => $post['title'],
        'SUBTITLE' => $post['subtitle'],
        'LINK' => $post['link'],
        'COLOR' => $post['color'],
    ];

    // Обработка загрузки файла
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileArray = $_FILES['image'];
        $fileId = CFile::SaveFile($fileArray, $module_id);
        if ($fileId) {
            $data['IMAGE'] = CFile::GetPath($fileId);
        } else {
             $response['errors'][] = 'Не удалось сохранить файл.';
        }
    }

    // Ищем существующий баннер для этого слота
    $existing = BannerTable::getList([
        'filter' => ['=SET_ID' => 1, '=SLOT_INDEX' => $slotIndex]
    ])->fetch();

    if ($existing) {
        $result = BannerTable::update($existing['ID'], $data);
    } else {
        $result = BannerTable::add($data);
    }
    
    if ($result->isSuccess()) {
        $id = $existing ? $existing['ID'] : $result->getId();
        $savedData = BannerTable::getById($id)->fetch(); // Получаем финальные данные
        $response['success'] = true;
        $response['data'] = $savedData;
    } else {
        $response['errors'] = $result->getErrorMessages();
    }

} catch (\Exception $e) {
    $response['errors'][] = $e->getMessage();
}

// --- Отдаем JSON ответ ---
header('Content-Type: application/json');
echo json_encode($response);
die();
