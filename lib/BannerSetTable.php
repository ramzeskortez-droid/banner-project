<?php
namespace MyCompany\Banner;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

class BannerSetTable extends DataManager
{
    public static function getTableName() { return 'mycompany_banner_set'; }
    public static function getMap() {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new StringField('NAME', ['required' => true, 'title' => 'Название набора']),
        ];
    }
}
