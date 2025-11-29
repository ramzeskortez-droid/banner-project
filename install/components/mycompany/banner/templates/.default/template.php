<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arResult */
?>
<div class="my-banner-grid">
<?php foreach($arResult['BANNERS'] as $banner):
    if(!$banner) continue;
    
    // Classes
    $slotIndex = (int)$banner['SLOT_INDEX'];
    $sizeClass = ($slotIndex <= 4) ? 'large' : 'small';
    $imageTypeClass = 'is-' . ($banner['IMAGE_TYPE'] ?: 'background');
    $alignClass = 'align-' . ($banner['IMAGE_ALIGN'] ?: 'center');
    $fontClass = 'fs-' . ($banner['FONT_SIZE'] ?: 'normal');
    $classes = "banner-item {$sizeClass} {$imageTypeClass} {$alignClass}";

    // Styles
    $styles = "background-color: {$banner['COLOR']};";
    if ($imageTypeClass === 'is-background' && $banner['IMAGE']) {
        $styles .= " background-image: url('".htmlspecialcharsbx($banner['IMAGE'])."');";
    }
?>
    <a href="<?=htmlspecialcharsbx($banner['LINK'])?>" class="<?=$classes?>" style="<?=$styles?>">
        <div class="banner-slot-content <?=$fontClass?>" style="color: <?=htmlspecialcharsbx($banner['TEXT_COLOR'])?>;">
            <?php if($banner['TITLE']): ?><div class="b-title"><?=$banner['TITLE']?></div><?php endif; ?>
            <?php if($banner['SUBTITLE']): ?><div class="b-sub"><?=$banner['SUBTITLE']?></div><?php endif; ?>
        </div>

        <?php if($imageTypeClass === 'is-icon' && $banner['IMAGE']): ?>
            <div class="b-icon-wrapper">
                <img src="<?=htmlspecialcharsbx($banner['IMAGE'])?>" class="b-icon-img" alt="">
            </div>
        <?php endif; ?>
    </a>
<?php endforeach; ?>
</div>