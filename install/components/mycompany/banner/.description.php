<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arComponentDescription = array(
    "NAME" => "Красивый баннер v1",
    "DESCRIPTION" => "Выводит рекламный блок с заголовком и кнопкой",
    "ICON" => "/images/banner.gif",
    "SORT" => 10,
    "CACHE_PATH" => "Y",
    "PATH" => array(
        "ID" => "my_company_group",
        "NAME" => "Моя компания", // Название папки в списке компонентов
        "CHILD" => array(
            "ID" => "my_banner",
            "NAME" => "Баннеры"
        )
    ),
);