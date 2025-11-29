<?php
namespace MyCompany\Banner;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

class BannerTable extends DataManager
{
    public static function getTableName() {
        return 'mycompany_banner';
    }

    public static function getMap()
    {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new IntegerField('SET_ID'),
            new IntegerField('SLOT_INDEX'),
            new StringField('TITLE'),
            new TextField('SUBTITLE'),
            new StringField('LINK'),
            new StringField('COLOR'),
            new StringField('IMAGE'),
            
            // New fields for v1.0.9
            new IntegerField('CATEGORY_ID', ['nullable' => true]),
            new StringField('IMAGE_TYPE', [
                'default_value' => 'background', // 'background' or 'icon'
            ]),
            new StringField('IMAGE_ALIGN', [
                'default_value' => 'center', // 'left', 'center', 'right'
            ]),
        ];
    }
}
