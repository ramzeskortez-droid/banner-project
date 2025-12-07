<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arResult */

$set = $arResult['SET'] ?? [];
$globalBg = "";
if ($set && $set['TEXT_BG_SHOW'] == 'Y') {
    $hex = $set['TEXT_BG_COLOR'] ?: '#ffffff';
    $op = isset($set['TEXT_BG_OPACITY']) ? (int)$set['TEXT_BG_OPACITY'] : 90;
    $hex = ltrim($hex, '#');
    if(strlen($hex)==3) { $r=hexdec($hex[0].$hex[0]); $g=hexdec($hex[1].$hex[1]); $b=hexdec($hex[2].$hex[2]); }
    else { $r=hexdec(substr($hex,0,2)); $g=hexdec(substr($hex,2,2)); $b=hexdec(substr($hex,4,2)); }
    $globalBg = "background-color: rgba($r, $g, $b, ".($op/100).");";
}
?>
<div class="my-banner-grid">
<?php foreach($arResult['BANNERS'] as $i => $banner):
    if(!$banner) continue;

    $sizeClass = ($i < 4) ? 'large' : 'small';
    $classes = "banner-item {$sizeClass}";

    $imgStyle = "background-color: ".htmlspecialcharsbx($banner['COLOR']).";";
    if($banner['IMAGE']) {
       $imgStyle .= " background-image: url('".htmlspecialcharsbx($banner['IMAGE'])."');";
       $scale = $banner['IMG_SCALE'] ? (int)$banner['IMG_SCALE'] : 100;
       $posX = isset($banner['IMG_POS_X']) ? (int)$banner['IMG_POS_X'] : 50;
       $posY = isset($banner['IMG_POS_Y']) ? (int)$banner['IMG_POS_Y'] : 50;
       $imgStyle .= " background-size: {$scale}%; background-position: {$posX}% {$posY}%;";
    }

    $textColor = $banner['TEXT_COLOR'] ?: '#000000';
    $textAlign = $banner['TEXT_ALIGN'] ?: 'center';

    // Обработка размеров шрифта с добавлением 'px' для числовых значений
    $tSize = (is_numeric($banner['TITLE_FONT_SIZE']) && $banner['TITLE_FONT_SIZE'] > 0) ? $banner['TITLE_FONT_SIZE'].'px' : ($banner['TITLE_FONT_SIZE'] ?: '22px');
    $sSize = (is_numeric($banner['SUBTITLE_FONT_SIZE']) && $banner['SUBTITLE_FONT_SIZE'] > 0) ? $banner['SUBTITLE_FONT_SIZE'].'px' : ($banner['SUBTITLE_FONT_SIZE'] ?: '14px');
    
    $titleStyle = "font-size:" . $tSize . ";";
    $titleStyle .= ($banner['TITLE_BOLD'] == 'Y') ? "font-weight:bold;" : "font-weight:normal;";
    $titleStyle .= ($banner['TITLE_ITALIC'] == 'Y') ? "font-style:italic;" : "";
    $titleStyle .= ($banner['TITLE_UNDERLINE'] == 'Y') ? "text-decoration:underline;" : "";

    $subtitleStyle = "font-size:" . $sSize . ";";
    if ($banner['SUBTITLE_BOLD'] == 'Y') $subtitleStyle .= "font-weight:bold;";
    if ($banner['SUBTITLE_ITALIC'] == 'Y') $subtitleStyle .= "font-style:italic;";
    if ($banner['SUBTITLE_UNDERLINE'] == 'Y') $subtitleStyle .= "text-decoration:underline;";
?>
    <a href="<?=htmlspecialcharsbx($banner['LINK'])?>" class="<?=$classes?>" style="<?=$imgStyle?>">
        <div class="banner-slot-content text-<?=$textAlign?>" style="color: <?=$textColor?>;">
            <div class="b-text-wrapper" style="<?=$globalBg?>">
                <?php if($banner['TITLE']): ?><div class="b-title banner-clamp" style="<?=$titleStyle?>"><?=$banner['TITLE']?></div><?php endif; ?>
                <?php if($banner['SUBTITLE']): ?><div class="b-sub banner-clamp" style="<?=$subtitleStyle?>"><?=$banner['SUBTITLE']?></div><?php endif; ?>
            </div>
        </div>
    </a>
<?php endforeach; ?>
</div>
