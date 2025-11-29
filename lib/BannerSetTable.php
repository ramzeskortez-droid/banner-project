<?php
namespace MyCompany\Banner;

use Bitrix\Main\Entity;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Class BannerSetTable
 *
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> NAME string(255) mandatory
 * </ul>
 *
 * @package MyCompany\Banner
 **/

class BannerSetTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'mycompany_banner_set';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new Entity\StringField('NAME', ['required' => true]),
        ];
    }
}