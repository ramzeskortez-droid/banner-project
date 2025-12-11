<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arResult */
?>
<div class="my-banner-grid-public">
    <?php foreach($arResult['BANNERS'] as $i => $banner): 
        if(!$banner) continue;

        // Grid classes based on slot index, as per new spec
        $colClass = ($banner['SLOT_INDEX'] <= 4) ? 'span-2' : 'span-1';
        $heightClass = ($banner['SLOT_INDEX'] <= 2) ? 'h-large' : 'h-small'; // A more semantic naming
        
        $hoverClass = ($banner['HOVER_ANIMATION'] == 'Y') ? 'hover-anim' : '';
        
        // --- Inline Styles ---
        // Background
        $bgStyle = "background-image: url('".htmlspecialcharsbx($banner['IMAGE'])."'); ";
        $bgStyle .= "background-position: " . ($banner['IMG_POS_X'] ?? '50') . "% " . ($banner['IMG_POS_Y'] ?? '50') . "%; ";
        $bgStyle .= "background-size: " . ($banner['IMG_SCALE'] ?? '100') . "%; ";
        $bgStyle .= "background-repeat: no-repeat; ";
        if (!$banner['IMAGE']) {
            $bgStyle .= "background-color: ".htmlspecialcharsbx($banner['COLOR'] ?: '#f0f0f0').";";
        }

        // Text wrapper (for background color)
        $textWrapperStyle = '';
        if ($banner['TEXT_BG_SHOW'] === 'Y') {
            $hex = $banner['TEXT_BG_COLOR'] ?: '#ffffff';
            $op = isset($banner['TEXT_BG_OPACITY']) ? (int)$banner['TEXT_BG_OPACITY'] : 70;
            $hex = ltrim($hex, '#');
            if(strlen($hex)==3) { $r=hexdec($hex[0].$hex[0]); $g=hexdec($hex[1].$hex[1]); $b=hexdec($hex[2].$hex[2]); }
            else { $r=hexdec(substr($hex,0,2)); $g=hexdec(substr($hex,2,2)); $b=hexdec(substr($hex,4,2)); }
            $textWrapperStyle = "background-color: rgba($r, $g, $b, ".($op/100).");";
        }

        // Base text style
        $baseTextStyle = "color: ".($banner['TEXT_COLOR'] ?: '#ffffff').";";
        if ($banner['TEXT_STROKE_WIDTH'] > 0) {
            $baseTextStyle .= "-webkit-text-stroke: ".$banner['TEXT_STROKE_WIDTH']."px ".($banner['TEXT_STROKE_COLOR'] ?: '#000000').";";
        }

        // Title style
        $titleStyle = $baseTextStyle;
        $titleStyle .= "font-size:" . ($banner['TITLE_FONT_SIZE'] ?: '22px') . ";";
        $titleStyle .= "font-weight:" . ($banner['TITLE_BOLD'] == 'Y' ? "bold;" : "normal;");
        $titleStyle .= "font-style:" . ($banner['TITLE_ITALIC'] == 'Y' ? "italic;" : "normal;");
        $titleStyle .= "text-decoration:" . ($banner['TITLE_UNDERLINE'] == 'Y' ? "underline;" : "none;");

        // Subtitle style
        $subtitleStyle = $baseTextStyle;
        $subtitleStyle .= "font-size:" . ($banner['SUBTITLE_FONT_SIZE'] ?: '14px') . ";";
        $subtitleStyle .= "font-weight:" . ($banner['SUBTITLE_BOLD'] == 'Y' ? "bold;" : "normal;");
        $subtitleStyle .= "font-style:" . ($banner['SUBTITLE_ITALIC'] == 'Y' ? "italic;" : "normal;");
        $subtitleStyle .= "text-decoration:" . ($banner['SUBTITLE_UNDERLINE'] == 'Y' ? "underline;" : "none;");
    ?>
    <a href="<?=htmlspecialcharsbx($banner['LINK'])?>" class="banner-item <?=$colClass?> <?=$heightClass?> <?=$hoverClass?>" style="<?=$bgStyle?>">
        <div class="content-wrapper" style="<?=$textWrapperStyle?>">
            <?php if(!empty($banner['TITLE'])): ?>
                <div class="title" style="<?=$titleStyle?>"><?=$banner['TITLE']?></div>
            <?php endif; ?>
            <?php if(!empty($banner['SUBTITLE'])): ?>
                <div class="subtitle" style="<?=$subtitleStyle?>"><?=$banner['SUBTITLE']?></div>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>

