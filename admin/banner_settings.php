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
        position: absolute; display: none; background: #fff; border: 1px solid #ccc;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2); padding: 10px; z-index: 1000;
    }
    #preview-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; width: 600px; transform: scale(0.4); transform-origin: top left; }
    .preview-slot { background-color: #eee; background-size: cover; background-position: center; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: white; text-shadow: 0 1px 2px rgba(0,0,0,0.5); font-size: 28px; font-weight: bold; }
    .preview-slot.large { grid-column: span 2; height: 180px; }
    .preview-slot.small { grid-column: span 1; height: 120px; }
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

<div id="preview-popup"><div id="preview-grid"></div></div>

<script>
    const setsData = <?=json_encode($bannersBySet)?>;
    const popup = document.getElementById('preview-popup');

    function showPreview(setId, el) {
        const banners = setsData[setId] || [];
        const grid = document.getElementById('preview-grid');
        grid.innerHTML = '';

        for (let i = 0; i < 8; i++) {
            const b = banners[i];
            const slot = document.createElement('div');
            const sizeClass = i < 4 ? 'large' : 'small';
            slot.className = `preview-slot ${sizeClass}`;

            if (b) {
                slot.style.backgroundColor = b.COLOR || '#fff';
                if (b.IMAGE) slot.style.backgroundImage = `url(${b.IMAGE})`;
                slot.innerHTML = `<div>${b.TITLE || ''}</div>`;
            } else {
                slot.innerHTML = `<span>${i+1}</span>`;
                slot.style.color = '#aaa';
            }
            grid.appendChild(slot);
        }

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