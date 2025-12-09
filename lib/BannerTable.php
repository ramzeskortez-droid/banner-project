<?php
namespace MyCompany\Banner;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\DB\SqlExpression;

/**
 * Class BannerTable
 *
 * Maps to 'mycompany_banner' table.
 * Represents a single "Block" inside a "Banner" (which is a BannerSet).
 * In the database and code, this is a "Banner". In the UI, it's a "Block".
 *
 * @package MyCompany\Banner
 */
class BannerTable extends DataManager
{
    /**
     * Returns the physical table name.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'mycompany_banner';
    }

    /**
     * Defines the table structure (ORM map).
     *
     * @return array
     * @throws \Bitrix\Main\SystemException
     */
    public static function getMap()
    {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            
            // Foreign key to mycompany_banner_set table
            new IntegerField('SET_ID', [
                'required' => true,
                'title' => 'ID Баннера (набора)'
            ]),
            
            // The visual position of the block in the grid (1 to 8)
            new IntegerField('SLOT_INDEX', [
                'required' => true,
                'title' => 'Индекс слота'
            ]),

            // --- Content fields ---
            new StringField('TITLE', ['nullable' => true, 'title' => 'Заголовок']),
            new TextField('SUBTITLE', ['nullable' => true, 'title' => 'Подзаголовок (анонс)']),
            new StringField('LINK', ['nullable' => true, 'title' => 'Ссылка']),
            new StringField('IMAGE', ['nullable' => true, 'title' => 'Изображение (URL)']),

            // Fallback color if no image is present
            new StringField('COLOR', ['nullable' => true, 'title' => 'Цвет фона']),
            
            // Optional linked IBlock Section ID for auto-filling
            new IntegerField('CATEGORY_ID', ['nullable' => true, 'title' => 'ID раздела-источника']),

            // --- Typography and Style ---
            new StringField('TEXT_COLOR', ['default_value' => '#000000', 'title' => 'Цвет текста']),
            new StringField('TEXT_ALIGN', ['default_value' => 'center', 'title' => 'Выравнивание текста']),
            new StringField('TITLE_FONT_SIZE', ['default_value' => '22px', 'title' => 'Размер заголовка']),
            new StringField('SUBTITLE_FONT_SIZE', ['default_value' => '14px', 'title' => 'Размер анонса']),
            
            // Title text formatting
            new StringField('TITLE_BOLD', ['default_value' => 'N', 'values' => ['Y','N']]),
            new StringField('TITLE_ITALIC', ['default_value' => 'N', 'values' => ['Y','N']]),
            new StringField('TITLE_UNDERLINE', ['default_value' => 'N', 'values' => ['Y','N']]),

            // Subtitle text formatting
            new StringField('SUBTITLE_BOLD', ['default_value' => 'N', 'values' => ['Y','N']]),
            new StringField('SUBTITLE_ITALIC', ['default_value' => 'N', 'values' => ['Y','N']]),
            new StringField('SUBTITLE_UNDERLINE', ['default_value' => 'N', 'values' => ['Y','N']]),

            // --- Image positioning ---
            new IntegerField('IMG_SCALE', ['default_value' => 100, 'title' => 'Масштаб изображения (%)']),
            new IntegerField('IMG_POS_X', ['default_value' => 50, 'title' => 'Позиция изображения по X (%)']),
            new IntegerField('IMG_POS_Y', ['default_value' => 50, 'title' => 'Позиция изображения по Y (%)']),
            
            // Sorting order
            new IntegerField('SORT', ['default_value' => 500]),

            // Deprecated/unused fields from older versions
            new StringField('IMAGE_TYPE', ['default_value' => 'background']),
            new StringField('IMAGE_ALIGN', ['default_value' => 'center']),
            new StringField('FONT_FAMILY', ['default_value' => 'Open Sans']),
            new StringField('FONT_WEIGHT', ['default_value' => 'normal']),
            new StringField('FONT_STYLE', ['default_value' => 'normal']),
        ];
    }
}
