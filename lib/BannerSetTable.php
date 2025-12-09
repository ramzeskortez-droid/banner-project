<?php
namespace MyCompany\Banner;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\BooleanField;

class BannerSetTable extends DataManager
{
    public static function getTableName() { return 'mycompany_banner_set'; }
    public static function getMap() {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new StringField('NAME', ['required' => true, 'title' => 'Название набора']),
            new BooleanField('TEXT_BG_SHOW', ['values' => ['N', 'Y'], 'default_value' => 'N']),
            new StringField('TEXT_BG_COLOR', ['default_value' => '#ffffff']),
            new IntegerField('TEXT_BG_OPACITY', ['default_value' => 90]),
            new BooleanField('USE_GLOBAL_TEXT_COLOR', ['values' => ['N', 'Y'], 'default_value' => 'N']),
            new StringField('GLOBAL_TEXT_COLOR', ['default_value' => '#000000']),
            new \Bitrix\Main\ORM\Fields\BooleanField('CATEGORY_MODE', ['values' => ['N', 'Y'], 'default_value' => 'Y']),
            new \Bitrix\Main\Entity\DatetimeField('DATE_CREATE', [
                'default_value' => function() { return new \Bitrix\Main\Type\DateTime(); }
            ]),
        ];
    }
}