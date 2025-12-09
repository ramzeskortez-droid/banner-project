<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
use Bitrix\Main\Loader;
use MyCompany\Banner\BannerTable;
use MyCompany\Banner\BannerSetTable;

Loader::includeModule("mycompany.banner");
Loader::includeModule("iblock");

$setId = (int)($_REQUEST['set_id'] ?? 1);
$set = BannerSetTable::getById($setId)->fetch();

$iblocks = [];
$sectionsByIblock = [];

if(Loader::includeModule("iblock")) {
    // 1. Получаем Инфоблоки
    $ibRes = \CIBlock::GetList(['SORT'=>'ASC'], ['ACTIVE'=>'Y']);
    while($ib = $ibRes->Fetch()) {
        $iblocks[$ib['ID']] = $ib['NAME'];
    }

    // 2. Получаем Разделы
    $secRes = \CIBlockSection::GetList(
        ['LEFT_MARGIN'=>'ASC'], 
        ['ACTIVE'=>'Y', 'GLOBAL_ACTIVE'=>'Y'], 
        false, 
        ['ID','NAME','DEPTH_LEVEL','IBLOCK_ID','SECTION_PAGE_URL','DESCRIPTION', 'PICTURE', 'IBLOCK_SECTION_ID']
    );
    
    while($sec = $secRes->GetNext()) {
        $sid = $sec['IBLOCK_ID'];
        // Сохраняем данные для JS
        $sectionsByIblock[$sid][] = [
            'id' => $sec['ID'],
            'name' => $sec['NAME'],
            'depth' => (int)$sec['DEPTH_LEVEL'],
            'parent' => $sec['IBLOCK_SECTION_ID'],
            'data' => [ // Данные для автозаполнения
                'title' => $sec['NAME'],
                'link' => $sec['SECTION_PAGE_URL'],
                'subtitle' => $sec['DESCRIPTION'] ? strip_tags($sec['DESCRIPTION']) : '',
                'image' => $sec['PICTURE'] ? \CFile::GetPath($sec['PICTURE']) : ''
            ]
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
    .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }

    .slot {
        position: relative;
        background-color: #f0f0f0;
        border: 2px dashed #ddd;
        border-radius: 8px;
        overflow: hidden;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat; /* <-- Добавлено */
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .slot:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        z-index: 50;
        border-color: #999;
    }

    .slot[data-i="1"], .slot[data-i="2"], .slot[data-i="3"], .slot[data-i="4"] { grid-column: span 2; height: 300px; }
    .slot[data-i="5"], .slot[data-i="6"], .slot[data-i="7"], .slot[data-i="8"] { grid-column: span 1; height: 200px; }
    
    .slot-content { height: 100%; display: flex; flex-direction: column; justify-content: center; padding: 20px; box-sizing: border-box; }
    .b-text-wrapper { display: inline-block; padding: 10px 15px; border-radius: 6px; transition: background-color 0.1s linear; }
    .b-title { font-weight: bold; margin-bottom: 5px; }
    .slot-placeholder { text-align: center; color: #bbb; width: 100%; display:flex; align-items:center; justify-content:center; height:100%; flex-direction:column; }

    .text-left { align-items: flex-start; text-align: left; }
    .text-center { align-items: center; text-align: center; }
    .text-right { align-items: flex-end; text-align: right; }

    .overlay { position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.6); z-index: 9990; display: none; align-items: center; justify-content: center; }
    #adjusterOverlay { z-index: 9999; }
    
    #adjusterOverlay .popup {
        width: 95%;
        max-width: 1600px;
        height: 90vh;
    }

    .popup { background: #fdfdfd; width: 800px; max-height: 95vh; display: flex; flex-direction: column; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
    
    #adjusterOverlay .popup-body {
        display: flex;
        height: 100%;
        overflow: hidden;
        padding: 0;
    }

    .popup-header { padding: 15px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .popup-body { padding: 0; overflow-y: auto; flex: 1; }
    .popup-footer { padding: 15px 25px; background: #f7f7f7; text-align: right; border-top: 1px solid #eee; border-radius: 0 0 8px 8px; }

    .adj-col-left { width: 550px; flex-shrink: 0; background: #f8f9fa; border-right: 1px solid #ddd; display: flex; flex-direction: column; height: 100%; }
    .adj-col-right { flex: 1; position: relative; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #e9ecef; padding: 20px; box-sizing: border-box; }
    #adjFullGridWrapper { width: 100%; height: 100%; display:flex; align-items:center; justify-content:center; }
    #adjFullGrid { transform-origin: center center; pointer-events: none; box-shadow: 0 0 20px rgba(0,0,0,0.2); background: #fff; }

    .settings-group { padding: 0; border: 1px solid #bbdefb; overflow: hidden; margin-bottom: 20px; border-radius: 6px; background: #fff; }
    .group-title { background: #e3f2fd; color: #1565c0; padding: 15px 20px; font-weight: bold; border-bottom: 1px solid #90caf9; margin: 0 0 15px 0; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
    .group-content { padding: 20px; }
    .global-settings .form-row { padding: 15px 20px; display: flex; align-items: center; gap: 15px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px;}
    .global-settings .form-row .sep { width: 1px; height: 20px; background: #dee2e6; }
    .form-row { padding: 0 20px; margin-bottom: 15px; }
    .form-row:last-child { margin-bottom: 0; }
    .form-row label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 5px; }
    .form-control { width: 100%; height: 40px; line-height: 38px; padding: 0 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; background: #fff; }

    /* Фикс для многострочного поля Анонс */
    textarea.form-control {
    min-height: 80px !important;  /* Даем базу, но не фиксируем */
    height: auto;                 /* Разрешаем рост */
    line-height: 1.5 !important;
    padding: 10px !important;
    resize: vertical !important;  /* Разрешаем растягивание */
}
    
    .adjust-preview { height: 350px; width: 100%; background-size: 100%; background-position: 50% 50%; background-repeat: no-repeat; border-bottom: 1px solid #ddd; position: relative; background-color: #eee; cursor: grab; overflow: hidden; display: flex; flex-direction: column; justify-content: center; }
    .adjust-text-overlay { pointer-events: none; }
    .adjust-controls { padding: 20px; }

.fmt-row { display: inline-flex; border: 1px solid #ccc; border-radius: 4px; overflow: hidden; vertical-align: middle; }
.fmt-btn-real { display: none; }
.fmt-icon {
    width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
    background: #fff; cursor: pointer; border-right: 1px solid #eee; color: #333;
    font-family: serif; font-size: 14px; font-weight: normal; transition: 0.2s;
}
.fmt-icon:last-child { border-right: none; }
.fmt-icon:hover { background: #f5f5f5; }
/* Активное состояние - ЗЕЛЕНЫЙ */
.fmt-btn-real:checked + .fmt-icon,
.fmt-icon.active {
    background: #4caf50; color: #fff; border-color: #4caf50;
}
/* Маленькие кнопки в попапе */
.local-fmt .fmt-icon { width: 24px; height: 24px; font-size: 12px; }

.pos-controls { display: flex; gap: 15px; margin-bottom: 15px; }
.pos-group { display: flex; flex-direction: column; gap: 5px; }
.pos-group label { font-size: 11px; font-weight: bold; color: #666; }
.btn-row { display: flex; border: 1px solid #ccc; border-radius: 4px; overflow: hidden; }
.pos-btn { flex: 1; border: none; background: #fff; cursor: pointer; padding: 5px 10px; font-size: 12px; border-right: 1px solid #eee; }
.pos-btn:last-child { border-right: none; }
.pos-btn:hover { background: #f0f0f0; }
</style>

<div class="construct-wrap">
    <div class="construct-header">
        <h2>Сетка баннеров</h2>
        <a href="mycompany_banner_settings.php?lang=<?=LANGUAGE_ID?>" class="adm-btn">← Вернуться к списку</a>
    </div>
    <div class="global-settings">
        <div class="form-row flex-center" style="padding: 15px 20px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; display: flex; align-items: center; justify-content: flex-start; gap: 15px; flex-wrap: wrap;">
            
            <label><input type="checkbox" id="globalBgShow" onchange="saveGlobalSettings()"> Фон под текстом</label>
            <input type="color" id="globalBgColor" onchange="saveGlobalSettings()" value="#ffffff">
            <label>Прозрачность:</label>
            <input type="range" id="globalBgOp" min="0" max="100" value="90" oninput="syncOpacity(this.value)" onchange="saveGlobalSettings()">
            <input type="number" id="globalBgOpNum" min="0" max="100" value="90" class="form-control" style="width: 60px; height: 30px;" oninput="syncOpacity(this.value)" onchange="saveGlobalSettings()">
            <span>%</span>

            <div class="sep" style="margin:0 15px; border-left:1px solid #ddd; height:20px;"></div>

            <label><input type="checkbox" id="globalCatMode" onchange="saveGlobalSettings()"> Режим категорий (Авто)</label>
        </div>
        <div class="settings-group global-format" style="background:#fff8e1; border-color:#ffe0b2; margin-top: 15px;">
            <div class="group-title" style="background:#ffecb3; color:#ef6c00; padding:10px 15px;">Быстрое редактирование (Ко всем)</div>
            <div class="form-row flex-center" style="gap:20px; padding:10px 15px; display: flex; align-items: center;">
                
                <!-- Цвет текста -->
                <div style="display:flex; align-items:center; gap:10px;">
                    <label>Цвет текста:</label>
                    <input type="color" id="massColorPicker" value="#000000" style="height:30px; width:40px; padding:0; border:none;">
                    <button type="button" class="adm-btn" onclick="applyMassColor()">Применить ко всем</button>
                </div>

                <div class="sep" style="width:1px; height:20px; background:#ddd;"></div>

                <!-- Размер шрифта -->
                <div style="display:flex; align-items:center; gap:10px;">
                    <label>Размер шрифта:</label>
                    <label for="massTitleSize" style="font-weight:normal;">Заголовок:</label>
                    <input type="number" id="massTitleSize" class="form-control" placeholder="Заголовок px" style="width: 120px; height: 30px;">
                    <label for="massSubtitleSize" style="font-weight:normal; margin-left:5px;">Анонс:</label>
                    <input type="number" id="massSubtitleSize" class="form-control" placeholder="Анонс px" style="width: 120px; height: 30px;">
                    <button type="button" class="adm-btn" onclick="applyMassFontSize()">Применить</button>
                </div>

                <div class="sep" style="width:1px; height:20px; background:#ddd;"></div>

                <!-- Кнопки Заголовков -->
                <div style="display:flex; align-items:center; gap:5px;">
                    <b>Заголовки:</b>
                    <div class="fmt-row">
                        <div class="fmt-icon" id="btn_GB_TB" onclick="toggleMass('TITLE_BOLD')">B</div>
                        <div class="fmt-icon" id="btn_GB_TI" onclick="toggleMass('TITLE_ITALIC')" style="font-style:italic">I</div>
                        <div class="fmt-icon" id="btn_GB_TU" onclick="toggleMass('TITLE_UNDERLINE')" style="text-decoration:underline">U</div>
                    </div>
                </div>
                
                <!-- Кнопки Анонсов -->
                <div style="display:flex; align-items:center; gap:5px;">
                    <b>Анонсы:</b>
                    <div class="fmt-row">
                        <div class="fmt-icon" id="btn_GB_SB" onclick="toggleMass('SUBTITLE_BOLD')">B</div>
                        <div class="fmt-icon" id="btn_GB_SI" onclick="toggleMass('SUBTITLE_ITALIC')" style="font-style:italic">I</div>
                        <div class="fmt-icon" id="btn_GB_SU" onclick="toggleMass('SUBTITLE_UNDERLINE')" style="text-decoration:underline">U</div>
                    </div>
                </div>
            </div>
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
                        <div class="form-row" style="background:#f0f8ff; padding:15px; border-radius:6px; border:1px solid #cce5ff;">
    <label style="color:#004085;">Шаг 1: Выберите Инфоблок</label>
    <select id="iblockSelect" class="form-control" onchange="renderCategories(this.value)">
        <option value="0">-- Не выбрано --</option>
        <?php foreach($iblocks as $id => $name): ?>
            <option value="<?=$id?>"><?=htmlspecialcharsbx($name)?></option>
        <?php endforeach; ?>
    </select>
    
    <div style="height:10px;"></div>
    
    <label style="color:#004085;">Шаг 2: Выберите Раздел</label>
    <select id="catSelect" name="category_id" class="form-control" disabled>
        <option value="0">-- Сначала выберите инфоблок --</option>
    </select>
</div>
                        <div class="form-row">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                <label style="margin:0">Заголовок</label>
                                <div class="fmt-row local-fmt">
                                    <label><input type="checkbox" id="tb_b" name="title_bold" value="Y" class="fmt-btn-real"><span class="fmt-icon">B</span></label>
                                    <label><input type="checkbox" id="tb_i" name="title_italic" value="Y" class="fmt-btn-real"><span class="fmt-icon" style="font-style:italic">I</span></label>
                                    <label><input type="checkbox" id="tb_u" name="title_underline" value="Y" class="fmt-btn-real"><span class="fmt-icon" style="text-decoration:underline">U</span></label>
                                </div>
                            </div>
                            <input type="text" name="title" id="inpTitle" class="form-control">
                        </div>
                        <div class="form-row">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                <label style="margin:0">Анонс</label>
                                <div class="fmt-row local-fmt">
                                    <label><input type="checkbox" id="sb_b" name="subtitle_bold" value="Y" class="fmt-btn-real"><span class="fmt-icon">B</span></label>
                                    <label><input type="checkbox" id="sb_i" name="subtitle_italic" value="Y" class="fmt-btn-real"><span class="fmt-icon" style="font-style:italic">I</span></label>
                                    <label><input type="checkbox" id="sb_u" name="subtitle_underline" value="Y" class="fmt-btn-real"><span class="fmt-icon" style="text-decoration:underline">U</span></label>
                                </div>
                            </div>
                            <textarea name="subtitle" id="inpSubtitle" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="form-row"><label>Ссылка</label><input type="text" name="link" id="inpLink" class="form-control"></div>
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
                         <div class="form-row">
                            <label for="inpTextColor">Цвет текста</label>
                            <input type="color" name="text_color" id="inpTextColor" value="#000000" style="width:100%; height:38px;">
                            <small id="inpTextColorWarning" style="color: #888; display: none; margin-top: 5px;">(Включен единый цвет)</small>
                        </div>
                        <div class="form-row">
                            <label>Размер заголовка (px)</label>
                            <input type="number" name="title_font_size" id="inpTitleSize" class="form-control">
                        </div>
                        <div class="form-row">
                            <label>Размер анонса (px)</label>
                            <input type="number" name="subtitle_font_size" id="inpTextSize" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="popup-footer"><button type="submit" class="adm-btn adm-btn-save">Сохранить</button></div>
    </form>
</div></div>

<!-- ADJUSTER POPUP -->
<div id="adjusterOverlay" class="overlay"><div class="popup">
    <div class="popup-header"><h3>Настройка отображения <small>(Тяните мышкой для сдвига)</small></h3></div>
    <div class="popup-body">
        <div class="adj-col-left">
            <div class="adjust-preview" id="adjPreview"><div class="adjust-text-overlay" id="adjustTextOverlay"></div></div>
            <div class="adjust-controls group-content" style="overflow-y: auto;">
                <div class="pos-controls">
                    <div class="pos-group">
                        <label>Горизонталь:</label>
                        <div class="btn-row">
                            <button type="button" class="pos-btn" onclick="setPos('x', 0)">Слева</button>
                            <button type="button" class="pos-btn" onclick="setPos('x', 50)">Центр</button>
                            <button type="button" class="pos-btn" onclick="setPos('x', 100)">Справа</button>
                        </div>
                    </div>
                    <div class="pos-group">
                        <label>Вертикаль:</label>
                        <div class="btn-row">
                            <button type="button" class="pos-btn" onclick="setPos('y', 0)">Верх</button>
                            <button type="button" class="pos-btn" onclick="setPos('y', 50)">Центр</button>
                            <button type="button" class="pos-btn" onclick="setPos('y', 100)">Низ</button>
                        </div>
                    </div>
                </div>
                <div class="form-row"><label>Масштаб: <span id="scaleVal">100</span>%</label><input type="range" id="adjScale" min="10" max="250" value="100" class="form-control"></div>
                <input type="hidden" id="adjPosX"><input type="hidden" id="adjPosY">
            </div>
        </div>
        <div class="adj-col-right">
            <div id="adjFullGridWrapper"></div>
        </div>
    </div>
    <div class="popup-footer"><button type="button" class="adm-btn adm-btn-save" onclick="applyAdjustments()">Применить</button><button type="button" class="adm-btn" onclick="closeAdjuster()">Отмена</button></div>
</div></div>

<script>
let banners = <?=CUtil::PhpToJSObject($banners)?>, globalSettings = <?=CUtil::PhpToJSObject($set)?>;
const sectionsData = <?=CUtil::PhpToJSObject($sectionsByIblock)?>;
// Глобальный справочник для быстрого доступа к данным по ID раздела
let allSectionsFlat = {}; 

// Инициализация плоского списка (для автозаполнения при открытии)
for(let ibId in sectionsData) {
    sectionsData[ibId].forEach(s => {
        allSectionsFlat[s.id] = s.data;
    });
}

const grid = document.getElementById('grid');

function hexToRgb(hex, opacity) { let r=0,g=0,b=0; if(!hex) hex='#ffffff'; if (hex.length==4){r=parseInt(hex[1]+hex[1],16);g=parseInt(hex[2]+hex[2],16);b=parseInt(hex[3]+hex[3],16);}else if(hex.length==7){r=parseInt(hex.substring(1,3),16);g=parseInt(hex.substring(3,5),16);b=parseInt(hex.substring(5,7),16);} return `rgba(${r},${g},${b},${opacity/100})`; }

/**
 * Live-update opacity without re-rendering the whole grid.
 * This prevents flickering and focus loss on range/number inputs.
 * @param {number} val Opacity value from 0 to 100.
 */
function syncOpacity(val) {
    // 1. Sync both inputs
    document.getElementById('globalBgOp').value = val;
    document.getElementById('globalBgOpNum').value = val;

    // 2. Get current color and calculate RGBA
    const color = document.getElementById('globalBgColor').value;
    const rgbaColor = hexToRgb(color, val);

    // 3. Apply style directly to all text wrappers
    document.querySelectorAll('.b-text-wrapper').forEach(el => {
        el.style.backgroundColor = rgbaColor;
    });
}

/**
 * Saves global settings via AJAX. Called onchange/onblur.
 */
function saveGlobalSettings() {
    const data = new FormData();
    data.append('action', 'save_set_settings');
    data.append('set_id', globalSettings.ID || 1);
    data.append('sessid', '<?=bitrix_sessid()?>');
    
    data.append('show', document.getElementById('globalBgShow').checked ? 'Y' : 'N');
    data.append('color', document.getElementById('globalBgColor').value);
    data.append('opacity', document.getElementById('globalBgOp').value);
    
    data.append('category_mode', document.getElementById('globalCatMode').checked ? 'Y' : 'N');

    fetch('mycompany_banner_ajax_save_banner.php', {method:'POST', body:data})
        .then(res => res.json())
        .then(d => {
            if(d.success) {
                globalSettings = d.data;
                render();
                initGlobalState();
            } else {
                alert('Ошибка: ' + (d.errors ? d.errors.join('\n') : 'Unknown error'));
            }
        });
}


function applyMassColor() {
    const c = document.getElementById('massColorPicker').value;
    const fd = new FormData();
    fd.append('action', 'save_mass_color');
    fd.append('set_id', '<?=$setId?>');
    fd.append('color', c);
    fd.append('sessid', '<?=bitrix_sessid()?>');
    fetch('mycompany_banner_ajax_save_banner.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
        if(d.success) {
            Object.values(banners).forEach(b => b.TEXT_COLOR = c);
            render();
            initGlobalState();
        }
    });
}

function toggleMass(field) {
    const btn = event.currentTarget;
    // Если кнопка УЖЕ активна (зеленая) -> значит хотим выключить всем (N)
    // Если не активна -> включаем всем (Y)
    const newVal = btn.classList.contains('active') ? 'N' : 'Y';

    const fd = new FormData();
    fd.append('action', 'save_mass_format');
    fd.append('set_id', '<?=$setId?>');
    fd.append('field', field);
    fd.append('value', newVal);
    fd.append('sessid', '<?=bitrix_sessid()?>');

    fetch('mycompany_banner_ajax_save_banner.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
        if(d.success) {
            Object.values(banners).forEach(b => b[field] = newVal);
            // Обновляем визуал кнопки
            if(newVal === 'Y') btn.classList.add('active'); else btn.classList.remove('active');
            render();
            initGlobalState();
        }
    });
}

function applyMassFontSize() {
    const titleInput = document.getElementById('massTitleSize');
    const subInput = document.getElementById('massSubtitleSize');
    const tVal = titleInput.value;
    const sVal = subInput.value;

    if (!tVal && !sVal) {
        alert('Введите хотя бы один размер шрифта.');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'save_mass_font_size');
    fd.append('set_id', '<?=$setId?>');
    fd.append('sessid', '<?=bitrix_sessid()?>');
    if (tVal) {
        fd.append('title_size', tVal);
    }
    if (sVal) {
        fd.append('subtitle_size', sVal);
    }

    fetch('mycompany_banner_ajax_save_banner.php', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
        if(d.success) {
            // Update local banners object
            Object.values(banners).forEach(b => {
                if(tVal) b.TITLE_FONT_SIZE = tVal + 'px';
                if(sVal) b.SUBTITLE_FONT_SIZE = sVal + 'px';
            });

            render(); // Перерисовка сетки

            // ВОССТАНОВЛЕНИЕ ЗНАЧЕНИЙ (Фикс исчезновения)
            if(titleInput) titleInput.value = tVal;
            if(subInput) subInput.value = sVal;
        } else {
             alert('Ошибка: ' + (d.errors ? d.errors.join('\\n') : 'Unknown error'));
        }
    });
}

function render() {
    grid.innerHTML = '';
    const list = Object.values(banners).sort((a,b) => (parseInt(a.SORT)||500) - (parseInt(b.SORT)||500));
    for(let i=0; i < 8; i++) {
        const b = list[i], el = document.createElement('div');
        el.className = 'slot'; el.dataset.slotIndex = b ? b.SLOT_INDEX : (i + 1); el.dataset.i = i + 1;
        if (b) {
            el.style.backgroundColor = b.COLOR || '#fff';
            if(b.IMAGE) { el.style.backgroundImage = `url(${b.IMAGE})`; el.style.backgroundSize = `${b.IMG_SCALE || 100}%`; el.style.backgroundPosition = `${b.IMG_POS_X || 50}% ${b.IMG_POS_Y || 50}%`; }
            
            const content = document.createElement('div');
            content.className = `slot-content text-${b.TEXT_ALIGN || 'center'}`;
            content.style.color = b.TEXT_COLOR || '#000000';
            
            const wrapper = document.createElement('div');
            wrapper.className = 'b-text-wrapper';
            if (globalSettings.TEXT_BG_SHOW === 'Y') {
                wrapper.style.backgroundColor = hexToRgb(globalSettings.TEXT_BG_COLOR, globalSettings.TEXT_BG_OPACITY);
            }
            
            let titleStyle = `font-size: ${b.TITLE_FONT_SIZE || '18px'};`;
            if (b.TITLE_BOLD === 'Y') titleStyle += 'font-weight:bold;'; else titleStyle += 'font-weight:normal;';
            if (b.TITLE_ITALIC === 'Y') titleStyle += 'font-style:italic;';
            if (b.TITLE_UNDERLINE === 'Y') titleStyle += 'text-decoration:underline;';

            let subtitleStyle = `font-size: ${b.SUBTITLE_FONT_SIZE || '14px'};`;
            if (b.SUBTITLE_BOLD === 'Y') subtitleStyle += 'font-weight:bold;'; else subtitleStyle += 'font-weight:normal;';
            if (b.SUBTITLE_ITALIC === 'Y') subtitleStyle += 'font-style:italic;';
            if (b.SUBTITLE_UNDERLINE === 'Y') subtitleStyle += 'text-decoration:underline;';

            let innerHTML = '';
            if (b.TITLE) innerHTML += `<div class="b-title" style="${titleStyle}">${b.TITLE}</div>`;
            if (b.SUBTITLE) innerHTML += `<div class="b-sub" style="${subtitleStyle}">${b.SUBTITLE}</div>`;
            wrapper.innerHTML = innerHTML;

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

function renderCategories(ibId) {
    const catSelect = document.getElementById('catSelect');
    catSelect.innerHTML = '<option value="0">-- Не выбрано --</option>';
    
    if (!ibId || !sectionsData[ibId]) {
        catSelect.disabled = true;
        return;
    }
    
    catSelect.disabled = false;
    const list = sectionsData[ibId];
    
    list.forEach(sec => {
        const opt = document.createElement('option');
        opt.value = sec.id;
        let prefix = '';
        if(sec.depth > 1) prefix = '&nbsp;&nbsp;'.repeat(sec.depth - 1) + '↳ ';
        
        opt.innerHTML = prefix + sec.name;
        if(sec.depth === 1) opt.style.fontWeight = 'bold';
        
        catSelect.appendChild(opt);
    });
}

function openPopupNew(visualIndex) {
    const f = document.getElementById('editForm');
    f.reset();
    document.getElementById('slotIndex').value = findFreeSlotIndex();
    const sort = (visualIndex + 1) * 10;
    f.sort.value = sort;
    f.text_align.value = 'center';
    document.getElementById('popupTitle').innerText = `Новый блок (Сортировка: ${sort})`;
    
    // Установка дефолтных значений для шрифтов
    f.title_font_size.value = 22;
    f.subtitle_font_size.value = 14;

    // Reset selects
    document.getElementById('iblockSelect').value = 0;
    renderCategories(0);

    document.getElementById('popup').style.display = 'flex';
}

function openPopup(slotIndex) {
    const f = document.getElementById('editForm');
    f.reset();
    document.getElementById('slotIndex').value = slotIndex;
    const b = banners[slotIndex] || {};
    document.getElementById('popupTitle').innerText = `Настройка блока #${slotIndex}`;
    
    // Попытка найти и восстановить выбранные селекты
    if(b.CATEGORY_ID) {
        let foundIb = 0;
        for(let ibId in sectionsData) {
            if(sectionsData[ibId].find(s => s.id == b.CATEGORY_ID)) {
                foundIb = ibId;
                break;
            }
        }
        if(foundIb) {
            document.getElementById('iblockSelect').value = foundIb;
            renderCategories(foundIb);
            document.getElementById('catSelect').value = b.CATEGORY_ID;
        }
    } else {
        document.getElementById('iblockSelect').value = 0;
        renderCategories(0);
    }

    f.title.value = b.TITLE || '';
    f.subtitle.value = b.SUBTITLE || '';
    f.link.value = b.LINK || '';
    f.sort.value = b.SORT || 500;
    f.text_align.value = b.TEXT_ALIGN || 'center';
    f.color.value = b.COLOR || '#ffffff';
    if (b.IMAGE) document.getElementById('inpImgUrl').value = b.IMAGE;
    f.img_scale.value = b.IMG_SCALE || 100;
    f.img_pos_x.value = b.IMG_POS_X || 50;
    f.img_pos_y.value = b.IMG_POS_Y || 50;
    const textColorInput = document.getElementById('inpTextColor');
    const textColorWarning = document.getElementById('inpTextColorWarning');
    textColorInput.value = b.TEXT_COLOR || '#000000';
    textColorInput.disabled = false;
    textColorWarning.style.display = 'none';

    // Превращаем "20px" в 20, чтобы input type="number" это принял
    f.title_font_size.value = parseInt(b.TITLE_FONT_SIZE) || 20;
    f.subtitle_font_size.value = parseInt(b.SUBTITLE_FONT_SIZE) || 14;

    // Set formatting checkboxes
    document.getElementById('tb_b').checked = (b.TITLE_BOLD === 'Y');
    document.getElementById('tb_i').checked = (b.TITLE_ITALIC === 'Y');
    document.getElementById('tb_u').checked = (b.TITLE_UNDERLINE === 'Y');
    document.getElementById('sb_b').checked = (b.SUBTITLE_BOLD === 'Y');
    document.getElementById('sb_i').checked = (b.SUBTITLE_ITALIC === 'Y');
    document.getElementById('sb_u').checked = (b.SUBTITLE_UNDERLINE === 'Y');

    document.getElementById('popup').style.display = 'flex';
}
function closePopup() { document.getElementById('popup').style.display = 'none'; }

const adjuster = { 
    preview: document.getElementById('adjPreview'),
    scale: document.getElementById('adjScale'), 
    posX: document.getElementById('adjPosX'), 
    posY: document.getElementById('adjPosY'), 
    isDragging: false, 
    startX: 0, 
    startY: 0, 
    initPosX: 50, 
    initPosY: 50,
};

function setPos(axis, val) {
    if (axis === 'x') {
        adjuster.posX.value = val;
    } else {
        adjuster.posY.value = val;
    }
    updateAdjusterPreview();
}

function openAdjuster() {
    const slotIndex = document.getElementById('slotIndex').value;
    const visualEl = document.querySelector(`.slot[data-slot-index='${slotIndex}']`);
    if (!visualEl) { console.error('Could not find visual element for slot', slotIndex); return; }

    let imgUrl = document.getElementById('inpImgUrl').value; 
    const file = document.getElementById('inpFile').files[0];
    if(file) imgUrl = URL.createObjectURL(file);
    if(!imgUrl) { alert('Сначала выберите изображение'); return; }

    // Настраиваем левое превью
    adjuster.preview.style.backgroundImage = `url(${imgUrl})`;
    const contentToClone = visualEl.querySelector('.slot-content');
    if (contentToClone) {
        document.getElementById('adjustTextOverlay').innerHTML = '';
        document.getElementById('adjustTextOverlay').appendChild(contentToClone.cloneNode(true));
    }

    const currentBanner = banners[slotIndex] || {};
    const rawX = document.getElementById('inpPosX').value !== '' ? document.getElementById('inpPosX').value : currentBanner.IMG_POS_X;
    const rawY = document.getElementById('inpPosY').value !== '' ? document.getElementById('inpPosY').value : currentBanner.IMG_POS_Y;
    const rawScale = document.getElementById('inpScale').value !== '' ? document.getElementById('inpScale').value : currentBanner.IMG_SCALE;

    const curX = (rawX !== undefined && rawX !== null && rawX !== '') ? parseFloat(rawX) : 50;
    const curY = (rawY !== undefined && rawY !== null && rawY !== '') ? parseFloat(rawY) : 50;
    const curScale = (rawScale !== undefined && rawScale !== null && rawScale !== '') ? parseFloat(rawScale) : 100;
    
    adjuster.scale.value = curScale;
    adjuster.posX.value = curX;
    adjuster.posY.value = curY;
    
    // --- Логика для правой колонки (весь баннер) ---

    // 1. Подготовка правой части
    const wrapper = document.getElementById('adjFullGridWrapper');
    wrapper.innerHTML = ''; // Очистка
    const mainGrid = document.getElementById('grid');
    const cloneGrid = mainGrid.cloneNode(true);
    cloneGrid.id = 'adjFullGrid';
    cloneGrid.style.transform = 'none'; // Сброс, масштаб зададим позже
    // Отключаем клики
    cloneGrid.querySelectorAll('.slot').forEach(el => el.onclick = null);
    wrapper.appendChild(cloneGrid);

    // 2. Установка текущей картинки
    const currentImgUrl = adjuster.preview.style.backgroundImage;
    const targetSlot = cloneGrid.querySelector(`.slot[data-slot-index="${slotIndex}"]`);
    if(targetSlot) {
        targetSlot.style.backgroundImage = currentImgUrl;
        targetSlot.style.zIndex = 10;
        targetSlot.style.boxShadow = "0 0 0 3px #ff5722, inset 0 0 15px rgba(0,0,0,0.3)";
    }

    // 3. Масштабирование
    setTimeout(() => {
        const wW = wrapper.clientWidth - 40;
        const wH = wrapper.clientHeight - 40;
        const gW = cloneGrid.scrollWidth;
        const gH = cloneGrit.scrollHeight;
        if (gW > 0 && gH > 0) {
            const scale = Math.min(wW / gW, wH / gH);
            cloneGrid.style.transform = `scale(${scale})`;
        }
    }, 50);

    updateAdjusterPreview();
    document.getElementById('adjusterOverlay').style.display = 'flex';
    
    adjuster.preview = document.getElementById('adjPreview'); // Обновляем ссылку на новый элемент в DOM
    // Восстанавливаем логику перетаскивания
    adjuster.preview.onmousedown = function(e) {
        e.preventDefault();
        adjuster.isDragging = true;
        adjuster.startX = e.clientX;
        adjuster.startY = e.clientY;
        adjuster.initPosX = parseFloat(adjuster.posX.value);
        adjuster.initPosY = parseFloat(adjuster.posY.value);
        adjuster.preview.style.cursor = 'grabbing';
    };
}

function closeAdjuster() { document.getElementById('adjusterOverlay').style.display = 'none'; }

function updateAdjusterPreview() {
    const scale = adjuster.scale.value;
    const posX = adjuster.posX.value;
    const posY = adjuster.posY.value;

    // Обновление левого превью
    adjuster.preview.style.backgroundSize = `${scale}%`;
    adjuster.preview.style.backgroundPosition = `${posX}% ${posY}%`;
    document.getElementById('scaleVal').innerText = scale;

    // Синхронизация правого грида
    const slotIndex = document.getElementById('slotIndex').value;
    const liveSlot = document.querySelector(`#adjFullGrid .slot[data-slot-index="${slotIndex}"]`);
    if(liveSlot) {
        liveSlot.style.backgroundSize = `${scale}%`;
        liveSlot.style.backgroundPosition = `${posX}% ${posY}%`;
        liveSlot.style.backgroundRepeat = 'no-repeat';
    }
}

function applyAdjustments() {
    document.getElementById('inpScale').value = adjuster.scale.value;
    document.getElementById('inpPosX').value = adjuster.posX.value;
    document.getElementById('inpPosY').value = adjuster.posY.value;
    
    const slotIndex = document.getElementById('slotIndex').value;
    if (!banners[slotIndex]) banners[slotIndex] = { SLOT_INDEX: slotIndex };
    
    banners[slotIndex].IMG_SCALE = adjuster.scale.value;
    banners[slotIndex].IMG_POS_X = adjuster.posX.value;
    banners[slotIndex].IMG_POS_Y = adjuster.posY.value;
    
    render();
    closeAdjuster();
}

adjuster.scale.addEventListener('input', updateAdjusterPreview);

// Drag-n-drop логика для левого превью
adjuster.preview.onmousedown = function(e) { 
    e.preventDefault(); 
    adjuster.isDragging = true; 
    adjuster.startX = e.clientX; 
    adjuster.startY = e.clientY; 
    adjuster.initPosX = parseFloat(adjuster.posX.value); 
    adjuster.initPosY = parseFloat(adjuster.posY.value); 
    adjuster.preview.style.cursor = 'grabbing'; 
};

window.onmousemove = function(e) { 
    if(!adjuster.isDragging) return;
    let newX = adjuster.initPosX + ((e.clientX - adjuster.startX) * 0.2); 
    let newY = adjuster.initPosY + ((e.clientY - adjuster.startY) * 0.2); 
    adjuster.posX.value = Math.max(0, Math.min(100, newX)).toFixed(2); 
    adjuster.posY.value = Math.max(0, Math.min(100, newY)).toFixed(2); 
    updateAdjusterPreview(); 
};

window.onmouseup = function() { 
    if (adjuster.isDragging) {
        adjuster.isDragging = false; 
        adjuster.preview.style.cursor = 'grab'; 
    }
};

function initGlobalSettings() {
    if(!globalSettings) return;
    document.getElementById('globalBgShow').checked = globalSettings.TEXT_BG_SHOW === 'Y';
    document.getElementById('globalBgColor').value = globalSettings.TEXT_BG_COLOR || '#ffffff';
    document.getElementById('globalBgOp').value = globalSettings.TEXT_BG_OPACITY || 90;
    document.getElementById('globalBgOpNum').value = globalSettings.TEXT_BG_OPACITY || 90;
    
    
    document.getElementById('globalCatMode').checked = globalSettings.CATEGORY_MODE === 'Y';
}

function initGlobalState() {
    const b = Object.values(banners)[0]; // Берем первый попавшийся
    if (b) {
        const tgl = (id, active) => {
            const el = document.getElementById(id);
            if (el) {
                if(active) el.classList.add('active'); else el.classList.remove('active');
            }
        };
        tgl('btn_GB_TB', b.TITLE_BOLD === 'Y');
        tgl('btn_GB_TI', b.TITLE_ITALIC === 'Y');
        tgl('btn_GB_TU', b.TITLE_UNDERLINE === 'Y');
        tgl('btn_GB_SB', b.SUBTITLE_BOLD === 'Y');
        tgl('btn_GB_SI', b.SUBTITLE_ITALIC === 'Y');
        tgl('btn_GB_SU', b.SUBTITLE_UNDERLINE === 'Y');
        
        // Подставляем значения в глобальные инпуты, очищая от 'px'
        const tSize = parseInt(b.TITLE_FONT_SIZE) || 22;
        const sSize = parseInt(b.SUBTITLE_FONT_SIZE) || 14;

        const massT = document.getElementById('massTitleSize');
        const massS = document.getElementById('massSubtitleSize');

        if(massT && !massT.value) massT.value = tSize; 
        if(massS && !massS.value) massS.value = sSize;
    }
}

document.getElementById('catSelect').addEventListener('change', function() {
    const secId = this.value;
    const data = allSectionsFlat[secId];
    if(data) {
        document.getElementById('inpTitle').value = data.title;
        document.getElementById('inpSubtitle').value = data.subtitle;
        document.getElementById('inpLink').value = data.link;
        if(data.image) document.getElementById('inpImgUrl').value = data.image;
    }
});

document.getElementById('editForm').onsubmit = async function(e) { e.preventDefault(); let fd = new FormData(this); let res = await fetch('mycompany_banner_ajax_save_banner.php', {method:'POST', body:fd}); let data = await res.json(); if(data.success) { banners[data.data.SLOT_INDEX] = data.data; render(); initGlobalState(); closePopup(); } else { alert(data.errors.join('\n')); } };

initGlobalSettings();
render();
initGlobalState();
</script>
<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php"); ?>
