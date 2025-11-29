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

$response = ['success' => false, 'errors' => [], 'data' => null];
$request = Application::getInstance()->getContext()->getRequest();
$module_id = 'mycompany.banner';

try {
    // 1. Базовые проверки
    if (!Loader::includeModule($module_id)) {
        throw new \Exception('Модуль mycompany.banner не установлен.');
    }
    if (!$request->isPost()) {
        throw new \Exception('Неверный метод запроса.');
    }
    if (!check_bitrix_sessid()) {
        throw new \Exception('Ошибка сессии.');
    }

    // 2. Проверка действия
    $action = $request->getPost('action');
    if ($action !== 'save_slot') {
        throw new \Exception('Неизвестное действие.');
    }

    // 3. Получение и валидация данных
    $postData = $request->getPostList()->toArray();
    $setId = (int)$postData['set_id'];
    $slotIndex = (int)$postData['slot_index'];

    if ($setId <= 0) {
        throw new \Exception('Неверный ID набора.');
    }
    if ($slotIndex <= 0 || $slotIndex > 8) { // 8 слотов в новой сетке
        throw new \Exception('Неверный индекс слота.');
    }

    // 4. Подготовка данных для сохранения
    $data = [
        'SET_ID' => $setId,
        'SLOT_INDEX' => $slotIndex,
        'TITLE' => htmlspecialcharsbx($postData['title']),
        'SUBTITLE' => htmlspecialcharsbx($postData['subtitle']),
        'LINK' => htmlspecialcharsbx($postData['link']),
        'COLOR' => htmlspecialcharsbx($postData['color']),
    ];

    // 5. Обработка загрузки файла
    $files = $request->getFileList()->toArray();
    if (isset($files['image']) && $files['image']['error'] === UPLOAD_ERR_OK) {
        $fileArray = $files['image'];
        $fileId = CFile::SaveFile($fileArray, $module_id);
        if ($fileId) {
            $data['IMAGE'] = CFile::GetPath($fileId);
        } else {
             $response['errors'][] = 'Не удалось сохранить файл.';
        }
    }

    // 6. Поиск существующего баннера и обновление/добавление
    $existing = BannerTable::getList([
        'filter' => ['=SET_ID' => $setId, '=SLOT_INDEX' => $slotIndex]
    ])->fetch();

    if ($existing) {
        // Если есть файл и мы его не обновили, сохраняем старый
        if (empty($data['IMAGE']) && !empty($existing['IMAGE'])) {
           $data['IMAGE'] = $existing['IMAGE'];
        }
        $result = BannerTable::update($existing['ID'], $data);
    } else {
        $result = BannerTable::add($data);
    }
    
    // 7. Формирование успешного ответа
    if ($result->isSuccess()) {
        $id = $existing ? $existing['ID'] : $result->getId();
        $savedData = BannerTable::getById($id)->fetch();
        
        $response['success'] = true;
        $response['data'] = $savedData; // Отправляем актуальные данные обратно
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