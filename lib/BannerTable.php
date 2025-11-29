<?php
namespace MyCompany\Banner;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

class BannerTable extends DataManager
{
    public static function getTableName() { return 'mycompany_banner'; }

    public static function getMap()
    {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new IntegerField('SET_ID'),
            new IntegerField('SLOT_INDEX'),
            new StringField('TITLE', ['nullable' => true]),
            new TextField('SUBTITLE', ['nullable' => true]),
            new StringField('LINK', ['nullable' => true]),
            new StringField('COLOR', ['nullable' => true]),

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

            new StringField('TEXT_COLOR', [
                'default_value' => '#333333'
            ]),
            new StringField('FONT_SIZE', [
                'default_value' => 'normal'
            ]),
            // New field for v1.0.11
            new StringField('TEXT_ALIGN', [
                'default_value' => 'center'
            ]),
        ];
    }
}
