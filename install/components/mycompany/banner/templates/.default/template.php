<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die(); ?>

<div class="my-banner-grid">
    <?php for ($i = 1; $i <= 10; $i++): ?>
        <?php if (isset($arResult['BANNERS'][$i]) && !empty($arResult['BANNERS'][$i])): 
            $banner = $arResult['BANNERS'][$i];
            $isLarge = $i <= 4;
            $class = $isLarge ? 'my-banner-large' : 'my-banner-small';
            $styles = "background-color: " . htmlspecialcharsbx($banner['COLOR']) . ";";
            if ($isLarge && $banner['IMAGE_POSITION'] == 'right') {
                $styles .= " flex-direction: row-reverse;";
            }
        ?>
            <a href="<?=htmlspecialcharsbx($banner['LINK'])?>" class="my-banner-slot <?=$class?>" style="<?=$styles?>">
                
                <div class="my-banner-content">
                    <?php if ($banner['TITLE']): ?>
                        <div class="my-banner-title"><?=htmlspecialcharsbx($banner['TITLE'])?></div>
                    <?php endif; ?>
                    <?php if ($banner['SUBTITLE']): ?>
                        <div class="my-banner-subtitle"><?=htmlspecialcharsbx($banner['SUBTITLE'])?></div>
                    <?php endif; ?>
                </div>

                <?php if ($isLarge && $banner['IMAGE']): ?>
                    <div class="my-banner-image">
                        <img src="<?=htmlspecialcharsbx($banner['IMAGE'])?>" alt="<?=htmlspecialcharsbx($banner['TITLE'])?>">
                    </div>
                <?php endif; ?>
            </a>
        <?php endif; ?>
    <?php endfor; ?>
</div>