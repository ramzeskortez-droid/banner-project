<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;
use MyCompany\Banner\BannerTable;

class BannerComponent extends CBitrixComponent
{
    public function onPrepareComponentParams($arParams)
    {
        $arParams['BANNER_SET_ID'] = (int)($arParams['BANNER_SET_ID'] ?? 0);
        return $arParams;
    }

    public function executeComponent()
    {
        if (!Loader::includeModule('mycompany.banner')) {
            ShowError("Модуль mycompany.banner не установлен.");
            return;
        }

        if ($this->arParams['BANNER_SET_ID'] <= 0) {
            ShowError("Не выбран набор баннеров.");
            return;
        }

        $this->arResult['BANNERS'] = [];
        $res = BannerTable::getList([
            'filter' => ['=SET_ID' => $this->arParams['BANNER_SET_ID']],
            'order' => ['SLOT_INDEX' => 'ASC']
        ]);
        while ($row = $res->fetch()) {
            $this->arResult['BANNERS'][$row['SLOT_INDEX']] = $row;
        }

        $this->includeComponentTemplate();
    }
}