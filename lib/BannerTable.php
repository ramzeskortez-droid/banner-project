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
            new StringField('IMAGE', ['nullable' => true]),
            new IntegerField('CATEGORY_ID', ['nullable' => true]),
            new StringField('IMAGE_TYPE', ['default_value' => 'background']),
            new StringField('IMAGE_ALIGN', ['default_value' => 'center']),
            new StringField('TEXT_COLOR', ['default_value' => '#000000']),
            new StringField('TEXT_ALIGN', ['default_value' => 'center']),
            new StringField('TITLE_FONT_SIZE', ['default_value' => '22px']),
            new StringField('SUBTITLE_FONT_SIZE', ['default_value' => '14px']),
            new StringField('FONT_FAMILY', ['default_value' => 'Open Sans']),
            new StringField('FONT_WEIGHT', ['default_value' => 'normal']),
            new StringField('FONT_STYLE', ['default_value' => 'normal']),
            new IntegerField('SORT', ['default_value' => 500]),
            new IntegerField('IMG_SCALE', ['default_value' => 100]),
            new IntegerField('IMG_POS_X', ['default_value' => 50]),
            new IntegerField('IMG_POS_Y', ['default_value' => 50]),
            new StringField('TITLE_BOLD', ['default_value' => 'N']),
            new StringField('TITLE_ITALIC', ['default_value' => 'N']),
            new StringField('TITLE_UNDERLINE', ['default_value' => 'N']),
            new StringField('SUBTITLE_BOLD', ['default_value' => 'N']),
            new StringField('SUBTITLE_ITALIC', ['default_value' => 'N']),
            new StringField('SUBTITLE_UNDERLINE', ['default_value' => 'N']),
        ];
    }
}