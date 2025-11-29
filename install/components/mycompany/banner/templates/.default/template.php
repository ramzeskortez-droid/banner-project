<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */
$this->setFrameMode(true);

if (empty($arResult['BANNERS'])) {
    return;
}
?>

<div class="banner-grid-container">
    <?php for ($i = 1; $i <= 8; $i++):
        $banner = $arResult['BANNERS'][$i] ?? null;
        $isLarge = $i <= 4;
        $class = $isLarge ? 'large' : 'small';
        if (!$banner) continue; // Не выводим пустые слоты
    ?>
        <a href="<?=htmlspecialcharsbx($banner['LINK_URL'] ?? '#')?>" class="banner-slot banner-slot-<?=$class?>" style="background-color: <?=htmlspecialcharsbx($banner['THEME_COLOR'])?>; color: #fff;">
            <div class="banner-slot-content">
                <div class="banner-slot-title"><?=htmlspecialcharsbx($banner['TITLE'])?></div>
                <div class="banner-slot-announcement"><?=htmlspecialcharsbx($banner['ANNOUNCEMENT'])?></div>
            </div>
            <?php if ($isLarge && !empty($banner['IMAGE_LINK'])): ?>
                <img src="<?=htmlspecialcharsbx($banner['IMAGE_LINK'])?>" alt="" class="banner-slot-image">
            <?php endif; ?>
        </a>
    <?php endfor; ?>
</div>