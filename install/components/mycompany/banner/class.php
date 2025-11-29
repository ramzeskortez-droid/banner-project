<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use MyCompany\Banner\BannerTable;

class MyCompanyBanner extends \CBitrixComponent
{
    public function executeComponent()
    {
        if (Loader::includeModule('mycompany.banner')) {
            
            $bannersRaw = BannerTable::getList([
                'filter' => ['=SET_ID' => 1],
                'order' => ['SLOT_INDEX' => 'ASC']
            ])->fetchAll();
            
            $banners = [];
            foreach ($bannersRaw as $banner) {
                $banners[$banner['SLOT_INDEX']] = $banner;
            }

            $this->arResult['BANNERS'] = $banners;
        }

        $this->includeComponentTemplate();
    }
}