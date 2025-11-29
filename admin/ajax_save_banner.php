<?php
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use MyCompany\Banner\BannerTable;

$resp = ['success' => false, 'errors' => []];
Loader::includeModule('mycompany.banner');
$req = Application::getInstance()->getContext()->getRequest();

if ($req->getPost('action') === 'save_slot' && check_bitrix_sessid()) {
    $data = [
        'SET_ID' => (int)$req->getPost('set_id'),
        'SLOT_INDEX' => (int)$req->getPost('slot_index'),
        'TITLE' => $req->getPost('title'),
        'SUBTITLE' => $req->getPost('subtitle'),
        'LINK' => $req->getPost('link'),
        'COLOR' => $req->getPost('color'),
    ];

    // Обработка картинки
    if (!empty($_FILES['image_file']['name'])) {
        $fid = \CFile::SaveFile($_FILES['image_file'], 'mycompany.banner');
        if($fid) $data['IMAGE'] = \CFile::GetPath($fid);
    } elseif ($url = $req->getPost('image_url')) {
        $data['IMAGE'] = $url;
    }

    $exist = BannerTable::getList(['filter'=>['SET_ID'=>$data['SET_ID'], 'SLOT_INDEX'=>$data['SLOT_INDEX']]])->fetch();
    
    if($exist) $res = BannerTable::update($exist['ID'], $data);
    else $res = BannerTable::add($data);

    if($res->isSuccess()) {
        $resp['success'] = true;
        $id = $exist ? $exist['ID'] : $res->getId();
        $resp['data'] = BannerTable::getById($id)->fetch();
    } else {
        $resp['errors'] = $res->getErrorMessages();
    }
} else {
    $resp['errors'][] = 'Ошибка действия или сессии';
}

header('Content-Type: application/json');
echo json_encode($resp);
