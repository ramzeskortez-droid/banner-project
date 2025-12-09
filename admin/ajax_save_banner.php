<?php
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use MyCompany\Banner\BannerTable;
use MyCompany\Banner\BannerSetTable;

header('Content-Type: application/json');

// --- NEW LOGGING LOGIC ---
// ЛОГИРОВАНИЕ (В самом начале)
if (!function_exists('writeDebugLog')) {
    function writeDebugLog($data) {
        $file = $_SERVER['DOCUMENT_ROOT'] . '/upload/mycompany_banner_debug.log';
        $logEntry = date('Y-m-d H:i:s') . " | " . print_r($data, true) . "\n----------------\n";
        file_put_contents($file, $logEntry, FILE_APPEND);
    }
}

// Get request object and action early
$req = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
$action = $req->get('action') ?: $req->getPost('action');

// ОБРАБОТЧИК ЛОГОВ (Должен быть тут, чтобы не вернуть JSON ошибки внизу)
if ($action === 'get_log') {
    $file = $_SERVER['DOCUMENT_ROOT'] . '/upload/mycompany_banner_debug.log';
    if (file_exists($file)) {
        echo file_get_contents($file);
    } else {
        echo "Лог файл пуст или не создан.";
    }
    die(); // Важно! Прерываем скрипт
}
if ($action === 'clear_log') {
    $file = $_SERVER['DOCUMENT_ROOT'] . '/upload/mycompany_banner_debug.log';
    file_put_contents($file, '');
    echo "Cleared";
    die();
}

// Пишем лог только если это не чтение лога
writeDebugLog($_REQUEST);
// --- END NEW LOGGING LOGIC ---

$resp = ['success' => false, 'errors' => []];

// Security check
if (!check_bitrix_sessid()) {
    $resp['errors'][] = 'Session has expired.';
    echo json_encode($resp);
    die();
}

try {
    Loader::includeModule('mycompany.banner');
    // $req and $action are already defined above
    $setId = (int)$req->getPost('set_id');

    // Action: Create a new Banner (Set)
    if ($action === 'create_set') {
        $name = trim($req->getPost('name'));
        if (empty($name)) {
            throw new \Exception('Banner name cannot be empty.');
        }
        $catMode = $req->getPost('category_mode') === 'Y' ? 'Y' : 'N';

        // Add new Banner (Set) to DB
        $res = BannerSetTable::add([
            'NAME' => $name,
            'CATEGORY_MODE' => $catMode,
            // Default styles
            'TEXT_BG_SHOW' => 'N', 'TEXT_BG_COLOR' => '#ffffff', 'TEXT_BG_OPACITY' => 90,
            'USE_GLOBAL_TEXT_COLOR' => 'N', 'GLOBAL_TEXT_COLOR' => '#000000'
        ]);
        
        if ($res->isSuccess()) {
            $newSetId = $res->getId();
            $resp['success'] = true;
            $resp['id'] = $newSetId;
            
            // If "category mode" is enabled, pre-fill the new Banner with Blocks from IBlock sections
            if ($catMode === 'Y' && Loader::includeModule('iblock')) {
                $sections = [];
                $rs = \CIBlockSection::GetList(
                    ['RAND'=>'ASC'],
                    ['ACTIVE'=>'Y', 'GLOBAL_ACTIVE'=>'Y', 'IBLOCK_ACTIVE'=>'Y', 'CNT_ACTIVE' => 'Y'],
                    false,
                    ['ID','NAME','DESCRIPTION','PICTURE','SECTION_PAGE_URL'],
                    ['nTopCount'=>8] // Get 8 random active sections
                );
                while($r = $rs->GetNext()) {
                    $sections[] = $r;
                }
                
                // Create 8 Blocks (slots)
                for($i = 1; $i <= 8; $i++) {
                    $section = $sections[$i-1] ?? null;
                    BannerTable::add([
                        'SET_ID' => $newSetId,
                        'SLOT_INDEX' => $i,
                        'TITLE' => $section ? $section['NAME'] : "Блок $i",
                        'SUBTITLE' => $section ? TruncateText(strip_tags($section['DESCRIPTION']), 150) : "",
                        'LINK' => $section ? $section['SECTION_PAGE_URL'] : '#',
                        'IMAGE' => ($section && $section['PICTURE']) ? \CFile::GetPath($section['PICTURE']) : '',
                        'SORT' => $i * 10,
                        'TITLE_BOLD' => 'Y',
                        'TEXT_ALIGN' => 'center'
                    ]);
                }
            }
        } else {
            $resp['errors'] = $res->getErrorMessages();
        }
    }
    // Action: Delete a Banner (Set) and all its Blocks
    elseif ($action === 'delete_set') {
        if ($setId > 0) {
            // 1. Delete all associated Blocks (BannerTable records)
            $banners = BannerTable::getList(['filter' => ['SET_ID' => $setId], 'select' => ['ID']])->fetchAll();
            foreach ($banners as $banner) {
                BannerTable::delete($banner['ID']);
            }

            // 2. Delete the Banner (Set) itself
            $res = BannerSetTable::delete($setId);
            if ($res->isSuccess()) {
                $resp['success'] = true;
            } else {
                $resp['errors'] = $res->getErrorMessages();
            }
        } else {
            $resp['errors'][] = 'Invalid Banner ID provided.';
        }
    }
    // Action: Save a single Block's (slot) data
    elseif ($action === 'save_slot') {
        $data = [
            'SET_ID'             => $setId,
            'SLOT_INDEX'         => (int)$req->getPost('slot_index'),
            'TITLE'              => trim($req->getPost('title')),
            'SUBTITLE'           => trim($req->getPost('subtitle')),
            'LINK'               => trim($req->getPost('link')),
            'COLOR'              => trim($req->getPost('color')),
            'CATEGORY_ID'        => (int)$req->getPost('category_id'),
            'TEXT_ALIGN'         => $req->getPost('text_align') ?: 'center',
            'TEXT_COLOR'         => $req->getPost('text_color') ?: '#000000',
            'TITLE_FONT_SIZE'    => ((int)$req->getPost('title_font_size') ?: 20) . 'px',
            'SUBTITLE_FONT_SIZE' => ((int)$req->getPost('subtitle_font_size') ?: 14) . 'px',
            'SORT'               => (int)$req->getPost('sort') ?: 500,
            'IMG_SCALE'          => (int)$req->getPost('img_scale') ?: 100,
            'IMG_POS_X'          => is_numeric($req->getPost('img_pos_x')) ? (int)$req->getPost('img_pos_x') : 50,
            'IMG_POS_Y'          => is_numeric($req->getPost('img_pos_y')) ? (int)$req->getPost('img_pos_y') : 50,
            'TITLE_BOLD'         => $req->getPost('title_bold') === 'Y' ? 'Y' : 'N',
            'TITLE_ITALIC'       => $req->getPost('title_italic') === 'Y' ? 'Y' : 'N',
            'TITLE_UNDERLINE'    => $req->getPost('title_underline') === 'Y' ? 'Y' : 'N',
            'SUBTITLE_BOLD'      => $req->getPost('subtitle_bold') === 'Y' ? 'Y' : 'N',
            'SUBTITLE_ITALIC'    => $req->getPost('subtitle_italic') === 'Y' ? 'Y' : 'N',
            'SUBTITLE_UNDERLINE' => $req->getPost('subtitle_underline') === 'Y' ? 'Y' : 'N',
        ];
        
        $existing = BannerTable::getList(['filter'=>['SET_ID'=>$data['SET_ID'], 'SLOT_INDEX'=>$data['SLOT_INDEX']]])->fetch();

        // Handle image upload (file or URL)
        $imagePath = '';
        if (!empty($_FILES['image_file']['tmp_name'])) {
            $fid = \CFile::SaveFile($_FILES['image_file'], 'mycompany.banner');
            if ($fid) $imagePath = \CFile::GetPath($fid);
        } elseif (!empty($req->getPost('image_url'))) {
            $imagePath = trim($req->getPost('image_url'));
        }

        if ($imagePath) {
            $data['IMAGE'] = $imagePath;
        }

        // Update or Add the Block record
        if($existing) {
            $res = BannerTable::update($existing['ID'], $data);
        } else {
            $res = BannerTable::add($data);
        }

        if($res->isSuccess()) {
            $resp['success'] = true;
            $id = $existing ? $existing['ID'] : $res->getId();
            $resp['data'] = BannerTable::getById($id)->fetch(); // Return updated data
        } else {
            $resp['errors'] = $res->getErrorMessages();
        }
    }
     // Other mass-update and settings actions...
    elseif ($action === 'save_set_settings') {
        $data = [
            'TEXT_BG_SHOW' => $req->getPost('show') === 'Y' ? 'Y' : 'N',
            'TEXT_BG_COLOR' => trim($req->getPost('color')),
            'TEXT_BG_OPACITY' => (int)$req->getPost('opacity'),
            'CATEGORY_MODE' => $req->getPost('category_mode') === 'Y' ? 'Y' : 'N',
        ];
        $res = BannerSetTable::update($setId, $data);

        if ($res->isSuccess()) {
            $resp['success'] = true;
            $resp['data'] = BannerSetTable::getById($setId)->fetch();
        } else {
            $resp['errors'] = $res->getErrorMessages();
        }
    }
    // ... other actions remain unchanged
} catch (\Exception $e) {
    $resp['errors'][] = $e->getMessage();
}

echo json_encode($resp);
