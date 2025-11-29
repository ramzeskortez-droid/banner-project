<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
/** @var array $arParams */
/** @var array $arResult */

$this->setFrameMode(true);

if (empty($arResult['BANNERS'])) {
    return;
}
?>

<div class="banner-grid-container">
    <?php for ($i = 1; $i <= 8; $i++):
        $banner = $arResult['BANNERS'][$i] ?? null;
        if (!$banner) continue; // Не выводим пустые слоты

        // Определяем класс для размера слота
        $isLarge = ($i <= 4);
        $sizeClass = $isLarge ? 'banner-slot-large' : 'banner-slot-small';
    ?>
        <a href="<?=htmlspecialcharsbx($banner['LINK'] ?? '#')?>" class="banner-slot <?=$sizeClass?>" style="background-color: <?=htmlspecialcharsbx($banner['COLOR'])?>;">
            <div class="banner-slot-content">
                <?php if (!empty($banner['TITLE'])): ?>
                    <div class="banner-slot-title"><?=htmlspecialcharsbx($banner['TITLE'])?></div>
                <?php endif; ?>
                <?php if (!empty($banner['SUBTITLE'])): ?>
                    <div class="banner-slot-announcement"><?=htmlspecialcharsbx($banner['SUBTITLE'])?></div>
                <?php endif; ?>
            </div>
            <?php if ($isLarge && !empty($banner['IMAGE'])): ?>
                <img src="<?=htmlspecialcharsbx($banner['IMAGE'])?>" alt="" class="banner-slot-image">
            <?php endif; ?>
        </a>
    <?php endfor; ?>
</div>
