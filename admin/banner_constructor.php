<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use MyCompany\Banner\BannerTable;
use MyCompany\Banner\BannerSetTable;

Loader::includeModule("mycompany.banner");
Loader::includeModule("iblock");

$setId = (int)$_REQUEST['set_id'];
$set = BannerSetTable::getById($setId)->fetch();

// Получаем разделы для демо-данных и режима категорий
$sections = [];
if(Loader::includeModule("iblock")) {
    $res = CIBlockSection::GetList(['LEFT_MARGIN'=>'ASC'], ['ACTIVE'=>'Y','IBLOCK_ACTIVE'=>'Y','GLOBAL_ACTIVE'=>'Y'], false, ['ID','NAME','DEPTH_LEVEL','SECTION_PAGE_URL','DESCRIPTION', 'PICTURE']);
    while($sec = $res->GetNext()) {
        $sections[$sec['ID']] = [
            'id' => $sec['ID'],
            'title' => $sec['NAME'],
            'link' => $sec['SECTION_PAGE_URL'],
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
    /* Main Layout */
    .construct-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .construct-wrap { max-width: 1400px; margin: 0 auto; }
    
    .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
    
    .slot { 
        background: #fff; border: 2px dashed #ccc; border-radius: 8px; 
        position: relative; overflow: hidden; cursor: pointer; transition: all 0.2s;
        display: flex; flex-direction: column; justify-content: center;
    }
    .slot:hover { border-color: #999; transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); z-index: 5; }
    
    .slot[data-i="0"], .slot[data-i="1"], .slot[data-i="2"], .slot[data-i="3"] { grid-column: span 2; height: 300px; }
    .slot[data-i="4"], .slot[data-i="5"], .slot[data-i="6"], .slot[data-i="7"] { grid-column: span 1; height: 200px; }
    
    .slot-content { padding: 20px; width: 100%; box-sizing: border-box; z-index: 2; }
    .slot-placeholder { text-align: center; color: #bbb; width: 100%; }
    
    /* POPUP UI */
    .overlay { position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); z-index: 9999; display: none; align-items: center; justify-content: center; }
    .popup { background: #fdfdfd; width: 700px; max-height: 90vh; display: flex; flex-direction: column; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
    .popup-header { padding: 15px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #fff; border-radius: 8px 8px 0 0; }
    .popup-body { padding: 20px; overflow-y: auto; flex: 1; }
    
    /* Settings Groups */
    .settings-group { padding: 0; border: 1px solid #bbdefb; overflow: hidden; margin-bottom: 20px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .group-title { background: #e3f2fd; color: #1565c0; padding: 12px 20px; font-weight: bold; border-bottom: 1px solid #90caf9; margin: 0; text-transform: uppercase; font-size: 12px; }
    .group-content { padding: 20px; }
    
    .form-row { margin-bottom: 15px; }
    .form-row:last-child { margin-bottom: 0; }
    .form-row label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 5px; }
    .form-control { width: 100%; height: 40px; line-height: 38px; padding: 0 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; background: #fff; }
    textarea.form-control { height: auto; padding: 10px; line-height: 1.4; }
    
    .color-presets { display: flex; gap: 8px; margin-bottom: 8px; }
    .color-preset { width: 26px; height: 26px; border-radius: 4px; cursor: pointer; border: 1px solid rgba(0,0,0,0.1); transition: transform 0.2s; }
    .color-preset:hover { transform: scale(1.3); z-index: 10; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
</style>

<div class="construct-wrap">
    <div class="construct-header">
        <h2>Сетка баннеров</h2>
        <a href="mycompany_banner_settings.php?lang=<?=LANGUAGE_ID?>" class="adm-btn">← Вернуться к списку</a>
    </div>
    
    <div class="grid" id="grid"></div>
</div>

<!-- POPUP FORM -->
<div class="overlay" id="popup">
    <div class="popup">
        <div class="popup-header">
            <h3 style="margin:0" id="popupTitle">Настройка блока</h3>
            <span style="cursor:pointer; font-size:20px; color:#999;" onclick="closePopup()">✕</span>
        </div>
        <form id="editForm" class="popup-body">
            <input type="hidden" name="slot_index" id="slotIndex">
            <input type="hidden" name="set_id" value="<?=$setId?>">
            <input type="hidden" name="action" value="save_slot">
            <input type="hidden" name="sessid" value="<?=bitrix_sessid()?>">
            
            <div class="settings-group">
                <div class="group-title">Основные данные</div>
                <div class="group-content">
                    <div class="form-row">
                        <label>Заполнить из категории</label>
                        <select id="catSelect" name="category_id" class="form-control">
                            <option value="0">-- Не выбрано --</option>
                            <?php foreach($sections as $id => $s): ?>
                                <option value="<?=$id?>"><?=$s['title']?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>Заголовок</label>
                        <input type="text" name="title" id="inpTitle" class="form-control">
                        <input type="text" name="title_font_size" id="inpTitleSize" class="form-control" placeholder="Размер (напр. 24px)" style="margin-top:5px; height:30px; font-size:12px;">
                    </div>
                    <div class="form-row">
                        <label>Анонс</label>
                        <textarea name="subtitle" id="inpSubtitle" class="form-control" rows="3"></textarea>
                        <input type="text" name="subtitle_font_size" id="inpSubSize" class="form-control" placeholder="Размер (напр. 16px)" style="margin-top:5px; height:30px; font-size:12px;">
                    </div>
                    <div class="form-row"><label>Ссылка</label><input type="text" name="link" id="inpLink" class="form-control"></div>
                     <div class="form-row"><label>Сортировка</label><input type="number" name="sort" id="inpSort" class="form-control"></div>
                </div>
            </div>
            
            <div class="settings-group">
                <div class="group-title">Изображение и Фон</div>
                 <div class="group-content">
                    <div class="form-row">
                        <label>Тип фона</label>
                        <select name="image_type" class="form-control">
                            <option value="background">Картинка на весь фон</option>
                            <option value="icon">Иконка + Цвет фона</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>Файл картинки</label>
                        <input type="file" name="image_file" class="form-control" style="padding-top:8px;">
                        <input type="text" name="image_url" id="inpImgUrl" class="form-control" placeholder="Или ссылка URL" style="margin-top:5px;">
                    </div>
                    <div class="form-row">
                        <label>Цвет фона</label>
                        <div class="color-presets">
                            <?php foreach(['#ffffff','#000000','#f5f5f5','#ffdddd','#ddffdd','#ddddff','#ffeb3b'] as $c): ?>
                                <div class="color-preset" style="background:<?=$c?>" onclick="setColor('<?=$c?>')"></div>
                            <?php endforeach; ?>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <input type="color" name="color" id="inpColor" style="height:38px; width:50px; padding:0; border:none;">
                            <input type="text" id="inpColorText" class="form-control" onchange="setColor(this.value)">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="settings-group">
                <div class="group-title">Типографика</div>
                <div class="group-content">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-row">
                            <label>Цвет текста</label>
                            <input type="color" name="text_color" id="inpTextColor" style="width:100%; height:38px;">
                        </div>
                        <div class="form-row">
                            <label>Выравнивание</label>
                            <select name="text_align" id="inpTextAlign" class="form-control">
                                <option value="left">Слева</option>
                                <option value="center">По центру</option>
                                <option value="right">Справа</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="text-align: right; margin-top:20px;">
                <button type="submit" class="adm-btn adm-btn-save">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
const banners = <?=CUtil::PhpToJSObject($banners)?>;
const sections = <?=CUtil::PhpToJSObject($sections)?>;
const grid = document.getElementById('grid');

function render() {
    grid.innerHTML = '';
    const list = Object.values(banners).sort((a,b) => (parseInt(a.SORT)||500) - (parseInt(b.SORT)||500));

    for(let i=0; i < 8; i++) {
        const b = list[i];
        const el = document.createElement('div');
        el.className = 'slot';
        el.dataset.i = i;
        
        if (b) {
             // Apply Styles
            el.style.backgroundColor = b.COLOR || '#fff';
            if(b.IMAGE_TYPE === 'background' && b.IMAGE) {
                el.style.backgroundImage = `url(${b.IMAGE})`;
                el.style.backgroundSize = 'cover';
                el.style.backgroundPosition = 'center';
            }
            
            // Content
            const align = b.TEXT_ALIGN || 'center';
            const color = b.TEXT_COLOR || '#000000';
            
            if(b.TITLE || b.SUBTITLE) {
                let html = `<div class="slot-content" style="text-align:${align}; color:${color}">`;
                if(b.IMAGE_TYPE === 'icon' && b.IMAGE) {
                    html += `<img src="${b.IMAGE}" style="max-height:50px; margin-bottom:10px; display:inline-block;">`;
                }
                if(b.TITLE) html += `<div style="font-weight:bold; font-size: ${b.TITLE_FONT_SIZE || '18px'}">${b.TITLE}</div>`;
                if(b.SUBTITLE) html += `<div style="opacity:0.8; font-size: ${b.SUBTITLE_FONT_SIZE || '14px'}">${b.SUBTITLE}</div>`;
                html += `</div>`;
                el.innerHTML = html;
            }
            el.onclick = () => openPopup(b.SLOT_INDEX);
        } else {
            el.innerHTML = '<div class="slot-placeholder">Слот '+(i+1)+'<br><small>Настроить</small></div>';
            el.onclick = () => openPopupNew(i);
        }
        grid.appendChild(el);
    }
}

function findFreeSlotIndex() {
    for(let i=1; i<=100; i++) {
        if (!banners[i]) return i;
    }
    return 101; // fallback
}

function openPopupNew(visualIndex) {
    const f = document.getElementById('editForm');
    f.reset();
    
    const newSlotIndex = findFreeSlotIndex();
    document.getElementById('slotIndex').value = newSlotIndex;
    
    const defaultSort = (visualIndex + 1) * 10;
    f.sort.value = defaultSort;
    
    // Set defaults
    setColor('#ffffff');
    document.getElementById('inpTextColor').value = '#000000';
    document.getElementById('inpTextAlign').value = 'center';
    
    document.getElementById('popupTitle').innerText = `Новый блок (Сортировка: ${defaultSort})`;
    document.getElementById('popup').style.display = 'flex';
}

function openPopup(slotIndex) {
    const f = document.getElementById('editForm');
    f.reset();
    document.getElementById('slotIndex').value = slotIndex;
    const b = banners[slotIndex] || {};
    
    document.getElementById('popupTitle').innerText = `Настройка блока #${slotIndex}`;
    
    // Fill fields
    if(b.CATEGORY_ID) f.category_id.value = b.CATEGORY_ID;
    f.title.value = b.TITLE || '';
    f.subtitle.value = b.SUBTITLE || '';
    f.link.value = b.LINK || '';
    f.title_font_size.value = b.TITLE_FONT_SIZE || '';
    f.subtitle_font_size.value = b.SUBTITLE_FONT_SIZE || '';
    f.sort.value = b.SORT || 500;
    
    // Visual
    if(b.IMAGE_TYPE) f.image_type.value = b.IMAGE_TYPE;
    setColor(b.COLOR || '#ffffff');
    if(b.IMAGE) document.getElementById('inpImgUrl').value = b.IMAGE;
    
    // Type
    document.getElementById('inpTextColor').value = b.TEXT_COLOR || '#000000';
    if(b.TEXT_ALIGN) f.text_align.value = b.TEXT_ALIGN;
    
    document.getElementById('popup').style.display = 'flex';
}

function closePopup() { document.getElementById('popup').style.display = 'none'; }
function setColor(c) { 
    document.getElementById('inpColor').value = c; 
    document.getElementById('inpColorText').value = c; 
}

// Logic: Auto-fill from Category
document.getElementById('catSelect').addEventListener('change', function() {
    const sec = sections[this.value];
    if(sec) {
        document.getElementById('inpTitle').value = sec.title;
        document.getElementById('inpSubtitle').value = sec.subtitle;
        document.getElementById('inpLink').value = sec.link;
        if(sec.image) {
            document.getElementById('inpImgUrl').value = sec.image;
        }
    }
});

// Save AJAX
document.getElementById('editForm').onsubmit = async function(e) {
    e.preventDefault();
    let fd = new FormData(this);
    let res = await fetch('mycompany_banner_ajax_save_banner.php', {method:'POST', body:fd});
    let data = await res.json();
    if(data.success) {
        banners[data.data.SLOT_INDEX] = data.data;
        render();
        closePopup();
    } else {
        alert(data.errors.join('\n'));
    }
};

render();
</script>
<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php"); ?>