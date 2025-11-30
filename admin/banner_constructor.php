<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
use Bitrix\Main\Loader;
use MyCompany\Banner\BannerTable;
use MyCompany\Banner\BannerSetTable;

Loader::includeModule("mycompany.banner");
Loader::includeModule("iblock");

$setId = (int)($_REQUEST['set_id'] ?? 1);
$set = BannerSetTable::getById($setId)->fetch();

$sections = [];
if(Loader::includeModule("iblock")) {
    $res = CIBlockSection::GetList(['LEFT_MARGIN'=>'ASC'], ['ACTIVE'=>'Y','IBLOCK_ACTIVE'=>'Y','GLOBAL_ACTIVE'=>'Y'], false, ['ID','NAME','DEPTH_LEVEL','SECTION_PAGE_URL','DESCRIPTION', 'PICTURE']);
    while($sec = $res->GetNext()) {
        $sections[$sec['ID']] = [
            'id' => $sec['ID'], 'title' => $sec['NAME'], 'link' => $sec['SECTION_PAGE_URL'],
            'subtitle' => $sec['DESCRIPTION'] ? strip_tags($sec['DESCRIPTION']) : '',
            'image' => $sec['PICTURE'] ? \CFile::GetPath($sec['PICTURE']) : ''
        ];
    }
}

$APPLICATION->SetTitle("Конструктор баннера: " . ($set['NAME'] ?? 'Новый'));
$bannersRaw = BannerTable::getList(['filter' => ['=SET_ID' => $setId]])->fetchAll();
$banners = [];
foreach($bannersRaw as $b) $banners[$b['SLOT_INDEX']] = $b;

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>

<style>
    .construct-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .construct-wrap { max-width: 1400px; margin: 0 auto; }
    .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
    .slot { background: #fff; border: 2px dashed #ccc; border-radius: 8px; position: relative; overflow: hidden; cursor: pointer; transition: all 0.2s; display: flex; flex-direction: column; justify-content: center; background-size: cover; background-position: center; }
    .slot:hover { border-color: #999; transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); z-index: 5; }
    .slot[data-i="0"], .slot[data-i="1"], .slot[data-i="2"], .slot[data-i="3"] { grid-column: span 2; height: 300px; }
    .slot[data-i="4"], .slot[data-i="5"], .slot[data-i="6"], .slot[data-i="7"] { grid-column: span 1; height: 200px; }
    .slot-content { height: 100%; display: flex; flex-direction: column; justify-content: center; padding: 20px; box-sizing: border-box; }
    .b-text-wrapper { display: inline-block; padding: 10px 15px; border-radius: 6px; transition: background-color 0.3s; }
    .slot-placeholder { text-align: center; color: #bbb; width: 100%; }

    .text-left { align-items: flex-start; text-align: left; }
    .text-center { align-items: center; text-align: center; }
    .text-right { align-items: flex-end; text-align: right; }

    .overlay { position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.6); z-index: 9990; display: none; align-items: center; justify-content: center; }
    #adjusterOverlay { z-index: 9999; }
    .popup { background: #fdfdfd; width: 800px; max-height: 95vh; display: flex; flex-direction: column; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
    .popup-header { padding: 15px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .popup-body { padding: 0; overflow-y: auto; flex: 1; }
    .popup-footer { padding: 15px 25px; background: #f7f7f7; text-align: right; border-top: 1px solid #eee; border-radius: 0 0 8px 8px; }

    .settings-group { padding: 0; border: 1px solid #bbdefb; overflow: hidden; margin-bottom: 20px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .group-title { background: #e3f2fd; color: #1565c0; padding: 12px 20px; font-weight: bold; border-bottom: 1px solid #90caf9; margin: 0; text-transform: uppercase; font-size: 12px; }
    .group-content { padding: 20px; }
    .global-settings .form-row { padding: 15px 20px; display: flex; align-items: center; gap: 15px; }
    .global-settings .form-row .sep { width: 1px; height: 20px; background: #bbdefb; }
    .form-row { margin-bottom: 15px; }
    .form-row:last-child { margin-bottom: 0; }
    .form-row label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 5px; }
    .form-control { width: 100%; height: 40px; line-height: 38px; padding: 0 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; background: #fff; }
    
    .adjust-preview { height: 350px; width: 100%; background-size: 100%; background-position: 50% 50%; border-bottom: 1px solid #ddd; position: relative; background-color: #eee; cursor: grab; overflow: hidden; display: flex; flex-direction: column; justify-content: center; }
    .adjust-text-overlay { pointer-events: none; }
    .adjust-controls { padding: 20px; }
</style>

<div class="construct-wrap">
    <div class="construct-header"><h2>Сетка баннеров</h2><a href="mycompany_banner_settings.php?lang=<?=LANGUAGE_ID?>" class="adm-btn">← Вернуться к списку</a></div>
    <div class="settings-group global-settings">
        <div class="group-title">Настройки всего набора</div>
        <div class="form-row">
            <label><input type="checkbox" id="globalBgShow"> Включить фон для текста</label>
            <div class="sep"></div><label>Цвет:</label> <input type="color" id="globalBgColor">
            <div class="sep"></div><label>Прозрачность (%):</label> <input type="range" id="globalBgOp" min="0" max="100">
        </div>
    </div>
    <div class="grid" id="grid"></div>
</div>

<!-- MAIN POPUP -->
<div class="overlay" id="popup"><div class="popup" style="width: 800px;">
    <div class="popup-header"><h3 style="margin:0" id="popupTitle">Настройка блока</h3><span style="cursor:pointer; font-size:20px; color:#999;" onclick="closePopup()">✕</span></div>
    <form id="editForm" class="popup-body">
        <input type="hidden" name="slot_index" id="slotIndex"><input type="hidden" name="set_id" value="<?=$setId?>">
        <input type="hidden" name="action" value="save_slot"><input type="hidden" name="sessid" value="<?=bitrix_sessid()?>">
        <input type="hidden" name="img_scale" id="inpScale"><input type="hidden" name="img_pos_x" id="inpPosX"><input type="hidden" name="img_pos_y" id="inpPosY">

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding: 20px;">
            <div class="left-col">
                <div class="settings-group"><div class="group-title">Основные данные</div>
                    <div class="group-content">
                        <div class="form-row"><label>Заполнить из категории</label><select id="catSelect" name="category_id" class="form-control"><option value="0">-- Не выбрано --</option><?php foreach($sections as $id => $s): ?><option value="<?=$id?>"><?=$s['title']?></option><?php endforeach; ?></select></div>
                        <div class="form-row"><label>Заголовок</label><input type="text" name="title" id="inpTitle" class="form-control"></div>
                        <div class="form-row"><label>Анонс</label><textarea name="subtitle" id="inpSubtitle" class="form-control" rows="2"></textarea></div>
                        <div class="form-row"><label>Ссылка</label><input type="text" name="link" id="inpLink" class="form-control">
                        <div class="form-row"><label>Сортировка</label><input type="number" name="sort" id="inpSort" class="form-control"></div>
                    </div>
                </div>
            </div>
            <div class="right-col">
                <div class="settings-group"><div class="group-title">Изображение и Фон</div>
                     <div class="group-content">
                        <div class="form-row"><label>Файл картинки</label><input type="file" name="image_file" id="inpFile" class="form-control" style="padding-top:8px;"><input type="text" name="image_url" id="inpImgUrl" class="form-control" placeholder="Или ссылка URL" style="margin-top:5px;"></div>
                        <div class="form-row"><button type="button" class="adm-btn" onclick="openAdjuster()">✂ Настроить отображение</button></div>
                        <div class="form-row"><label>Цвет фона (если нет картинки)</label><input type="color" name="color" id="inpColor" style="width:100%; height:38px;"></div>
                    </div>
                </div>
                 <div class="settings-group"><div class="group-title">Типографика</div>
                    <div class="group-content">
                         <div class="form-row"><label>Выравнивание текста</label><select name="text_align" id="inpTextAlign" class="form-control"><option value="left">Слева</option><option value="center" selected>По центру</option><option value="right">Справа</option></select></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="popup-footer"><button type="submit" class="adm-btn adm-btn-save">Сохранить</button></div>
    </form>
</div></div>

<!-- ADJUSTER POPUP -->
<div id="adjusterOverlay" class="overlay"><div class="popup" style="width:800px;">
    <div class="popup-header"><h3>Настройка отображения <small>(Тяните мышкой для сдвига)</small></h3></div>
    <div class="popup-body">
        <div class="adjust-preview" id="adjPreview"><div id="adjustTextOverlay"></div></div>
        <div class="adjust-controls group-content">
            <div class="form-row"><label>Масштаб: <span id="scaleVal">100</span>%</label><input type="range" id="adjScale" min="10" max="250" value="100" class="form-control">
            <input type="hidden" id="adjPosX"><input type="hidden" id="adjPosY">
        </div>
    </div>
    <div class="popup-footer"><button type="button" class="adm-btn adm-btn-save" onclick="applyAdjustments()">Применить</button><button type="button" class="adm-btn" onclick="closeAdjuster()">Отмена</button></div>
</div></div>

<script>
let banners = <?=CUtil::PhpToJSObject($banners)?>, globalSettings = <?=CUtil::PhpToJSObject($set)?>, sections = <?=CUtil::PhpToJSObject($sections)?>;
const grid = document.getElementById('grid');

function hexToRgb(hex, opacity) { let r=0,g=0,b=0; if(!hex) hex='#ffffff'; if (hex.length==4){r=parseInt(hex[1]+hex[1],16);g=parseInt(hex[2]+hex[2],16);b=parseInt(hex[3]+hex[3],16);}else if(hex.length==7){r=parseInt(hex.substring(1,3),16);g=parseInt(hex.substring(3,5),16);b=parseInt(hex.substring(5,7),16);} return `rgba(${r},${g},${b},${opacity/100})`; }

function render() {
    grid.innerHTML = '';
    const list = Object.values(banners).sort((a,b) => (parseInt(a.SORT)||500) - (parseInt(b.SORT)||500));
    for(let i=0; i < 8; i++) {
        const b = list[i], el = document.createElement('div');
        el.className = 'slot'; el.dataset.i = i;
        if (b) {
            el.style.backgroundColor = b.COLOR || '#fff';
            if(b.IMAGE) { el.style.backgroundImage = `url(${b.IMAGE})`; el.style.backgroundSize = `${b.IMG_SCALE || 100}%`; el.style.backgroundPosition = `${b.IMG_POS_X || 50}% ${b.IMG_POS_Y || 50}%`; }
            const content = document.createElement('div'), wrapper = document.createElement('div');
            content.className = `slot-content text-${b.TEXT_ALIGN || 'center'}`;
            wrapper.className = 'b-text-wrapper';
            if (globalSettings.TEXT_BG_SHOW === 'Y') { wrapper.style.backgroundColor = hexToRgb(globalSettings.TEXT_BG_COLOR, globalSettings.TEXT_BG_OPACITY); }
            if(b.TITLE) wrapper.innerHTML = `<div style="color:${b.TEXT_COLOR||'#000'}; font-size: ${b.TITLE_FONT_SIZE || '18px'}">${b.TITLE}</div>`;
            content.appendChild(wrapper);
            el.appendChild(content);
            el.onclick = () => openPopup(b.SLOT_INDEX);
        } else {
            el.innerHTML = '<div class="slot-placeholder">Слот '+(i+1)+'<br><small>Настроить</small></div>';
            el.onclick = () => openPopupNew(i);
        }
        grid.appendChild(el);
    }
}
function findFreeSlotIndex() { for(let i=1; i<=100; i++) { if (!banners[i]) return i; } return 101; }
function openPopupNew(visualIndex) { const f = document.getElementById('editForm'); f.reset(); document.getElementById('slotIndex').value = findFreeSlotIndex(); const sort = (visualIndex + 1) * 10; f.sort.value = sort; f.text_align.value = 'center'; document.getElementById('popupTitle').innerText = `Новый блок (Сортировка: ${sort})`; document.getElementById('popup').style.display = 'flex'; }
function openPopup(slotIndex) { const f = document.getElementById('editForm'); f.reset(); document.getElementById('slotIndex').value = slotIndex; const b = banners[slotIndex] || {}; document.getElementById('popupTitle').innerText = `Настройка блока #${slotIndex}`; if(b.CATEGORY_ID) f.category_id.value = b.CATEGORY_ID; f.title.value = b.TITLE || ''; f.subtitle.value = b.SUBTITLE || ''; f.link.value = b.LINK || ''; f.sort.value = b.SORT || 500; f.text_align.value = b.TEXT_ALIGN || 'center'; f.color.value = b.COLOR || '#ffffff'; if(b.IMAGE) document.getElementById('inpImgUrl').value = b.IMAGE; f.img_scale.value = b.IMG_SCALE || 100; f.img_pos_x.value = b.IMG_POS_X || 50; f.img_pos_y.value = b.IMG_POS_Y || 50; document.getElementById('popup').style.display = 'flex'; }
function closePopup() { document.getElementById('popup').style.display = 'none'; }

const adjuster = { preview: document.getElementById('adjPreview'), scale: document.getElementById('adjScale'), posX: document.getElementById('adjPosX'), posY: document.getElementById('adjPosY'), isDragging: false, startX: 0, startY: 0, initPosX: 50, initPosY: 50 };
function openAdjuster() {
    let imgUrl = document.getElementById('inpImgUrl').value; const file = document.getElementById('inpFile').files[0];
    if(file) imgUrl = URL.createObjectURL(file);
    if(!imgUrl) { alert('Сначала выберите изображение'); return; }
    adjuster.preview.style.backgroundImage = `url(${imgUrl})`;
    const textPreview = document.createElement('div'); textPreview.className = 'slot-content text-center'; textPreview.innerHTML = `<div class="b-text-wrapper" style="background-color: ${hexToRgb(globalSettings.TEXT_BG_COLOR, globalSettings.TEXT_BG_OPACITY)}"><div style="color:#fff; font-weight:bold;">${document.getElementById('inpTitle').value}</div></div>`;
    document.getElementById('adjustTextOverlay').innerHTML = ''; document.getElementById('adjustTextOverlay').appendChild(textPreview);
    adjuster.scale.value = document.getElementById('inpScale').value || 100; adjuster.posX.value = document.getElementById('inpPosX').value || 50; adjuster.posY.value = document.getElementById('inpPosY').value || 50;
    updateAdjusterPreview(); document.getElementById('adjusterOverlay').style.display = 'flex';
}
function closeAdjuster() { document.getElementById('adjusterOverlay').style.display = 'none'; }
function updateAdjusterPreview() { adjuster.preview.style.backgroundSize = `${adjuster.scale.value}%`; adjuster.preview.style.backgroundPosition = `${adjuster.posX.value}% ${adjuster.posY.value}%`; document.getElementById('scaleVal').innerText = adjuster.scale.value; }
function applyAdjustments() { document.getElementById('inpScale').value = adjuster.scale.value; document.getElementById('inpPosX').value = adjuster.posX.value; document.getElementById('inpPosY').value = adjuster.posY.value; closeAdjuster(); }
adjuster.scale.addEventListener('input', updateAdjusterPreview);
adjuster.preview.onmousedown = function(e) { e.preventDefault(); adjuster.isDragging = true; adjuster.startX = e.clientX; adjuster.startY = e.clientY; adjuster.initPosX = parseFloat(adjuster.posX.value); adjuster.initPosY = parseFloat(adjuster.posY.value); adjuster.preview.style.cursor = 'grabbing'; };
window.onmousemove = function(e) { if(!adjuster.isDragging) return; let newX = adjuster.initPosX - ((e.clientX - adjuster.startX) * 0.2); let newY = adjuster.initPosY - ((e.clientY - adjuster.startY) * 0.2); adjuster.posX.value = Math.max(0, Math.min(100, newX)); adjuster.posY.value = Math.max(0, Math.min(100, newY)); updateAdjusterPreview(); };
window.onmouseup = function() { adjuster.isDragging = false; adjuster.preview.style.cursor = 'grab'; };

function saveGlobalSettings() { const data = new FormData(); data.append('action', 'save_set_settings'); data.append('set_id', globalSettings.ID); data.append('sessid', '<?=bitrix_sessid()?>'); data.append('show', document.getElementById('globalBgShow').checked ? 'Y' : 'N'); data.append('color', document.getElementById('globalBgColor').value); data.append('opacity', document.getElementById('globalBgOp').value); fetch('mycompany_banner_ajax_save_banner.php', {method:'POST', body:data}).then(res => res.json()).then(d => { if(d.success) { globalSettings = d.data; render(); } else { alert('Ошибка сохранения настроек'); } }); }
function initGlobalSettings() { document.getElementById('globalBgShow').checked = globalSettings.TEXT_BG_SHOW === 'Y'; document.getElementById('globalBgColor').value = globalSettings.TEXT_BG_COLOR; document.getElementById('globalBgOp').value = globalSettings.TEXT_BG_OPACITY; ['globalBgShow', 'globalBgColor', 'globalBgOp'].forEach(id => document.getElementById(id).addEventListener('change', saveGlobalSettings)); }

document.getElementById('catSelect').addEventListener('change', function() { const sec = sections[this.value]; if(sec) { document.getElementById('inpTitle').value = sec.title; document.getElementById('inpSubtitle').value = sec.subtitle; document.getElementById('inpLink').value = sec.link; if(sec.image) document.getElementById('inpImgUrl').value = sec.image; } });
document.getElementById('editForm').onsubmit = async function(e) { e.preventDefault(); let fd = new FormData(this); let res = await fetch('mycompany_banner_ajax_save_banner.php', {method:'POST', body:fd}); let data = await res.json(); if(data.success) { banners[data.data.SLOT_INDEX] = data.data; render(); closePopup(); } else { alert(data.errors.join('\n')); } };

initGlobalSettings();
render();
</script>
<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php"); ?>
