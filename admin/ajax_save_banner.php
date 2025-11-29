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
        ];

        // If Category Mode is on, get data from iblock section
        if ($data['CATEGORY_ID'] > 0 && Loader::includeModule('iblock')) {
            $res = CIBlockSection::GetByID($data['CATEGORY_ID']);
            if ($sec = $res->GetNext()) {
                $data['TITLE'] = $sec['NAME'];
                $data['LINK'] = $sec['SECTION_PAGE_URL'];
                $data['SUBTITLE'] = $sec['DESCRIPTION'];
            }
        }
        
        // Handle image upload
        $currentImage = null;
        $exist = BannerTable::getList(['filter'=>['SET_ID'=>$data['SET_ID'], 'SLOT_INDEX'=>$data['SLOT_INDEX']]])->fetch();
        if ($exist) {
            $currentImage = $exist['IMAGE'];
        }

        if (!empty($_FILES['image_file']['name'])) {
            $fid = \CFile::SaveFile($_FILES['image_file'], 'mycompany.banner');
            if($fid) $data['IMAGE'] = \CFile::GetPath($fid);
        } elseif ($url = $req->getPost('image_url')) {
            $data['IMAGE'] = trim($url);
        } else {
             $data['IMAGE'] = $currentImage; // Keep old image if nothing new is provided
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

    } else {
        $resp['errors'][] = 'Неверное действие';
    }

} catch (\Exception $e) {
    $resp['errors'][] = $e->getMessage();
}

echo json_encode($resp);