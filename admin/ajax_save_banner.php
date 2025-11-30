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
        if ($setId > 0) {
            $data = [
                'TEXT_BG_SHOW' => $req->getPost('show') === 'Y' ? 'Y' : 'N',
                'TEXT_BG_COLOR' => trim($req->getPost('color')),
                'TEXT_BG_OPACITY' => (int)$req->getPost('opacity'),
            ];
            $res = BannerSetTable::update($setId, $data);
            if ($res->isSuccess()) {
                $resp['success'] = true;
                $resp['data'] = BannerSetTable::getById($setId)->fetch();
            } else {
                $resp['errors'] = $res->getErrorMessages();
            }
        } else {
            $resp['errors'][] = 'Set ID не указан';
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