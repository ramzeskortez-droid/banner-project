<?php
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use MyCompany\Banner\BannerTable;

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

    if ($req->getPost('action') === 'save_slot') {
        $data = [
            'SET_ID'             => (int)$req->getPost('set_id'),
            'SLOT_INDEX'         => (int)$req->getPost('slot_index'),
            'TITLE'              => trim($req->getPost('title')),
            'SUBTITLE'           => trim($req->getPost('subtitle')),
            'LINK'               => trim($req->getPost('link')),
            'COLOR'              => trim($req->getPost('color')),
            'CATEGORY_ID'        => (int)$req->getPost('category_id'),
            'TEXT_ALIGN'         => $req->getPost('text_align') ?: 'center',
            'TEXT_COLOR'         => $req->getPost('text_color') ?: '#ffffff',
            'TITLE_FONT_SIZE'    => $req->getPost('title_font_size') ?: '20px',
            'SUBTITLE_FONT_SIZE' => $req->getPost('subtitle_font_size') ?: '14px',
            'FONT_FAMILY'        => $req->getPost('font_family') ?: 'Open Sans',
            'FONT_WEIGHT'        => $req->getPost('font_weight') ?: 'normal',
            'FONT_STYLE'         => $req->getPost('font_style') ?: 'normal',
            'IMAGE_TYPE'         => $req->getPost('image_type') ?: 'background',
            'IMAGE_ALIGN'        => $req->getPost('image_align') ?: 'center',
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
        } elseif (isset($existing['IMAGE']) && empty($data['IMAGE'])) {
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
