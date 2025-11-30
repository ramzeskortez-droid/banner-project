<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arResult */
?>
<div class="my-banner-grid">
<?php foreach($arResult['BANNERS'] as $banner):
    if(!$banner) continue;
    
    $slotIndex = (int)$banner['SLOT_INDEX'];
    $sizeClass = ($slotIndex <= 4) ? 'large' : 'small';
    $textAlign = $banner['TEXT_ALIGN'] ?: 'center';
    $classes = "banner-item {$sizeClass}";

    $bgStyles = "background-color: {$banner['COLOR']};";
    if ($banner['IMAGE']) {
        $bgStyles .= " background-image: url('".htmlspecialcharsbx($banner['IMAGE'])."');";
    }

    $textStyles = "color: " . htmlspecialcharsbx($banner['TEXT_COLOR']) . ";";
    if ($banner['FONT_FAMILY']) {
        $textStyles .= " font-family: '" . htmlspecialcharsbx($banner['FONT_FAMILY']) . "';";
    }
    if ($banner['FONT_WEIGHT']) {
        $textStyles .= " font-weight: " . htmlspecialcharsbx($banner['FONT_WEIGHT']) . ";";
    }
    if ($banner['FONT_STYLE']) {
        $textStyles .= " font-style: " . htmlspecialcharsbx($banner['FONT_STYLE']) . ";";
    }
?>
    <a href="<?=htmlspecialcharsbx($banner['LINK'])?>" class="<?=$classes?>" style="<?=$bgStyles?>">
        <div class="banner-slot-content text-<?=$textAlign?>" style="<?=$textStyles?>">
            <?php if($banner['TITLE']): ?><div class="b-title" style="font-size: <?=htmlspecialcharsbx($banner['TITLE_FONT_SIZE'])?>;"><?=$banner['TITLE']?></div><?php endif; ?>
            <?php if($banner['SUBTITLE']): ?><div class="b-sub" style="font-size: <?=htmlspecialcharsbx($banner['SUBTITLE_FONT_SIZE'])?>;"><?=$banner['SUBTITLE']?></div><?php endif; ?>
        </div>
    </a>
<?php endforeach; ?>
</div>