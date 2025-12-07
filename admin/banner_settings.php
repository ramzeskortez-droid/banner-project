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
        width: 480px;
        background: #fff;
        border: 1px solid #999;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        z-index: 9999;
        position: absolute;
        display: none;
        border-radius: 8px;
        padding: 15px;
        pointer-events: none;
    }
    /* Контейнер, обрезающий высоту отмасштабированного грида */
    #preview-crop {
        width: 100%;
        height: 320px; /* Подгоняем под высоту контента */
        overflow: hidden;
        position: relative;
    }
    #preview-grid {
        width: 1420px;
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        transform: scale(0.32);
        transform-origin: top left;
    }

    #preview-grid .slot { 
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

    #preview-grid .slot[data-i="1"],
    #preview-grid .slot[data-i="2"],
    #preview-grid .slot[data-i="3"],
    #preview-grid .slot[data-i="4"] { 
        height: 300px;
        grid-column: span 2;
    }
    #preview-grid .slot[data-i="5"],
    #preview-grid .slot[data-i="6"],
    #preview-grid .slot[data-i="7"],
    #preview-grid .slot[data-i="8"] { 
        height: 200px; 
        grid-column: span 1;
    }

    #preview-grid .slot-content { display: flex; flex-direction: column; justify-content: center; width: 100%; height: 100%; padding: 25px; box-sizing: border-box; position:relative; z-index:2; }
    #preview-grid .text-left { align-items: flex-start; text-align: left; }
    #preview-grid .text-center { align-items: center; text-align: center; }
    #preview-grid .text-right { align-items: flex-end; text-align: right; }
    #preview-grid .b-text-wrapper { padding: 10px 15px; border-radius: 4px; }
    #preview-grid .b-title { font-weight: bold; }
</style>

<div class="admin-header">
    <h2>Список созданных баннеров</h2>
    <div>
        <button class="adm-btn" onclick="showLogs()">📋 Логи отладки</button>
        <button class="adm-btn adm-btn-save" onclick="createSet()">Создать баннер из шаблона</button>
    </div>
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

<div id="preview-popup"><div id="preview-crop"></div></div>

<div id="create-popup" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; padding:25px; border-radius:6px; width:350px; box-shadow:0 5px 20px rgba(0,0,0,0.2);">
        <h3 style="margin-top:0; margin-bottom:15px;">Новый баннер</h3>
        <div style="margin-bottom:15px;">
            <label style="display:block; margin-bottom:5px; font-weight:bold;">Название:</label>
            <input type="text" id="newSetName" class="adm-input" style="width:100%; box-sizing:border-box;" placeholder="Например: Весна 2025">
        </div>
        <div style="margin-bottom:20px;">
            <label><input type="checkbox" id="newSetAuto" checked> Заполнить категориями (Авто)</label>
        </div>
        <div style="text-align:right; display:flex; gap:10px; justify-content:flex-end;">
            <button class="adm-btn" onclick="document.getElementById('create-popup').style.display='none'">Отмена</button>
            <button class="adm-btn adm-btn-save" onclick="doCreate()">Создать</button>
        </div>
    </div>
</div>

<script>
    function showLogs() {
        const logContent = document.createElement('div');
        logContent.style.cssText = "position:fixed; top:10%; left:50%; transform:translateX(-50%); width:600px; height:500px; background:#fff; border:1px solid #ccc; z-index:10000; box-shadow:0 0 20px rgba(0,0,0,0.5); display:flex; flex-direction:column;";
        logContent.innerHTML = `
            <div style="padding:10px; background:#eee; border-bottom:1px solid #ccc; display:flex; justify-content:space-between;"><strong>Debug Log</strong><button onclick="this.closest('div').parentElement.remove()">✕</button></div>
            <pre id="logArea" style="flex:1; overflow:auto; padding:10px; font-family:monospace; font-size:12px;"></pre>
            <div style="padding:10px; border-top:1px solid #ccc; text-align:right;"><button class="adm-btn" onclick="clearLogs()">Очистить</button></div>
        `;
        document.body.appendChild(logContent);

        fetch('mycompany_banner_ajax_save_banner.php?action=get_log&sessid=<?=bitrix_sessid()?>')
            .then(r => r.text())
            .then(txt => document.getElementById('logArea').innerText = txt || 'Лог пуст');
    }

    function clearLogs() {
        fetch('mycompany_banner_ajax_save_banner.php', {
            method: 'POST',
            body: new URLSearchParams({action:'clear_log', sessid:'<?=bitrix_sessid()?>'})
        }).then(() => {
            const area = document.getElementById('logArea');
            if(area) area.innerText = 'Очищено';
        });
    }

    const setsData = <?=json_encode(array_values($bannersBySet))?>;
    const setsDataById = <?=json_encode($bannersBySet)?>;
    const popup = document.getElementById('preview-popup');
    const popupCrop = document.getElementById('preview-crop');

    function createSet() {
        document.getElementById('create-popup').style.display = 'flex';
        document.getElementById('newSetName').focus();
    }
    
    function doCreate() {
        const btn = document.querySelector('#create-popup .adm-btn-save');
        const nameInp = document.getElementById('newSetName');
        
        if(!nameInp.value) { nameInp.style.border='1px solid red'; return; }
        
        // Блокируем интерфейс
        btn.disabled = true;
        btn.innerText = 'Создание...';

        const fd = new FormData();
        fd.append('action', 'create_set');
        fd.append('name', nameInp.value);
        fd.append('category_mode', document.getElementById('newSetAuto').checked ? 'Y' : 'N');
        fd.append('sessid', '<?=bitrix_sessid()?>');

        fetch('mycompany_banner_ajax_save_banner.php', {method:'POST', body:fd})
            .then(r => r.json())
            .then(res => {
                if(res.success) window.location = 'mycompany_banner_constructor.php?set_id=' + res.id + '&lang=<?=LANG?>';
                else { 
                    alert(res.errors.join('\n')); 
                    btn.disabled = false; 
                    btn.innerText = 'Создать'; 
                }
            });
    }

    function showPreview(setId, el) {
        const banners = setsDataById[setId] || [];
        popupCrop.innerHTML = '';

        const grid = document.createElement('div');
        grid.id = 'preview-grid';

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
            grid.appendChild(slot);
        }
        popupCrop.appendChild(grid);

        const rect = el.getBoundingClientRect();
        popup.style.display = 'block';
        
        // ЖЕСТКО СПРАВА
        popup.style.left = (rect.right + 15) + 'px';
        // Чуть выше середины курсора
        let topPos = window.scrollY + rect.top - 20;
        // Проверка, чтобы не улетел вниз экрана
        if (topPos + popup.offsetHeight > window.scrollY + window.innerHeight) {
            topPos = window.scrollY + window.innerHeight - popup.offsetHeight - 20;
        }
        popup.style.top = topPos + 'px'; 
    }

    function hidePreview() {
        popup.style.display = 'none';
    }
</script>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php"); ?>