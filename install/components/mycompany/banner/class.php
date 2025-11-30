<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;
use MyCompany\Banner\BannerTable;
use MyCompany\Banner\BannerSetTable;

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

        if ($this->arParams['BANNER_SET_ID'] > 0) {
            $this->arResult['SET'] = BannerSetTable::getById($this->arParams['BANNER_SET_ID'])->fetch();
        }

        $this->arResult['BANNERS'] = [];
        $res = BannerTable::getList([
            'filter' => ['=SET_ID' => $this->arParams['BANNER_SET_ID']],
            'order' => ['SORT' => 'ASC', 'ID' => 'DESC'] // Order by new SORT field
        ]);
        while ($row = $res->fetch()) {
            // Using SLOT_INDEX as key might be outdated, but let's keep it for now unless it breaks something.
            // The constructor now sorts by SORT field, so the public part should too.
            $this->arResult['BANNERS'][] = $row;
        }

        $this->includeComponentTemplate();
    }
}
