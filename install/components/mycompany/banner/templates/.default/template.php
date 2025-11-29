<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die(); ?>
<div class="my-banner-grid">
<?php for($i=1; $i<=8; $i++): 
    $b = $arResult['BANNERS'][$i] ?? null;
    if(!$b) continue;
    $class = ($i<=4) ? 'large' : 'small'; 
?>
    <a href="<?=$b['LINK']?>" class="banner-item <?=$class?>" style="background-color:<?=$b['COLOR']?>">
        <div class="b-content">
            <div class="b-title"><?=$b['TITLE']?></div>
            <div class="b-sub"><?=$b['SUBTITLE']?></div>
        </div>
        <?php if($b['IMAGE']): ?><img src="<?=$b['IMAGE']?>" class="b-img"><?php endif; ?>
    </a>
<?php endfor; ?>
</div>