<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arResult */
/** @var CBitrixComponentTemplate $this */
/** @var CBitrixComponent $component */

// Note: This template relies on $arResult['SET_SETTINGS'] being populated by the component's class.php
$setSettings = $arResult['SET_SETTINGS'] ?? [];
$showTextBg = ($setSettings['TEXT_BG_SHOW'] ?? 'N') === 'Y';
$textBgStyle = '';

if ($showTextBg) {
    if (!function_exists('myBannerHexToRgba')) {
        function myBannerHexToRgba($hex, $opacity) {
            $hex = str_replace("#", "", $hex);
            if(strlen($hex) == 3) {
               $r = hexdec(substr($hex,0,1).substr($hex,0,1));
               $g = hexdec(substr($hex,1,1).substr($hex,1,1));
               $b = hexdec(substr($hex,2,1).substr($hex,2,1));
            } else {
               $r = hexdec(substr($hex,0,2));
               $g = hexdec(substr($hex,2,2));
               $b = hexdec(substr($hex,4,2));
            }
            $alpha = ($opacity ?? 90) / 100;
            return "rgba($r, $g, $b, $alpha)";
        }
    }
    $textBgStyle = "background-color: " . myBannerHexToRgba(
        $setSettings['TEXT_BG_COLOR'] ?? '#ffffff',
        $setSettings['TEXT_BG_OPACITY'] ?? 90
    ) . ";";
}

?>
<div class="my-banner-grid">
<?php foreach($arResult['BANNERS'] as $banner):
    if(!$banner) continue;
    
    $slotIndex = (int)$banner['SLOT_INDEX'];
    $sizeClass = ($slotIndex <= 4) ? 'large' : 'small'; // This logic might be outdated if sorting is used
    $textAlign = $banner['TEXT_ALIGN'] ?: 'center';
    $classes = "banner-item {$sizeClass}";

    // --- Background Styles ---
    $bgStyles = "background-color: " . htmlspecialcharsbx($banner['COLOR']) . ";";
    if ($banner['IMAGE']) {
        $bgStyles .= " background-image: url('".htmlspecialcharsbx($banner['IMAGE'])."');";
        $bgStyles .= " background-size: " . (int)($banner['IMG_SCALE'] ?? 100) . "%;";
        $bgStyles .= " background-position: " . (int)($banner['IMG_POS_X'] ?? 50) . "% " . (int)($banner['IMG_POS_Y'] ?? 50) . "%;";
    }

    // --- Text Container Styles ---
    $textContainerStyles = $textBgStyle;

    // --- Text Styles ---
    $textStyles = "color: " . htmlspecialcharsbx($banner['TEXT_COLOR'] ?: '#000000') . ";";
    if ($banner['FONT_FAMILY']) {
        $textStyles .= " font-family: '" . htmlspecialcharsbx($banner['FONT_FAMILY']) . "';";
    }
?>
    <a href="<?=htmlspecialcharsbx($banner['LINK'])?>" class="<?=$classes?>" style="<?=$bgStyles?>">
        <div class="banner-slot-content text-<?=$textAlign?>" style="<?=$textContainerStyles?>">
            <div style="<?=$textStyles?>">
                <?php if($banner['TITLE']): ?><div class="b-title" style="font-size: <?=htmlspecialcharsbx($banner['TITLE_FONT_SIZE'])?>;"><?=$banner['TITLE']?></div><?php endif; ?>
                <?php if($banner['SUBTITLE']): ?><div class="b-sub" style="font-size: <?=htmlspecialcharsbx($banner['SUBTITLE_FONT_SIZE'])?>;"><?=$banner['SUBTITLE']?></div><?php endif; ?>
            </div>
        </div>
    </a>
<?php endforeach; ?>
</div>
