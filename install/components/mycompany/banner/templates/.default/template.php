<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arResult */
?>
<div class="my-banner-grid">
<?php foreach($arResult['BANNERS'] as $banner):
    if(!$banner) continue;
    
    $slotIndex = (int)$banner['SLOT_INDEX'];
    $sizeClass = ($slotIndex <= 4) ? 'large' : 'small';
    $textAlign = $banner['TEXT_ALIGN'] ?: 'center';
    $fontClass = 'fs-' . ($banner['FONT_SIZE'] ?: 'normal');
    $classes = "banner-item {$sizeClass}";

    $styles = "background-color: {$banner['COLOR']};";
    if ($banner['IMAGE']) {
        $styles .= " background-image: url('".htmlspecialcharsbx($banner['IMAGE'])."');";
    }
?>
    <a href="<?=htmlspecialcharsbx($banner['LINK'])?>" class="<?=$classes?>" style="<?=$styles?>">
        <div class="banner-slot-content text-<?=$textAlign?> <?=$fontClass?>" style="color: <?=htmlspecialcharsbx($banner['TEXT_COLOR'])?>;">
            <?php if($banner['TITLE']): ?><div class="b-title"><?=$banner['TITLE']?></div><?php endif; ?>
            <?php if($banner['SUBTITLE']): ?><div class="b-sub"><?=$banner['SUBTITLE']?></div><?php endif; ?>
        </div>
    </a>
<?php endforeach; ?>
</div>
