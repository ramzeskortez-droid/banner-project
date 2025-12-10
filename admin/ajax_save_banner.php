<?php
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use MyCompany\Banner\BannerTable;
use MyCompany\Banner\BannerSetTable;

header('Content-Type: application/json');

$req = Application::getInstance()->getContext()->getRequest();
$resp = ['success' => false, 'errors' => []];

if (!check_bitrix_sessid()) {
    $resp['errors'][] = 'Session has expired.';
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
            if (empty($name)) throw new \Exception('Название баннера не может быть пустым.');
            
            $res = BannerSetTable::add(['NAME' => $name]);
            if (!$res->isSuccess()) {
                $resp['errors'] = $res->getErrorMessages();
                break;
            }
            $newSetId = $res->getId();

            if ($req->getPost('auto_fill') === 'Y' && Loader::includeModule('catalog')) {
                $catalogIblockId = 0;
                $catalogs = \CCatalog::GetList([], ['IBLOCK_ACTIVE' => 'Y'], false, ['nTopCount' => 1], ['IBLOCK_ID']);
                if ($catalog = $catalogs->Fetch()) {
                    $catalogIblockId = $catalog['IBLOCK_ID'];
                }

                if ($catalogIblockId > 0) {
                    $sectionsWithPicAndDesc = [];
                    $otherSections = [];
                    $rs = \CIBlockSection::GetList(['NAME'=>'ASC'], ['ACTIVE'=>'Y', 'GLOBAL_ACTIVE'=>'Y', 'IBLOCK_ID' => $catalogIblockId], false, ['ID','NAME','DESCRIPTION','PICTURE','SECTION_PAGE_URL']);
                    while($r = $rs->GetNext()) {
                        if (!empty($r['DESCRIPTION']) && !empty($r['PICTURE'])) {
                            $sectionsWithPicAndDesc[] = $r;
                        } else {
                            $otherSections[] = $r;
                        }
                    }
                    $finalSections = array_slice(array_merge($sectionsWithPicAndDesc, $otherSections), 0, 8);
                    
                    for($i = 1; $i <= 8; $i++) {
                        $section = $finalSections[$i-1] ?? null;
                        BannerTable::add([
                            'SET_ID' => $newSetId, 'SLOT_INDEX' => $i, 'SORT' => $i * 10,
                            'TITLE' => $section ? $section['NAME'] : "Блок $i",
                            'SUBTITLE' => $section ? TruncateText(strip_tags($section['DESCRIPTION']), 150) : "",
                            'LINK' => $section ? $section['SECTION_PAGE_URL'] : '#',
                            'IMAGE' => $section['PICTURE'] ?? null,
                        ]);
                    }
                }
            }
            $newSetData = BannerSetTable::getById($newSetId)->fetch();
            $newBannersDataRaw = BannerTable::getList(['filter' => ['SET_ID' => $newSetId]])->fetchAll();
            $newBannersData = array_map(function($b){ if($b['IMAGE']) $b['IMAGE'] = CFile::GetPath($b['IMAGE']); return $b; }, $newBannersDataRaw);
            $resp['success'] = true;
            $resp['data'] = ['set' => $newSetData, 'banners' => $newBannersData];
            break;

        case 'delete_set':
            if ($setId > 0) {
                $banners = BannerTable::getList(['filter' => ['SET_ID' => $setId], 'select' => ['ID']])->fetchAll();
                foreach ($banners as $banner) { BannerTable::delete($banner['ID']); }
                $res = BannerSetTable::delete($setId);
                if ($res->isSuccess()) $resp['success'] = true; else $resp['errors'] = $res->getErrorMessages();
            } else {
                $resp['errors'][] = 'Invalid Banner ID.';
            }
            break;

        case 'save_slot':
            $fields = $req->getPostList()->toArray();
            $slotIndex = (int)$fields['SLOT_INDEX'];
            if (!$setId || !$slotIndex) throw new \Exception('Missing Set or Slot ID.');
            
            $allowed = ['TITLE','SUBTITLE','LINK','IMAGE','COLOR','CATEGORY_ID','TEXT_COLOR','TEXT_ALIGN','TITLE_FONT_SIZE','SUBTITLE_FONT_SIZE','TITLE_BOLD','TITLE_ITALIC','TITLE_UNDERLINE','SUBTITLE_BOLD','SUBTITLE_ITALIC','SUBTITLE_UNDERLINE','IMG_SCALE','IMG_POS_X','IMG_POS_Y','SORT','TEXT_BG_SHOW','TEXT_BG_COLOR','TEXT_BG_OPACITY','TEXT_STROKE_WIDTH','TEXT_STROKE_COLOR','HOVER_ANIMATION'];
            $data = array_intersect_key($fields, array_flip($allowed));

            $existing = BannerTable::getList(['filter'=>['SET_ID'=>$setId, 'SLOT_INDEX'=>$slotIndex]])->fetch();
            $res = $existing ? BannerTable::update($existing['ID'], $data) : BannerTable::add($data + ['SET_ID' => $setId, 'SLOT_INDEX' => $slotIndex]);

            if ($res->isSuccess()) {
                $resp['success'] = true;
                $id = $existing ? $existing['ID'] : $res->getId();
                $savedData = BannerTable::getById($id)->fetch();
                if($savedData['IMAGE']) $savedData['IMAGE'] = CFile::GetPath($savedData['IMAGE']);
                $resp['data'] = $savedData;
            } else {
                $resp['errors'] = $res->getErrorMessages();
            }
            break;

        case 'swap_blocks':
            $slot1 = (int)$req->getPost('slot_index_1');
            $slot2 = (int)$req->getPost('slot_index_2');
            if ($setId > 0 && $slot1 > 0 && $slot2 > 0 && $slot1 != $slot2) {
                $block1 = BannerTable::getList(['filter' => ['SET_ID' => $setId, 'SLOT_INDEX' => $slot1]])->fetch();
                $block2 = BannerTable::getList(['filter' => ['SET_ID' => $setId, 'SLOT_INDEX' => $slot2]])->fetch();
                if ($block1 && $block2) {
                    BannerTable::update($block1['ID'], ['SORT' => $block2['SORT'], 'SLOT_INDEX' => $block2['SLOT_INDEX']]);
                    BannerTable::update($block2['ID'], ['SORT' => $block1['SORT'], 'SLOT_INDEX' => $block1['SLOT_INDEX']]);
                    $resp['success'] = true;
                } else $resp['errors'][] = 'One or both blocks not found.';
            } else $resp['errors'][] = 'Invalid parameters for swapping.';
            break;

        case 'save_global_styles':
            $styles = $req->getPost('styles');
            if ($setId > 0 && !empty($styles) && is_array($styles)) {
                $allowed = ['TEXT_COLOR','TITLE_FONT_SIZE','SUBTITLE_FONT_SIZE','TITLE_BOLD','TITLE_ITALIC','TITLE_UNDERLINE','SUBTITLE_BOLD','SUBTITLE_ITALIC','SUBTITLE_UNDERLINE'];
                $data = array_intersect_key($styles, array_flip($allowed));
                if(!empty($data)) {
                    $banners = BannerTable::getList(['filter' => ['SET_ID' => $setId], 'select' => ['ID']])->fetchAll();
                    foreach($banners as $banner) { BannerTable::update($banner['ID'], $data); }
                    $resp['success'] = true;
                } else $resp['errors'][] = 'No valid styles to update.';
            } else $resp['errors'][] = 'Invalid parameters for saving global styles.';
            break;

        case 'get_iblocks':
            $iblocks = [];
            $res = \CIBlock::GetList(['SORT'=>'ASC'], ['ACTIVE' => 'Y', 'TYPE' => 'catalog']);
            while($row = $res->Fetch()) $iblocks[] = $row;
            $resp['success'] = true;
            $resp['data'] = $iblocks;
            break;

        case 'get_sections_tree':
            $iblockId = (int)$req->get('iblock_id');
            if ($iblockId > 0) {
                $sections = [];
                $res = \CIBlockSection::GetList(['left_margin'=>'asc'], ['IBLOCK_ID'=>$iblockId, 'ACTIVE'=>'Y'], false, ['ID', 'NAME', 'DEPTH_LEVEL']);
                while($row = $res->Fetch()) $sections[] = $row;
                $resp['success'] = true;
                $resp['data'] = $sections;
            } else $resp['errors'][] = 'IBlock ID not provided.';
            break;
        
        case 'get_section_data':
            $sectionId = (int)$req->get('section_id');
            if($sectionId > 0) {
                $section = \CIBlockSection::GetByID($sectionId)->GetNext();
                if($section) {
                    if($section['PICTURE']) $section['PICTURE'] = CFile::GetPath($section['PICTURE']);
                    if($section['DESCRIPTION']) $section['DESCRIPTION'] = strip_tags($section['DESCRIPTION']);
                    $resp['success'] = true;
                    $resp['data'] = $section;
                } else $resp['errors'][] = 'Section not found.';
            } else $resp['errors'][] = 'Section ID not provided.';
            break;

        default:
            $resp['errors'][] = 'Unknown action.';
            break;
    }
} catch (\Exception $e) {
    $resp['errors'][] = $e->getMessage();
}

echo json_encode($resp);