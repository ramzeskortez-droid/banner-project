<?php
namespace MyCompany\Banner;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

class BannerTable extends DataManager
{
    public static function getTableName()
    {
        return 'mycompany_banner';
    }

    public static function getMap()
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new IntegerField('SET_ID', [
                'default_value' => 1
            ]),
            new IntegerField('SLOT_INDEX', [
                'required' => true,
            ]),
            new StringField('TITLE'),
            new StringField('SUBTITLE'),
            new StringField('IMAGE'),
            new StringField('IMAGE_POSITION', [
                'default_value' => 'left'
            ]),
            new StringField('COLOR', [
                'default_value' => '#f5f5f5'
            ]),
            new StringField('LINK'),
        ];
    }
}