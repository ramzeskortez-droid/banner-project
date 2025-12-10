<?php
/**
 * Banner Constructor Interface.
 * This page provides a visual grid editor for arranging and editing Blocks (slots) within a single Banner (Set).
 *
 * Terminology Mapping:
 * - UI "Баннер" (Banner)    <=> DB `mycompany_banner_set` (BannerSetTable)
 * - UI "Блок" (Block)      <=> DB `mycompany_banner` (BannerTable)
 */

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
use Bitrix\Main\Loader;
use MyCompany\Banner\BannerTable;
use MyCompany\Banner\BannerSetTable;

// --- Pre-loading data for JS ---
Loader::includeModule("mycompany.banner");
Loader::includeModule("iblock");

// Get the current Banner (Set) ID from request
$setId = (int)($_REQUEST['set_id'] ?? 0);
if ($setId <= 0) {
    // Redirect or show error if no ID is provided
    LocalRedirect("mycompany_banner_settings.php?lang=".LANGUAGE_ID);
}
$set = BannerSetTable::getById($setId)->fetch();
$APPLICATION->SetTitle("Конструктор баннера: " . ($set['NAME'] ?? '...'));

// Fetch all IBlocks and their Sections to use in the "auto-fill" dropdowns in the popup.
$iblocks = [];
$sectionsByIblock = [];
$secRes = \CIBlockSection::GetList(
    ['LEFT_MARGIN'=>'ASC'],
    ['ACTIVE'=>'Y', 'GLOBAL_ACTIVE'=>'Y'],
    false,
    ['ID','NAME','DEPTH_LEVEL','IBLOCK_ID','SECTION_PAGE_URL','DESCRIPTION', 'PICTURE', 'IBLOCK_SECTION_ID']
);
while($sec = $secRes->GetNext()) {
    $sid = $sec['IBLOCK_ID'];
    if (!isset($iblocks[$sid])) {
        $ibRes = \CIBlock::GetByID($sid)->GetNext();
        $iblocks[$sid] = $ibRes['NAME'];
    }
    $sectionsByIblock[$sid][] = [
        'id' => $sec['ID'],
        'name' => str_repeat('. ', $sec['DEPTH_LEVEL'] - 1) . $sec['NAME'],
        'data' => [
            'title' => $sec['NAME'],
            'link' => $sec['SECTION_PAGE_URL'],
            'subtitle' => $sec['DESCRIPTION'] ? strip_tags($sec['DESCRIPTION']) : '',
            'image' => $sec['PICTURE'] ? \CFile::GetPath($sec['PICTURE']) : ''
        ]
    ];
}

// Fetch all existing Blocks (Banners) for this Set and key them by their slot index for easy access in JS.
$bannersRaw = BannerTable::getList(['filter' => ['=SET_ID' => $setId], 'order' => ['SORT' => 'ASC', 'ID' => 'ASC']])->fetchAll();
$banners = [];
foreach($bannersRaw as $b) {
    $banners[$b['SLOT_INDEX']] = $b;
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>

<style>
    .construct-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .construct-wrap { max-width: 1400px; margin: 0 auto; }
    .grid { 
        display: grid; 
        grid-template-columns: repeat(4, 1fr); 
        gap: 15px; 
        width: 100%; 
        box-sizing: border-box;
    }

    .slot {
        position: relative;
        background-color: #f0f0f0;
        border: 2px dashed #ddd;
        border-radius: 8px;
        overflow: hidden;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        min-height: 200px; /* Fallback */
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .slot:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        z-index: 50;
        border-color: #999;
    }

    /* ВОССТАНОВЛЕННЫЕ СТИЛИ ИЗ 1.0.3 (Ключевой момент видимости) */
    .slot[data-i="1"], .slot[data-i="2"], .slot[data-i="3"], .slot[data-i="4"] { 
        grid-column: span 2; 
        height: 300px; 
    }
    .slot[data-i="5"], .slot[data-i="6"], .slot[data-i="7"], .slot[data-i="8"] { 
        grid-column: span 1; 
        height: 200px; 
    }
    
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
        <h2>Сетка блоков</h2>
        <a href="mycompany_banner_settings.php?lang=<?=LANGUAGE_ID?>" class="adm-btn">← К списку баннеров</a>
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

    <!-- The main grid where blocks are displayed -->
    <div class="grid" id="grid">
        <!-- Grid will be rendered by JavaScript -->
    </div>
</div>

<!-- MAIN POPUP -->
<div class="overlay" id="popup">
    <div class="popup" style="width: 850px;">
        <div class="popup-header">
            <h3 style="margin:0" id="popupTitle">Настройка блока</h3>
            <span style="cursor:pointer; font-size:20px; color:#999;" onclick="closePopup()">✕</span>
        </div>
        <form id="editForm">
            <div class="popup-body">
                <!-- Left Column -->
                <div>
                    <div class="settings-group">
                        <div class="group-title">Источник данных (Автозаполнение)</div>
                        <div class="group-content" style="padding-top: 15px;">
                            <div class="form-row">
                                <label>Инфоблок</label>
                                <select id="iblockSelect" class="form-control" onchange="renderCategories(this.value)">
                                    <option value="0">-- Не выбрано --</option>
                                    <?php foreach($iblocks as $id => $name): ?>
                                        <option value="<?=$id?>"><?=htmlspecialcharsbx($name)?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-row">
                                <label>Раздел</label>
                                <select id="catSelect" name="category_id" class="form-control" disabled>
                                    <option value="0">-- Сначала выберите инфоблок --</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="settings-group">
                        <div class="group-title">Контент блока</div>
                        <div class="group-content" style="padding-top: 15px;">
                            <div class="form-row"><label>Заголовок</label><input type="text" name="title" id="inpTitle" class="form-control"></div>
                            <div class="form-row"><label>Анонс</label><textarea name="subtitle" id="inpSubtitle" class="form-control" rows="3"></textarea></div>
                            <div class="form-row"><label>Ссылка</label><input type="text" name="link" id="inpLink" class="form-control"></div>
                            <div class="form-row"><label>Сортировка</label><input type="number" name="sort" id="inpSort" class="form-control"></div>
                        </div>
                    </div>
                </div>
                <!-- Right Column -->
                <div>
                    <div class="settings-group">
                        <div class="group-title">Оформление</div>
                         <div class="group-content" style="padding-top: 15px;">
                            <div class="form-row"><label>Фон (URL или файл)</label>
                                <input type="text" name="image_url" id="inpImgUrl" class="form-control" placeholder="URL изображения">
                                <input type="file" name="image_file" id="inpFile" class="form-control" style="margin-top: 5px;">
                            </div>
                             <div class="form-row"><label>Цвет фона (если нет картинки)</label><input type="color" name="color" id="inpColor" class="form-control" style="height: 38px;"></div>
                             <div class="form-row"><label>Выравнивание текста</label>
                                 <select name="text_align" id="inpTextAlign" class="form-control">
                                     <option value="left">Слева</option>
                                     <option value="center" selected>По центру</option>
                                     <option value="right">Справа</option>
                                 </select>
                             </div>
                             <div class="form-row">
                                <label for="inpTextColor">Цвет текста</label>
                                <input type="color" name="text_color" id="inpTextColor" value="#000000" class="form-control" style="height: 38px;">
                            </div>
                             <div class="form-row" style="display:flex; gap: 10px;">
                                 <div style="flex: 1;"><label>Размер заголовка (px)</label><input type="number" name="title_font_size" id="inpTitleSize" class="form-control"></div>
                                 <div style="flex: 1;"><label>Размер анонса (px)</label><input type="number" name="subtitle_font_size" id="inpSubSize" class="form-control"></div>
                             </div>
                        </div>
                    </div>
                    <div class="settings-group">
                        <div class="group-title">Дополнительные настройки</div>
                        <div class="group-content">
                            <div class="form-row"><button type="button" class="adm-btn" onclick="openAdjuster()">✂ Настроить отображение</button></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="popup-footer">
                <!-- Hidden fields for the form -->
                <input type="hidden" name="slot_index" id="slotIndex">
                <input type="hidden" name="set_id" value="<?=$setId?>">
                <input type="hidden" name="action" value="save_slot">
                <input type="hidden" name="sessid" value="<?=bitrix_sessid()?>">
                <input type="hidden" name="img_scale" id="inpScale"><input type="hidden" name="img_pos_x" id="inpPosX"><input type="hidden" name="img_pos_y" id="inpPosY">
                <button type="submit" class="adm-btn adm-btn-save">Сохранить</button>
            </div>
        </form>
    </div>
</div>

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
// Data passed from PHP
let blocks = <?=CUtil::PhpToJSObject($banners)?>, globalSettings = <?=CUtil::PhpToJSObject($set)?>;
const sectionsData = <?=CUtil::PhpToJSObject($sectionsByIblock)?>;

// A flat map for quick section data lookup by ID
const allSectionsFlat = Object.values(sectionsData).flat().reduce((acc, s) => {
    acc[s.id] = s.data;
    return acc;
}, {});

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
                renderGrid();
                initGlobalState();
            } else {
                alert('Ошибка: ' + (d.errors ? d.errors.join('\\n') : 'Unknown error'));
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
            Object.values(blocks).forEach(b => b.TEXT_COLOR = c);
            renderGrid();
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
            Object.values(blocks).forEach(b => b[field] = newVal);
            // Обновляем визуал кнопки
            if(newVal === 'Y') btn.classList.add('active'); else btn.classList.remove('active');
            renderGrid();
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
            // Update local blocks object
            Object.values(blocks).forEach(b => {
                if(tVal) b.TITLE_FONT_SIZE = tVal + 'px';
                if(sVal) b.SUBTITLE_FONT_SIZE = sVal + 'px';
            });

            renderGrid(); // Перерисовка сетки

            // ВОССТАНОВЛЕНИЕ ЗНАЧЕНИЙ (Фикс исчезновения)
            if(titleInput) titleInput.value = tVal;
            if(subInput) subInput.value = sVal;
        } else {
             alert('Ошибка: ' + (d.errors ? d.errors.join('\\n') : 'Unknown error'));
        }
    });
}

/**
 * Renders the entire grid of 8 blocks.
 */
function renderGrid() {
    grid.innerHTML = '';
    const sortedBlocks = Object.values(blocks).sort((a,b) => (parseInt(a.SORT) || 500) - (parseInt(b.SORT) || 500));
    
    // Create a map of existing blocks by slot_index
    const blockMap = sortedBlocks.reduce((acc, block) => {
        acc[block.SLOT_INDEX] = block;
        return acc;
    }, {});

    for(let i = 1; i <= 8; i++) {
        const block = blockMap[i];
        const el = document.createElement('div');
        el.className = 'slot';
        el.dataset.slotIndex = i;
        el.dataset.i = i; // For CSS grid-column rules

        if (block) {
            // This slot is filled, render its data
            el.style.backgroundColor = block.COLOR || '#fff';
            if(block.IMAGE) {
                el.style.backgroundImage = `url(${block.IMAGE})`;
            }
            
            const content = document.createElement('div');
            content.className = `slot-content text-${block.TEXT_ALIGN || 'center'}`;
            content.style.color = block.TEXT_COLOR || '#000000';
            
            const wrapper = document.createElement('div');
            wrapper.className = 'b-text-wrapper';
            
            // Re-apply global settings if TEXT_BG_SHOW is enabled
            if (globalSettings.TEXT_BG_SHOW === 'Y') {
                wrapper.style.backgroundColor = hexToRgb(globalSettings.TEXT_BG_COLOR, globalSettings.TEXT_BG_OPACITY);
            }

            let innerHTML = '';
            let titleStyle = `font-size: ${block.TITLE_FONT_SIZE || '18px'};`;
            if (block.TITLE_BOLD === 'Y') titleStyle += 'font-weight:bold;'; else titleStyle += 'font-weight:normal;';
            if (block.TITLE_ITALIC === 'Y') titleStyle += 'font-style:italic;';
            if (block.TITLE_UNDERLINE === 'Y') titleStyle += 'text-decoration:underline;';

            let subtitleStyle = `font-size: ${block.SUBTITLE_FONT_SIZE || '14px'};`;
            if (block.SUBTITLE_BOLD === 'Y') subtitleStyle += 'font-weight:bold;'; else subtitleStyle += 'font-weight:normal;';
            if (block.SUBTITLE_ITALIC === 'Y') subtitleStyle += 'font-style:italic;';
            if (block.SUBTITLE_UNDERLINE === 'Y') subtitleStyle += 'text-decoration:underline;';

            if (block.TITLE) innerHTML += `<div class="b-title" style="${titleStyle}">${block.TITLE}</div>`;
            if (block.SUBTITLE) innerHTML += `<div class="b-sub" style="${subtitleStyle}">${block.SUBTITLE}</div>`;
            wrapper.innerHTML = innerHTML;

            content.appendChild(wrapper);
            el.appendChild(content);
            el.onclick = () => openEditPopup(i);
            el.style.cursor = 'pointer';
        } else {
            // This slot is empty, show a placeholder
            el.innerHTML = `<div class="slot-placeholder">Блок #${i}<br><small>Настроить</small></div>`;
            el.onclick = () => openEditPopup(i);
            el.style.cursor = 'pointer';
        }
        grid.appendChild(el);
    }
}

/**
 * Populates the category select dropdown for a given IBlock ID.
 * @param {string} ibId - The IBlock ID.
 */
function renderCategories(ibId) {
    const catSelect = document.getElementById('catSelect');
    catSelect.innerHTML = '<option value="0">-- Не выбрано --</option>';
    catSelect.disabled = true;
    
    if (ibId && sectionsData[ibId]) {
        catSelect.disabled = false;
        sectionsData[ibId].forEach(sec => {
            const opt = document.createElement('option');
            opt.value = sec.id;
            opt.innerHTML = sec.name;
            if (sec.name.startsWith('.')) {
                opt.style.fontWeight = 'bold';
            }
            catSelect.appendChild(opt);
        });
    }
}

/**
 * Opens the popup to edit a block. Can be an existing or a new one.
 * @param {number} slotIndex - The index of the slot (1-8).
 */
function openEditPopup(slotIndex) {
    const form = document.getElementById('editForm');
    form.reset();
    
    const blockData = blocks[slotIndex] || {};
    
    document.getElementById('popupTitle').innerText = `Настройка блока #${slotIndex}`;
    
    // Set hidden fields
    form.slot_index.value = slotIndex;

    // Populate form fields
    form.title.value = blockData.TITLE || '';
    form.subtitle.value = blockData.SUBTITLE || '';
    form.link.value = blockData.LINK || '';
    form.sort.value = blockData.SORT || (slotIndex * 10);
    form.text_align.value = blockData.TEXT_ALIGN || 'center';
    form.color.value = blockData.COLOR || '#ffffff';
    if (blockData.IMAGE) document.getElementById('inpImgUrl').value = blockData.IMAGE;
    document.getElementById('inpFile').value = ''; // Clear file input
    form.img_scale.value = blockData.IMG_SCALE || 100;
    form.img_pos_x.value = blockData.IMG_POS_X || 50;
    form.img_pos_y.value = blockData.IMG_POS_Y || 50;
    const textColorInput = document.getElementById('inpTextColor');
    const textColorWarning = document.getElementById('inpTextColorWarning');
    textColorInput.value = blockData.TEXT_COLOR || '#000000';
    textColorInput.disabled = false;
    textColorWarning.style.display = 'none';

    form.title_font_size.value = parseInt(blockData.TITLE_FONT_SIZE) || 22;
    form.subtitle_font_size.value = parseInt(blockData.SUBTITLE_FONT_SIZE) || 14;

    // Set formatting checkboxes
    document.getElementById('tb_b').checked = (blockData.TITLE_BOLD === 'Y');
    document.getElementById('tb_i').checked = (blockData.TITLE_ITALIC === 'Y');
    document.getElementById('tb_u').checked = (blockData.TITLE_UNDERLINE === 'Y');
    document.getElementById('sb_b').checked = (blockData.SUBTITLE_BOLD === 'Y');
    document.getElementById('sb_i').checked = (blockData.SUBTITLE_ITALIC === 'Y');
    document.getElementById('sb_u').checked = (blockData.SUBTITLE_UNDERLINE === 'Y');

    // Auto-select IBlock/Section if a category_id is set
    document.getElementById('iblockSelect').value = 0;
    renderCategories(0);
    if (blockData.CATEGORY_ID) {
        for (const ibId in sectionsData) {
            const section = sectionsData[ibId].find(s => s.id == blockData.CATEGORY_ID);
            if (section) {
                document.getElementById('iblockSelect').value = ibId;
                renderCategories(ibId);
                document.getElementById('catSelect').value = blockData.CATEGORY_ID;
                break;
            }
        }
    }
    
    document.getElementById('popup').style.display = 'flex';
}

function closePopup() {
    document.getElementById('popup').style.display = 'none';
}

// --- Image Adjuster Logic ---
const adjuster = { 
    preview: null, // Will be set dynamically in openAdjuster
    scale: document.getElementById('adjScale'), 
    posX: document.getElementById('adjPosX'), 
    posY: document.getElementById('adjPosY'), 
    isDragging: false, 
    startX: 0, 
    startY: 0, 
    initPosX: 50,
    initPosY: 50 
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

    // Set the preview element for the adjuster
    adjuster.preview = document.getElementById('adjPreview');
    adjuster.preview.style.backgroundImage = `url(${imgUrl})`;
    
    const contentToClone = visualEl.querySelector('.slot-content');
    if (contentToClone) {
        document.getElementById('adjustTextOverlay').innerHTML = '';
        document.getElementById('adjustTextOverlay').appendChild(contentToClone.cloneNode(true));
    }

    const currentBlock = blocks[slotIndex] || {};
    const curX = parseFloat(document.getElementById('inpPosX').value) || parseFloat(currentBlock.IMG_POS_X) || 50;
    const curY = parseFloat(document.getElementById('inpPosY').value) || parseFloat(currentBlock.IMG_POS_Y) || 50;
    const curScale = parseFloat(document.getElementById('inpScale').value) || parseFloat(currentBlock.IMG_SCALE) || 100;
    
    adjuster.scale.value = curScale;
    adjuster.posX.value = curX;
    adjuster.posY.value = curY;
    
    // --- Logic for the right column (entire grid preview) ---
    const wrapper = document.getElementById('adjFullGridWrapper');
    wrapper.innerHTML = ''; // Clear previous content
    const mainGrid = document.getElementById('grid');
    const cloneGrid = mainGrid.cloneNode(true);
    cloneGrid.id = 'adjFullGrid';
    cloneGrid.style.transform = 'none'; // Reset transform, scale will be applied later
    cloneGrid.querySelectorAll('.slot').forEach(el => el.onclick = null); // Disable clicks on cloned grid
    wrapper.appendChild(cloneGrid);

    // Highlight the currently edited slot in the cloned grid
    const targetSlot = cloneGrid.querySelector(`.slot[data-slot-index="${slotIndex}"]`);
    if(targetSlot) {
        targetSlot.style.backgroundImage = `url(${imgUrl})`; // Ensure background is set
        targetSlot.style.zIndex = 10;
        targetSlot.style.boxShadow = "0 0 0 3px #ff5722, inset 0 0 15px rgba(0,0,0,0.3)";
    }

    // Scale the entire cloned grid to fit the wrapper
    setTimeout(() => {
        const wrapperWidth = wrapper.clientWidth - 40; // Account for padding
        const wrapperHeight = wrapper.clientHeight - 40;
        const gridWidth = cloneGrid.scrollWidth;
        const gridHeight = cloneGrid.scrollHeight;
        if (gridWidth > 0 && gridHeight > 0) {
            const scale = Math.min(wrapperWidth / gridWidth, wrapperHeight / gridHeight);
            cloneGrid.style.transform = `scale(${scale})`;
            cloneGrid.style.transformOrigin = `top left`;
        }
    }, 50); // Small delay to allow DOM to render
    

    updateAdjusterPreview(); // Apply current values to preview
    document.getElementById('adjusterOverlay').style.display = 'flex';
    
    // Re-bind drag logic after adjuster.preview is set
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

    // Update left preview
    if (adjuster.preview) {
        adjuster.preview.style.backgroundSize = `${scale}%`;
        adjuster.preview.style.backgroundPosition = `${posX}% ${posY}%`;
    }
    document.getElementById('scaleVal').innerText = scale;

    // Sync right grid (cloned grid)
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
    if (!blocks[slotIndex]) blocks[slotIndex] = { SLOT_INDEX: slotIndex };
    
    blocks[slotIndex].IMG_SCALE = adjuster.scale.value;
    blocks[slotIndex].IMG_POS_X = adjuster.posX.value;
    blocks[slotIndex].IMG_POS_Y = adjuster.posY.value;
    
    renderGrid();
    closeAdjuster();
}

adjuster.scale.addEventListener('input', updateAdjusterPreview);

// Drag-n-drop logic for left preview
if (adjuster.preview) {
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
        if (adjuster.preview) adjuster.preview.style.cursor = 'grab'; 
    }
};

window.onmouseup = function() { 
    if (adjuster.isDragging) {
        adjuster.isDragging = false; 
        if (adjuster.preview) adjuster.preview.style.cursor = 'grab'; 
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
    const b = Object.values(blocks)[0]; // Берем первый попавшийся
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

// Handle form submission
document.getElementById('editForm').onsubmit = async function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    try {
        const response = await fetch('mycompany_banner_ajax_save_banner.php', { method: 'POST', body: formData });
        const result = await response.json();

        if (result.success && result.data) {
            // Update local data and re-render
            blocks[result.data.SLOT_INDEX] = result.data;
            renderGrid();
            closePopup();
        } else {
            alert('Ошибка сохранения: ' + (result.errors ? result.errors.join('\n') : 'Неизвестная ошибка.'));
        }
    } catch (error) {
        alert('Сетевая ошибка при сохранении.');
        console.error(error);
    }
};

initGlobalSettings();
renderGrid();
initGlobalState();

</script>
<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php"); ?>