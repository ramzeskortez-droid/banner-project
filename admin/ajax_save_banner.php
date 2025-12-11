<?php
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use MyCompany\Banner\BannerTable;
use MyCompany\Banner\BannerSetTable;

function writeDebugLog($title, $data) {
    // defined in include.php
    if (!defined('MYCOMPANY_BANNER_LOG_FILE')) return;
    $logFile = $_SERVER['DOCUMENT_ROOT'] . MYCOMPANY_BANNER_LOG_FILE;
    $content = date('Y-m-d H:i:s') . " [" . $title . "]\n" . print_r($data, true) . "\n" . str_repeat("-", 40) . "\n\n";
    file_put_contents($logFile, $content, FILE_APPEND);
}

header('Content-Type: application/json');

// Log incoming request
writeDebugLog('INCOMING AJAX REQUEST', [
    'REQUEST' => $_REQUEST,
    'FILES' => $_FILES
]);

$req = Application::getInstance()->getContext()->getRequest();
$resp = ['success' => false, 'errors' => []];

if (!check_bitrix_sessid()) {
    $resp['errors'][] = 'Session has expired.';
    writeDebugLog('ERROR: Session expired', []);
    echo json_encode($resp);
    die();
}

try {
    Loader::includeModule('mycompany.banner');
    if (Loader::includeModule('iblock')) {
        // Only now we can proceed with iblock-related actions
    }
    
    $action = $req->get('action');
    $setId = (int)$req->getPost('set_id');

    switch ($action) {
        case 'create_set':
            $name = trim($req->getPost('name'));
            if (empty($name)) {
                writeDebugLog('CREATE_SET ERROR', 'Name is empty');
                throw new \Exception('Название баннера не может быть пустым.');
            }
            
            $res = BannerSetTable::add(['NAME' => $name]);
            if (!$res->isSuccess()) {
                writeDebugLog('CREATE_SET DB ERROR', $res->getErrorMessages());
                $resp['errors'] = $res->getErrorMessages();
                break;
            }
            $newSetId = $res->getId();
            writeDebugLog('CREATE_SET SUCCESS', ['NEW_SET_ID' => $newSetId, 'NAME' => $name]);

            if ($req->getPost('auto_fill') === 'Y' && Loader::includeModule('catalog')) {
                writeDebugLog('CREATE_SET: AUTO_FILL START', []);
                $catalogIblockId = 0;
                $catalogs = \CCatalog::GetList([], ['IBLOCK_ACTIVE' => 'Y'], false, ['nTopCount' => 1], ['IBLOCK_ID']);
                if ($catalog = $catalogs->Fetch()) {
                    $catalogIblockId = $catalog['IBLOCK_ID'];
                    writeDebugLog('CREATE_SET: AUTO_FILL', ['Found catalog iblock' => $catalogIblockId]);
                }

                if ($catalogIblockId > 0) {
                    $sections = [];
                    $rs = \CIBlockSection::GetList(
                        ['PICTURE' => 'DESC', 'DESCRIPTION' => 'DESC', 'NAME' => 'ASC'], 
                        ['ACTIVE' => 'Y', 'GLOBAL_ACTIVE' => 'Y', 'IBLOCK_ID' => $catalogIblockId, '>PICTURE' => 0, '!DESCRIPTION' => false], 
                        false, 
                        ['ID','NAME','DESCRIPTION','PICTURE','SECTION_PAGE_URL'],
                        ['nPageSize' => 8]
                    );
                    while($r = $rs->GetNext()) {
                        $sections[] = $r;
                    }
                    writeDebugLog('CREATE_SET: AUTO_FILL', ['Found sections' => count($sections)]);
                    
                    for($i = 1; $i <= 8; $i++) {
                        $section = $sections[$i-1] ?? null;
                        $bannerData = [
                            'SET_ID' => $newSetId, 
                            'SLOT_INDEX' => $i, 
                            'SORT' => $i * 10,
                            'TITLE' => $section ? $section['NAME'] : "Блок $i",
                            'SUBTITLE' => $section ? TruncateText(strip_tags($section['DESCRIPTION']), 150) : "",
                            'LINK' => $section ? $section['SECTION_PAGE_URL'] : '#',
                            'IMAGE' => $section['PICTURE'] ?? null,
                        ];
                        BannerTable::add($bannerData);
                    }
                    writeDebugLog('CREATE_SET: AUTO_FILL', ['Finished creating 8 blocks']);
                } else {
                    writeDebugLog('CREATE_SET: AUTO_FILL ERROR', 'No catalog Iblock found');
                }
            }

            // Fetch created data to return to frontend
            $newSetData = BannerSetTable::getById($newSetId)->fetch();
            $newBannersDataRaw = BannerTable::getList(['filter' => ['SET_ID' => $newSetId], 'order' => ['SLOT_INDEX' => 'ASC']])->fetchAll();
            $newBannersData = array_map(function($b){ 
                if($b['IMAGE'] && is_numeric($b['IMAGE'])) {
                    $b['IMAGE'] = \CFile::GetPath($b['IMAGE']);
                }
                return $b; 
            }, $newBannersDataRaw);
            
            $resp['success'] = true;
            $resp['data'] = ['set' => $newSetData, 'banners' => $newBannersData];
            break;

        case 'delete_set':
            if ($setId > 0) {
                writeDebugLog('DELETE_SET', ['SET_ID' => $setId]);
                $banners = BannerTable::getList(['filter' => ['SET_ID' => $setId], 'select' => ['ID']])->fetchAll();
                foreach ($banners as $banner) { BannerTable::delete($banner['ID']); }
                $res = BannerSetTable::delete($setId);
                if ($res->isSuccess()) {
                    writeDebugLog('DELETE_SET SUCCESS', ['SET_ID' => $setId]);
                    $resp['success'] = true;
                } else {
                    writeDebugLog('DELETE_SET ERROR', $res->getErrorMessages());
                    $resp['errors'] = $res->getErrorMessages();
                }
            } else {
                writeDebugLog('DELETE_SET ERROR', 'Invalid Set ID');
                $resp['errors'][] = 'Invalid Banner ID.';
            }
            break;

        case 'save_slot':
            $fields = $req->getPostList()->toArray();
            $slotIndex = (int)($fields['slot_index'] ?? $fields['SLOT_INDEX'] ?? 0);
            $currentSetId = (int)($fields['set_id'] ?? $setId ?? 0);

            if ($currentSetId <= 0 || $slotIndex <= 0) {
                $errorMsg = 'Missing Set or Slot ID. Received: Set=' . $currentSetId . ', Slot=' . $slotIndex;
                writeDebugLog('SAVE_SLOT ERROR', $errorMsg);
                $resp['errors'][] = $errorMsg;
                echo json_encode($resp);
                die();
            }
            
            writeDebugLog('SAVE_SLOT: DATA IN', $fields);

            $allowed = ['TITLE','SUBTITLE','LINK','IMAGE','COLOR','CATEGORY_ID','TEXT_COLOR','TEXT_ALIGN','TITLE_FONT_SIZE','SUBTITLE_FONT_SIZE','TITLE_BOLD','TITLE_ITALIC','TITLE_UNDERLINE','SUBTITLE_BOLD','SUBTITLE_ITALIC','SUBTITLE_UNDERLINE','IMG_SCALE','IMG_POS_X','IMG_POS_Y','SORT','TEXT_BG_SHOW','TEXT_BG_COLOR','TEXT_BG_OPACITY','TEXT_STROKE_WIDTH','TEXT_STROKE_COLOR','HOVER_ANIMATION'];
            $data = array_intersect_key($fields, array_flip($allowed));

            writeDebugLog('SAVE_SLOT: PREPARED DATA', $data);

            $existing = BannerTable::getList(['filter'=>['SET_ID'=>$currentSetId, 'SLOT_INDEX'=>$slotIndex]])->fetch();
            if ($existing) {
                $res = BannerTable::update($existing['ID'], $data);
            } else {
                $res = BannerTable::add($data + ['SET_ID' => $currentSetId, 'SLOT_INDEX' => $slotIndex]);
            }

            if ($res->isSuccess()) {
                $savedId = $existing ? $existing['ID'] : $res->getId();
                writeDebugLog('SAVE_SLOT SUCCESS', ['ID' => $savedId, 'DATA' => $data]);
                $resp['success'] = true;
                $savedData = BannerTable::getById($savedId)->fetch();
                if($savedData['IMAGE']) $savedData['IMAGE'] = CFile::GetPath($savedData['IMAGE']);
                $resp['data'] = $savedData;
            } else {
                writeDebugLog('SAVE_SLOT DB ERROR', $res->getErrorMessages());
                $resp['errors'] = $res->getErrorMessages();
            }
            break;

        case 'swap_blocks':
            $slot1 = (int)$req->getPost('slot_index_1');
            $slot2 = (int)$req->getPost('slot_index_2');
            writeDebugLog('SWAP_BLOCKS', ['SET_ID' => $setId, 'slot1' => $slot1, 'slot2' => $slot2]);

            if ($setId > 0 && $slot1 > 0 && $slot2 > 0 && $slot1 != $slot2) {
                $block1 = BannerTable::getList(['filter' => ['SET_ID' => $setId, 'SLOT_INDEX' => $slot1]])->fetch();
                $block2 = BannerTable::getList(['filter' => ['SET_ID' => $setId, 'SLOT_INDEX' => $slot2]])->fetch();
                
                if ($block1 && $block2) {
                    // Simple swap of slot indexes and sort values
                    $res1 = BannerTable::update($block1['ID'], ['SORT' => $block2['SORT'], 'SLOT_INDEX' => $block2['SLOT_INDEX']]);
                    $res2 = BannerTable::update($block2['ID'], ['SORT' => $block1['SORT'], 'SLOT_INDEX' => $block1['SLOT_INDEX']]);
                    if($res1->isSuccess() && $res2->isSuccess()) {
                         writeDebugLog('SWAP_BLOCKS SUCCESS', ['block1_id' => $block1['ID'], 'block2_id' => $block2['ID']]);
                        $resp['success'] = true;
                    } else {
                        $errors = array_merge($res1->getErrorMessages(), $res2->getErrorMessages());
                        writeDebugLog('SWAP_BLOCKS DB ERROR', $errors);
                        $resp['errors'] = $errors;
                    }
                } else {
                    writeDebugLog('SWAP_BLOCKS ERROR', 'One or both blocks not found');
                    $resp['errors'][] = 'Один или оба блока для обмена не найдены.';
                }
            } else {
                 writeDebugLog('SWAP_BLOCKS ERROR', 'Invalid params');
                $resp['errors'][] = 'Неверные параметры для обмена блоками.';
            }
            break;

        case 'save_global_styles':
            $styles = $req->getPost('styles');
            writeDebugLog('SAVE_GLOBAL_STYLES', ['SET_ID' => $setId, 'STYLES' => $styles]);
            if ($setId > 0 && !empty($styles) && is_array($styles)) {
                $allowed = ['TEXT_COLOR','TITLE_FONT_SIZE','SUBTITLE_FONT_SIZE','TITLE_BOLD','TITLE_ITALIC','TITLE_UNDERLINE','SUBTITLE_BOLD','SUBTITLE_ITALIC','SUBTITLE_UNDERLINE'];
                $data = array_intersect_key($styles, array_flip($allowed));
                if(!empty($data)) {
                    $banners = BannerTable::getList(['filter' => ['SET_ID' => $setId], 'select' => ['ID']])->fetchAll();
                    foreach($banners as $banner) { BannerTable::update($banner['ID'], $data); }
                    $resp['success'] = true;
                    writeDebugLog('SAVE_GLOBAL_STYLES SUCCESS', ['SET_ID' => $setId, 'APPLIED_DATA' => $data]);
                } else {
                     writeDebugLog('SAVE_GLOBAL_STYLES ERROR', 'No valid styles');
                    $resp['errors'][] = 'No valid styles to update.';
                }
            } else {
                writeDebugLog('SAVE_GLOBAL_STYLES ERROR', 'Invalid params');
                $resp['errors'][] = 'Invalid parameters for saving global styles.';
            }
            break;

        case 'get_iblocks':
            writeDebugLog('GET_IBLOCKS', []);
            $iblocks = [];
            $res = \CIBlock::GetList(['SORT'=>'ASC'], ['ACTIVE' => 'Y']); // More generic than just 'catalog'
            while($row = $res->Fetch()) $iblocks[] = ['ID' => $row['ID'], 'NAME' => $row['NAME']];
            $resp['success'] = true;
            $resp['data'] = $iblocks;
            writeDebugLog('GET_IBLOCKS SUCCESS', ['count' => count($iblocks)]);
            break;

        case 'get_sections_tree':
            $iblockId = (int)$req->get('iblock_id');
            writeDebugLog('GET_SECTIONS_TREE', ['IBLOCK_ID' => $iblockId]);
            if ($iblockId > 0) {
                $sections = [];
                $res = \CIBlockSection::GetList(['left_margin'=>'asc'], ['IBLOCK_ID'=>$iblockId, 'ACTIVE'=>'Y', 'GLOBAL_ACTIVE' => 'Y'], false, ['ID', 'NAME', 'DEPTH_LEVEL']);
                while($row = $res->Fetch()) $sections[] = $row;
                $resp['success'] = true;
                $resp['data'] = $sections;
                writeDebugLog('GET_SECTIONS_TREE SUCCESS', ['count' => count($sections)]);
            } else {
                writeDebugLog('GET_SECTIONS_TREE ERROR', 'IBlock ID not provided');
                $resp['errors'][] = 'IBlock ID not provided.';
            }
            break;
        
        case 'get_section_data':
            $sectionId = (int)$req->get('section_id');
            writeDebugLog('GET_SECTION_DATA', ['SECTION_ID' => $sectionId]);
            if($sectionId > 0) {
                $sectionRaw = \CIBlockSection::GetByID($sectionId)->GetNext();
                if($sectionRaw) {
                    if($sectionRaw['PICTURE']) $sectionRaw['PICTURE_SRC'] = \CFile::GetPath($sectionRaw['PICTURE']);
                    if($sectionRaw['DESCRIPTION']) $sectionRaw['DESCRIPTION'] = strip_tags($sectionRaw['DESCRIPTION']);
                    $resp['success'] = true;
                    $resp['data'] = [
                        'NAME' => $sectionRaw['NAME'],
                        'DESCRIPTION' => $sectionRaw['DESCRIPTION'],
                        'PICTURE' => $sectionRaw['PICTURE_SRC'] ?? null,
                        'SECTION_PAGE_URL' => $sectionRaw['SECTION_PAGE_URL']
                    ];
                    writeDebugLog('GET_SECTION_DATA SUCCESS', $resp['data']);
                } else {
                    writeDebugLog('GET_SECTION_DATA ERROR', 'Section not found');
                    $resp['errors'][] = 'Section not found.';
                }
            } else {
                writeDebugLog('GET_SECTION_DATA ERROR', 'Section ID not provided');
                $resp['errors'][] = 'Section ID not provided.';
            }
            break;

        case 'save_set_settings':
            $settings = $req->getPost('settings');
            writeDebugLog('SAVE_SET_SETTINGS', ['SET_ID' => $setId, 'SETTINGS' => $settings]);
            if ($setId > 0 && !empty($settings) && is_array($settings)) {
                $allowed = ['CATEGORY_MODE']; // Add other global settings here
                $data = array_intersect_key($settings, array_flip($allowed));
                if (!empty($data)) {
                    $res = BannerSetTable::update($setId, $data);
                    if ($res->isSuccess()) {
                        $resp['success'] = true;
                        writeDebugLog('SAVE_SET_SETTINGS SUCCESS', $data);
                    } else {
                        $resp['errors'] = $res->getErrorMessages();
                        writeDebugLog('SAVE_SET_SETTINGS ERROR', $resp['errors']);
                    }
                } else {
                    $resp['errors'][] = 'No valid settings to update.';
                }
            } else {
                $resp['errors'][] = 'Invalid parameters for saving settings.';
            }
            break;

        case 'clear_log':
            if (defined('MYCOMPANY_BANNER_LOG_FILE')) {
                $logFile = $_SERVER['DOCUMENT_ROOT'] . MYCOMPANY_BANNER_LOG_FILE;
                if (file_exists($logFile)) {
                    unlink($logFile);
                }
                $resp['success'] = true;
            }
            break;

        case 'get_log':
            if (defined('MYCOMPANY_BANNER_LOG_FILE')) {
                $logFile = $_SERVER['DOCUMENT_ROOT'] . MYCOMPANY_BANNER_LOG_FILE;
                if (file_exists($logFile)) {
                    $resp['data'] = file_get_contents($logFile);
                } else {
                    $resp['data'] = 'Log file is empty.';
                }
                $resp['success'] = true;
            }
            break;

        default:
            writeDebugLog('UNKNOWN ACTION', ['action' => $action]);
            $resp['errors'][] = 'Unknown action.';
            break;
    }
} catch (\Exception $e) {
    writeDebugLog('FATAL EXCEPTION', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    $resp['errors'][] = $e->getMessage();
}

writeDebugLog('FINAL RESPONSE', $resp);
echo json_encode($resp);