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
use MyCompany\Banner\BannerSetTable;

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

    $post = $request->getPostList();
    $action = $post->get('action');

    if ($action === 'create_set') {
        $setName = trim($post->get('name'));
        if (empty($setName)) {
            throw new \Exception('Название набора не может быть пустым.');
        }
        $result = BannerSetTable::add(['NAME' => $setName]);
        if ($result->isSuccess()) {
            $response['success'] = true;
            $response['set_id'] = $result->getId();
        } else {
            $response['errors'] = $result->getErrorMessages();
        }

    } elseif ($action === 'save_slot') {
        $postData = $post->toArray();
        $setId = (int)$postData['set_id'];
        $slotIndex = (int)$postData['slot_index'];

        if ($setId <= 0) {
            throw new \Exception('Неверный ID набора.');
        }
        if ($slotIndex <= 0 || $slotIndex > 8) {
            throw new \Exception('Неверный индекс слота.');
        }

        $data = [
            'SET_ID' => $setId,
            'SLOT_INDEX' => $slotIndex,
            'TITLE' => $postData['title'],
            'ANNOUNCEMENT' => $postData['announcement'],
            'THEME_COLOR' => $postData['theme_color'],
            'IMAGE_LINK' => $postData['image_link'], // По умолчанию сохраняем URL
        ];

        // Обработка загрузки файла
        $files = $request->getFileList()->toArray();
        if (isset($files['image_file']) && $files['image_file']['error'] === UPLOAD_ERR_OK) {
            $fileArray = $files['image_file'];
            $fileId = CFile::SaveFile($fileArray, $module_id);
            if ($fileId) {
                $data['IMAGE_LINK'] = CFile::GetPath($fileId);
            } else {
                 $response['errors'][] = 'Не удалось сохранить файл.';
            }
        }

        // Ищем существующий баннер для этого слота
        $existing = BannerTable::getList([
            'filter' => ['=SET_ID' => $setId, '=SLOT_INDEX' => $slotIndex]
        ])->fetch();

        if ($existing) {
            $result = BannerTable::update($existing['ID'], $data);
        } else {
            $result = BannerTable::add($data);
        }
        
        if ($result->isSuccess()) {
            $id = $existing ? $existing['ID'] : $result->getId();
            // Получаем финальные данные, включая ID
            $savedData = BannerTable::getById($id)->fetch();
            if ($savedData) {
                // Убедимся, что все нужные поля есть в ответе
                $response['success'] = true;
                $response['data'] = $savedData;
            } else {
                throw new \Exception('Не удалось получить сохраненные данные.');
            }
        } else {
            $response['errors'] = $result->getErrorMessages();
        }

    } else {
        throw new \Exception('Неизвестное действие.');
    }

} catch (\Exception $e) {
    $response['errors'][] = $e->getMessage();
}

// --- Отдаем JSON ответ ---
header('Content-Type: application/json');
echo json_encode($response);
die();
