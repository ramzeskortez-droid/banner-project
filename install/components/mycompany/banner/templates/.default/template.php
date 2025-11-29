<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die(); ?>
<div class="my-banner-grid">
<?php foreach($arResult['BANNERS'] as $b):
    if(!$b) continue;
    
    // Base classes
    $slotIndex = (int)$b['SLOT_INDEX'];
    $sizeClass = ($slotIndex <= 4) ? 'large' : 'small';
    $imageTypeClass = 'is-' . ($b['IMAGE_TYPE'] ?: 'background');
    $alignClass = 'align-' . ($b['IMAGE_ALIGN'] ?: 'center');
    $classes = "banner-item {$sizeClass} {$imageTypeClass} {$alignClass}";

    // Inline styles
    $styles = "background-color: {$b['COLOR']};";
    if ($b['IMAGE_TYPE'] === 'background' && $b['IMAGE']) {
        $styles .= " background-image: url('{$b['IMAGE']}');";
    }
?>
    <a href="<?=htmlspecialcharsbx($b['LINK'])?>" class="<?=$classes?>" style="<?=$styles?>">
        <div class="b-content">
            <?php if($b['TITLE']): ?><div class="b-title"><?=$b['TITLE']?></div><?php endif; ?>
            <?php if($b['SUBTITLE']): ?><div class="b-sub"><?=$b['SUBTITLE']?></div><?php endif; ?>
        </div>
        <?php if($b['IMAGE_TYPE'] === 'icon' && $b['IMAGE']): ?>
            <div class="b-icon-wrapper">
                <img src="<?=$b['IMAGE']?>" class="b-img-icon">
            </div>
        <?php endif; ?>
    </a>
<?php endforeach; ?>
</div>
