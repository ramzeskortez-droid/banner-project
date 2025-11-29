<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;
use MyCompany\Banner\BannerSetTable;

if (!Loader::includeModule('mycompany.banner')) {
    return;
}

$bannerSets = [];
try {
    $res = BannerSetTable::getList([
        'select' => ['ID', 'NAME'],
        'order' => ['NAME' => 'ASC']
    ]);
    while ($row = $res->fetch()) {
        $bannerSets[$row['ID']] = '[' . $row['ID'] . '] ' . $row['NAME'];
    }
} catch (\Exception $e) {
    // Модуль может быть еще не установлен, или таблица не создана
}

$arComponentParameters = [
    "GROUPS" => [],
    "PARAMETERS" => [
        "BANNER_SET_ID" => [
            "PARENT" => "BASE",
            "NAME" => "Набор баннеров",
            "TYPE" => "LIST",
            "VALUES" => $bannerSets,
            "ADDITIONAL_VALUES" => "N",
            "REFRESH" => "N",
        ],
    ],
];