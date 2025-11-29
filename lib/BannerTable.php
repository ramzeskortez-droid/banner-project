<?php
namespace MyCompany\Banner;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

class BannerTable extends DataManager
{
    public static function getTableName()
    {
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

            // Fix for v1.0.10: Image is not required
            new StringField('IMAGE', [
                'nullable' => true,
                'default_value' => ''
            ]),
            
            new IntegerField('CATEGORY_ID', ['nullable' => true]),
            
            new StringField('IMAGE_TYPE', [
                'default_value' => 'background',
            ]),
            new StringField('IMAGE_ALIGN', [
                'default_value' => 'center',
            ]),

            // New fields for v1.0.10
            new StringField('TEXT_COLOR', [
                'default_value' => '#333333'
            ]),
            new StringField('FONT_SIZE', [
                'default_value' => 'normal' // small, normal, large
            ]),
        ];
    }
}