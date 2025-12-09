<?php
namespace MyCompany\Banner;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Entity\DatetimeField;

/**
 * Class BannerSetTable
 *
 * Maps to 'mycompany_banner_set' table.
 * Represents the "Banner" entity which is a container for multiple "Blocks".
 * In the database and code, this is a "Set". In the UI, it's a "Banner".
 *
 * @package MyCompany\Banner
 */
class BannerSetTable extends DataManager
{
    /**
     * Returns the physical table name.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'mycompany_banner_set';
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
            // Primary key
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
                'title' => 'ID'
            ]),

            // The name of the Banner (set)
            new StringField('NAME', [
                'required' => true,
                'title' => 'Название Баннера'
            ]),

            // --- Settings for text background ---
            new BooleanField('TEXT_BG_SHOW', [
                'values' => ['N', 'Y'],
                'default_value' => 'N',
                'title' => 'Показывать фон под текстом'
            ]),
            new StringField('TEXT_BG_COLOR', [
                'default_value' => '#ffffff',
                'title' => 'Цвет фона под текстом'
            ]),
            new IntegerField('TEXT_BG_OPACITY', [
                'default_value' => 90,
                'title' => 'Прозрачность фона под текстом'
            ]),
            
            // --- Global text color settings ---
            new BooleanField('USE_GLOBAL_TEXT_COLOR', [
                'values' => ['N', 'Y'],
                'default_value' => 'N',
                'title' => 'Использовать единый цвет текста'
            ]),
            new StringField('GLOBAL_TEXT_COLOR', [
                'default_value' => '#000000',
                'title' => 'Единый цвет текста'
            ]),
            
            // If enabled, the constructor will try to pre-fill blocks from IBlock sections
            new BooleanField('CATEGORY_MODE', [
                'values' => ['N', 'Y'],
                'default_value' => 'Y',
                'title' => 'Режим автозаполнения из категорий'
            ]),
            
            // Creation date
            new DatetimeField('DATE_CREATE', [
                'default_value' => function() {
                    return new DateTime();
                },
                'title' => 'Дата создания'
            ]),
        ];
    }
}
