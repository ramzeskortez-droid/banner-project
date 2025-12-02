<?php
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use MyCompany\Banner\BannerTable;
use MyCompany\Banner\BannerSetTable;

header('Content-Type: application/json');
$resp = ['success' => false, 'errors' => []];

if (!check_bitrix_sessid()) {
    $resp['errors'][] = 'Ошибка сессии';
    echo json_encode($resp);
    die();
}

try {
    Loader::includeModule('mycompany.banner');
    $req = Application::getInstance()->getContext()->getRequest();
    $action = $req->getPost('action');
    $setId = (int)$req->getPost('set_id');

    if ($action === 'save_set_settings') {
        $setId = (int)$req->getPost('set_id');
        if ($setId <= 0) $setId = 1; // Фоллбэк

        $data = [
            'TEXT_BG_SHOW' => $req->getPost('show') === 'Y' ? 'Y' : 'N',
            'TEXT_BG_COLOR' => trim($req->getPost('color')),
            'TEXT_BG_OPACITY' => (int)$req->getPost('opacity'),
            'USE_GLOBAL_TEXT_COLOR' => $req->getPost('use_global_text_color') === 'Y' ? 'Y' : 'N',
            'GLOBAL_TEXT_COLOR' => trim($req->getPost('global_text_color')),
            'CATEGORY_MODE' => $req->getPost('category_mode') === 'Y' ? 'Y' : 'N',
        ];

        $exist = BannerSetTable::getById($setId)->fetch();
        if ($exist) {
            $res = BannerSetTable::update($setId, $data);
        } else {
            // Если записи нет, создаем её принудительно с ID
            $data['ID'] = $setId;
            $data['NAME'] = 'Default Set';
            $res = BannerSetTable::add($data);
        }

        if ($res->isSuccess()) {
            $resp['success'] = true;
            $resp['data'] = BannerSetTable::getById($setId)->fetch();
        } else {
            $resp['errors'] = $res->getErrorMessages();
        }
    }
    if ($action === 'save_mass_format') {
        $setId = (int)$req->getPost('set_id');
        $field = $req->getPost('field'); // напр. TITLE_BOLD
        $val = $req->getPost('value');   // Y или N
        
        // Массовое обновление
        $banners = BannerTable::getList(['filter'=>['SET_ID'=>$setId]])->fetchAll();
        foreach($banners as $b) {
            BannerTable::update($b['ID'], [$field => $val]);
        }
        $resp['success'] = true;
    }
    elseif ($action === 'create_set') {
        $name = trim($req->getPost('name'));
        $catMode = $req->getPost('category_mode') === 'Y' ? 'Y' : 'N';
        
        $res = \MyCompany\Banner\BannerSetTable::add([
            'NAME' => $name,
            'CATEGORY_MODE' => $catMode,
            // defaults
            'TEXT_BG_SHOW' => 'N', 'TEXT_BG_COLOR' => '#ffffff', 'TEXT_BG_OPACITY' => 90,
            'USE_GLOBAL_TEXT_COLOR' => 'N', 'GLOBAL_TEXT_COLOR' => '#000000'
        ]);
        
        if ($res->isSuccess()) {
            $setId = $res->getId();
            $resp['success'] = true;
            $resp['id'] = $setId;
            
            if ($catMode === 'Y' && \Bitrix\Main\Loader::includeModule('iblock')) {
                // 1. Ищем с описанием
                $cats = [];
                $rs = \CIBlockSection::GetList(['RAND'=>'ASC'], ['ACTIVE'=>'Y', 'GLOBAL_ACTIVE'=>'Y', '!DESCRIPTION'=>false], false, ['ID','NAME','DESCRIPTION','PICTURE','SECTION_PAGE_URL'], ['nTopCount'=>8]);
                while($r = $rs->GetNext()) $cats[] = $r;
                
                // 2. Если мало, добираем любые
                if (count($cats) < 8) {
                    $rs = \CIBlockSection::GetList(['RAND'=>'ASC'], ['ACTIVE'=>'Y', 'GLOBAL_ACTIVE'=>'Y'], false, ['ID','NAME','DESCRIPTION','PICTURE','SECTION_PAGE_URL'], ['nTopCount'=>(8-count($cats))]);
                    while($r = $rs->GetNext()) $cats[] = $r;
                }
                
                // 3. Заполняем слоты
                for($i=1; $i<=8; $i++) {
                    $cat = $cats[$i-1] ?? null;
                    \MyCompany\Banner\BannerTable::add([
                        'SET_ID' => $setId,
                        'SLOT_INDEX' => $i,
                        'TITLE' => $cat ? $cat['NAME'] : "Блок $i",
                        'SUBTITLE' => $cat ? TruncateText(strip_tags($cat['DESCRIPTION']), 100) : "",
                        'LINK' => $cat ? $cat['SECTION_PAGE_URL'] : '#',
                        'IMAGE' => ($cat && $cat['PICTURE']) ? \CFile::GetPath($cat['PICTURE']) : '',
                        'SORT' => $i * 10,
                        'TITLE_BOLD' => 'Y', // По дефолту красиво
                        'TEXT_COLOR' => '#000000',
                        'TEXT_ALIGN' => 'center'
                    ]);
                }
            }
        } else {
            $resp['errors'] = $res->getErrorMessages();
        }
    }
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
            'TITLE_FONT_SIZE'    => $req->getPost('title_font_size') ?: '20px',
            'SUBTITLE_FONT_SIZE' => $req->getPost('subtitle_font_size') ?: '14px',
            'FONT_FAMILY'        => $req->getPost('font_family') ?: 'Open Sans',
            'FONT_WEIGHT'        => $req->getPost('font_weight') ?: 'normal',
            'FONT_STYLE'         => $req->getPost('font_style') ?: 'normal',
            'IMAGE_TYPE'         => $req->getPost('image_type') ?: 'background',
            'SORT'               => (int)$req->getPost('sort') ?: 500,
            'IMG_SCALE'          => (int)$req->getPost('img_scale') ?: 100,
            'IMG_POS_X'          => (int)$req->getPost('img_pos_x') ?: 50,
            'IMG_POS_Y'          => (int)$req->getPost('img_pos_y') ?: 50,
            'TITLE_BOLD' => $req->getPost('title_bold') ?: 'N',
            'TITLE_ITALIC' => $req->getPost('title_italic') ?: 'N',
            'TITLE_UNDERLINE' => $req->getPost('title_underline') ?: 'N',
            'SUBTITLE_BOLD' => $req->getPost('subtitle_bold') ?: 'N',
            'SUBTITLE_ITALIC' => $req->getPost('subtitle_italic') ?: 'N',
            'SUBTITLE_UNDERLINE' => $req->getPost('subtitle_underline') ?: 'N',
        ];
        
        $existing = BannerTable::getList(['filter'=>['SET_ID'=>$data['SET_ID'], 'SLOT_INDEX'=>$data['SLOT_INDEX']]])->fetch();

        $imagePath = '';
        if (!empty($_FILES['image_file']['tmp_name'])) {
            $fid = \CFile::SaveFile($_FILES['image_file'], 'mycompany.banner');
            if ($fid) $imagePath = \CFile::GetPath($fid);
        } elseif (!empty($req->getPost('image_url'))) {
            $imagePath = trim($req->getPost('image_url'));
        }

        if ($imagePath) {
            $data['IMAGE'] = $imagePath;
        } elseif (isset($existing['IMAGE']) && empty($imagePath)) {
            // keep old image if no new one is provided
        } else {
            $data['IMAGE'] = '';
        }

        if($existing) {
            $res = BannerTable::update($existing['ID'], $data);
        } else {
            $res = BannerTable::add($data);
        }

        if($res->isSuccess()) {
            $resp['success'] = true;
            $id = $existing ? $existing['ID'] : $res->getId();
            $resp['data'] = BannerTable::getById($id)->fetch();
        } else {
            $resp['errors'] = $res->getErrorMessages();
        }
    }
} catch (\Exception $e) {
    $resp['errors'][] = $e->getMessage();
}

echo json_encode($resp);