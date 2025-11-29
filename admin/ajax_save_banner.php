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
            'SET_ID'       => (int)$req->getPost('set_id'),
            'SLOT_INDEX'   => (int)$req->getPost('slot_index'),
            'TITLE'        => trim($req->getPost('title')),
            'SUBTITLE'     => trim($req->getPost('subtitle')),
            'LINK'         => trim($req->getPost('link')),
            'COLOR'        => trim($req->getPost('color')),
            'CATEGORY_ID'  => (int)$req->getPost('category_id'),
            'IMAGE_TYPE'   => $req->getPost('image_type') ?: 'background',
            'IMAGE_ALIGN'  => $req->getPost('image_align') ?: 'center',
            'TEXT_COLOR'   => $req->getPost('text_color') ?: '#333333',
            'FONT_SIZE'    => $req->getPost('font_size') ?: 'normal',
        ];

        if ($req->getPost('category_mode') === 'Y' && $data['CATEGORY_ID'] > 0 && Loader::includeModule('iblock')) {
            $res = CIBlockSection::GetByID($data['CATEGORY_ID']);
            if ($sec = $res->GetNext()) {
                $data['TITLE'] = $sec['NAME'];
                $data['LINK'] = $sec['SECTION_PAGE_URL'];
                $data['SUBTITLE'] = strip_tags($sec['DESCRIPTION']);
            }
        }
        
        $exist = BannerTable::getList(['filter'=>['SET_ID'=>$data['SET_ID'], 'SLOT_INDEX'=>$data['SLOT_INDEX']]])->fetch();

        if (!empty($_FILES['image_file']['name'])) {
            $fid = \CFile::SaveFile($_FILES['image_file'], 'mycompany.banner');
            $data['IMAGE'] = $fid ? \CFile::GetPath($fid) : '';
        } elseif ($url = trim($req->getPost('image_url'))) {
            $data['IMAGE'] = $url;
        } else {
            // If no new image, keep the old one or set to empty if it's a new banner
            $data['IMAGE'] = $exist['IMAGE'] ?? '';
        }

        if($exist) {
            $res = BannerTable::update($exist['ID'], $data);
        } else {
            $res = BannerTable::add($data);
        }

        if($res->isSuccess()) {
            $resp['success'] = true;
            $id = $exist ? $exist['ID'] : $res->getId();
            $resp['data'] = BannerTable::getById($id)->fetch();
        } else {
            $resp['errors'] = $res->getErrorMessages();
        }
    }
} catch (\Exception $e) {
    $resp['errors'][] = $e->getMessage();
}

echo json_encode($resp);
