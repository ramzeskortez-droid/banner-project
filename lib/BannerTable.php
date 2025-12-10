<?php
namespace MyCompany\Banner;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\BooleanField;

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
            new IntegerField('SET_ID', ['required' => true]),
            new IntegerField('SLOT_INDEX', ['required' => true]),
            new IntegerField('SORT', ['default_value' => 500]),
            
            new StringField('TITLE'),
            new TextField('SUBTITLE'),
            new StringField('LINK'),
            
            new StringField('IMAGE'),
            new IntegerField('IMG_SCALE', ['default_value' => 100]),
            new StringField('IMG_POS_X', ['default_value' => '50']),
            new StringField('IMG_POS_Y', ['default_value' => '50']),
            new StringField('IMG_POS_X_ALIGN', ['default_value' => 'center']),
            new StringField('IMG_POS_Y_ALIGN', ['default_value' => 'center']),

            new StringField('COLOR', ['default_value' => '#f5f5f5']),
            new StringField('TEXT_COLOR', ['default_value' => '#000000']),
            new StringField('TEXT_ALIGN', ['default_value' => 'center']),
            
            // Typography
            new StringField('TITLE_FONT_SIZE', ['default_value' => '22px']),
            new StringField('SUBTITLE_FONT_SIZE', ['default_value' => '14px']),
            new StringField('TITLE_BOLD', ['default_value' => 'N']),
            new StringField('TITLE_ITALIC', ['default_value' => 'N']),
            new StringField('TITLE_UNDERLINE', ['default_value' => 'N']),
            new StringField('SUBTITLE_BOLD', ['default_value' => 'N']),
            new StringField('SUBTITLE_ITALIC', ['default_value' => 'N']),
            new StringField('SUBTITLE_UNDERLINE', ['default_value' => 'N']),

            // New Styles fields
            new BooleanField('TEXT_BG_SHOW', ['values' => ['N', 'Y'], 'default_value' => 'N']),
            new StringField('TEXT_BG_COLOR', ['default_value' => '#ffffff']),
            new IntegerField('TEXT_BG_OPACITY', ['default_value' => 90]),
            new IntegerField('TEXT_STROKE_WIDTH', ['default_value' => 0]),
            new StringField('TEXT_STROKE_COLOR', ['default_value' => '#000000']),
            new BooleanField('HOVER_ANIMATION', ['values' => ['N', 'Y'], 'default_value' => 'N']),
            
            new IntegerField('CATEGORY_ID'),
            new StringField('CATEGORY_MODE', ['default_value' => 'N']),
        ];
    }
}