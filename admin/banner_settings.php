<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use MyCompany\Banner\BannerSetTable;
use MyCompany\Banner\BannerTable;

Loader::includeModule("mycompany.banner");
$APPLICATION->SetTitle("Наборы баннеров");

// --- Data Fetching ---
$setsRaw = BannerSetTable::getList(['order' => ['ID' => 'DESC']]);
$setIds = [];
$sets = [];
while($row = $setsRaw->fetch()) {
    $sets[$row['ID']] = $row;
    $setIds[] = $row['ID'];
}

$bannersBySet = [];
if (!empty($setIds)) {
    $bannersRes = BannerTable::getList([
        'filter' => ['@SET_ID' => $setIds],
        'order' => ['SET_ID' => 'ASC', 'SORT' => 'ASC']
    ]);
    while ($banner = $bannersRes->fetch()) {
        $bannersBySet[$banner['SET_ID']][] = $banner;
        // Find a preview image for the set card - first available image
        if (empty($sets[$banner['SET_ID']]['PREVIEW_IMG']) && !empty($banner['IMAGE'])) {
            $sets[$banner['SET_ID']]['PREVIEW_IMG'] = $banner['IMAGE'];
        }
    }
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>

<style>
    .admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .sets-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
    .set-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.09);
        overflow: hidden;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        border: 1px solid #e9ecef;
    }
    .set-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.15); }
    .set-preview-bg { height: 120px; background-size: cover; background-position: center; background-color: #f8f9fa; border-bottom: 1px solid #eee; }
    .set-info { padding: 15px; }
    .set-name { font-weight: 600; font-size: 15px; margin-bottom: 5px; }
    .set-id { color: #888; font-size: 12px; }

    #preview-popup {
        width: 1000px; /* Большой размер */
        max-width: 90vw;
        background: #fff;
        border: 1px solid #aaa;
        box-shadow: 0 20px 100px rgba(0,0,0,0.5); /* Сильная тень */
        z-index: 9999;
        position: absolute;
        display: none;
        border-radius: 8px;
        padding: 20px;
        box-sizing: border-box;
    }
    /* Внутри попапа Grid должен растягиваться */
    #preview-popup .grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
        width: 100% !important;
    }
    #preview-popup .slot { 
        background-color: #eee; 
        background-size: cover; 
        background-position: center; 
        border-radius: 4px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        color: white; 
        text-shadow: 0 1px 2px rgba(0,0,0,0.5); 
        font-size: 28px; 
        font-weight: bold; 
        position: relative;
        overflow: hidden;
    }

    /* Сброс высоты для превью, чтобы влазило */
    #preview-popup .slot[data-i="1"],
    #preview-popup .slot[data-i="2"],
    #preview-popup .slot[data-i="3"],
    #preview-popup .slot[data-i="4"] { 
        height: 250px; /* Чуть меньше оригинала */
        grid-column: span 2;
    }
    #preview-popup .slot[data-i="5"],
    #preview-popup .slot[data-i="6"],
    #preview-popup .slot[data-i="7"],
    #preview-popup .slot[data-i="8"] { 
        height: 160px; 
        grid-column: span 1;
    }

    /* Styles for text inside preview */
    #preview-popup .slot-content { display: flex; flex-direction: column; justify-content: center; width: 100%; height: 100%; padding: 15px; box-sizing: border-box; position:relative; z-index:2; }
    #preview-popup .text-left { align-items: flex-start; text-align: left; }
    #preview-popup .text-center { align-items: center; text-align: center; }
    #preview-popup .text-right { align-items: flex-end; text-align: right; }
    #preview-popup .b-text-wrapper { padding: 8px 12px; border-radius: 4px; }
    #preview-popup .b-title { font-weight: bold; }
</style>

<div class="admin-header">
    <h2>Наборы баннеров</h2>
    <a href="mycompany_banner_set_edit.php?lang=<?=LANG?>" title="Добавить новый набор" class="adm-btn adm-btn-save">Добавить набор</a>
</div>

<div class="sets-grid">
    <?php foreach($sets as $set):
        $previewImg = $set['PREVIEW_IMG'] ?? '';
    ?>
    <div class="set-card" 
         onclick="window.location='mycompany_banner_constructor.php?set_id=<?=$set['ID']?>&lang=<?=LANG?>'"
         onmouseenter="showPreview(<?=$set['ID']?>, this)" 
         onmouseleave="hidePreview()">
        <div class="set-preview-bg" style="background-image: url('<?=htmlspecialcharsbx($previewImg)?>');"></div>
        <div class="set-info">
            <div class="set-name"><?=htmlspecialcharsbx($set['NAME'])?></div>
            <div class="set-id">ID: <?=$set['ID']?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div id="preview-popup"></div>

<script>
    const setsData = <?=json_encode(array_values($bannersBySet))?>;
    const setsDataById = <?=json_encode($bannersBySet)?>;
    const popup = document.getElementById('preview-popup');

    function showPreview(setId, el) {
        const banners = setsDataById[setId] || [];
        popup.innerHTML = '';

        const slotContainer = document.createElement('div');
        slotContainer.className = 'grid';

        for (let i = 0; i < 8; i++) {
            const slotIndex = i + 1;
            const b = banners.find(banner => banner.SLOT_INDEX == slotIndex);
            const slot = document.createElement('div');
            
            slot.dataset.i = slotIndex;
            slot.className = 'slot';

            if (b) {
                slot.style.backgroundColor = b.COLOR || '#fff';
                if (b.IMAGE) {
                    slot.style.backgroundImage = `url(${b.IMAGE})`;
                    slot.style.backgroundSize = `${b.IMG_SCALE || 100}%`;
                    slot.style.backgroundPosition = `${b.IMG_POS_X || 50}% ${b.IMG_POS_Y || 50}%`;
                }
                
                const slotContentDiv = document.createElement('div');
                slotContentDiv.className = `slot-content text-${b.TEXT_ALIGN || 'center'}`;
                slotContentDiv.style.color = b.TEXT_COLOR || '#000';

                const textWrapper = document.createElement('div');
                textWrapper.className = 'b-text-wrapper';

                let innerHTML = '';
                if (b.TITLE) innerHTML += `<div class="b-title" style="font-size: ${b.TITLE_FONT_SIZE || '18px'}">${b.TITLE}</div>`;
                if (b.SUBTITLE) innerHTML += `<div class="b-sub" style="font-size: ${b.SUBTITLE_FONT_SIZE || '14px'}">${b.SUBTITLE}</div>`;
                textWrapper.innerHTML = innerHTML;

                slotContentDiv.appendChild(textWrapper);
                slot.appendChild(slotContentDiv);

            } else {
                slot.innerHTML = `<span>${slotIndex}</span>`;
                slot.style.color = '#aaa';
            }
            slotContainer.appendChild(slot);
        }
        popup.appendChild(slotContainer);

        const rect = el.getBoundingClientRect();
        popup.style.display = 'block';
        const popupWidth = popup.offsetWidth;
        const spaceRight = window.innerWidth - rect.right;
        
        if (spaceRight > popupWidth + 20) {
            popup.style.left = (rect.right + 10) + 'px';
        } else {
            popup.style.left = (rect.left - popupWidth - 10) + 'px';
        }
        popup.style.top = (window.scrollY + rect.top) + 'px';
    }

    function hidePreview() {
        popup.style.display = 'none';
    }
</script>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php"); ?>